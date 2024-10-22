<?php

require_once '../include.php';
$configFile = getenv('WORK_DIR') . '/configs/supervisord.conf';

checkSupervisorCfg ('modbus');
// checkSupervisorCfg ('modbus_polling');
// checkSupervisorCfg ('dali_polling');
// checkSupervisorCfg ('curtain_polling');


//* FUNCTIONS */

// Функция получения несистемных процессов из конфига supervisord
function getSupervisorProcs()
{
    global $configFile;

    // Список системных процессов
    $systemProcs = [
        'nginx',
        'phpfpm',
        'cron',
        'beanstalkd',
        'mediamtx',
    ];
    
    // Получаем список несистемных процессов из конфига supervisord
    $supervisorPrograms = preg_grep('/^\[program:/', file($configFile));
    foreach ($supervisorPrograms as $line)
    {
        $cfgLine = explode(':', $line);
        $procsArray[] = preg_replace( '/[\W]/', '', $cfgLine[1]);
    }
    $procsArray = array_diff($procsArray, $systemProcs);
    return $procsArray;
}

// Функция определения элементов для добавления/удаления в файл конфигурации supervisord
function checkSupervisorCfg(string $subject)
{
    // Получаем список id в БД
    $dbIds = getIdArrayFromDb($subject);

    // Получаем список id в файле конфигурации supervisord
    $cfgIds = getIdArrayFromCfg($subject);

    // Выбираем id модбас шин, которые присутствуют и в файле конфигурации supervisord, и в БД
    $commonElements = array_intersect($dbIds, $cfgIds);

    // Проверяем есть ли шины в БД, которых нет в файле конфигурации supervisord
    // Если есть, то добавляем в файл конфигурации
    $addToCfgIds = array_diff($dbIds, $commonElements);
    if ($addToCfgIds)
    {
        foreach($addToCfgIds as $id) addToSupervisorConfig ($subject, $id);
    }

    // Проверяем есть ли шины в файле конфигурации supervisord, которых нет в БД
    // Если есть, то удаляем из файла конфигурации
    $delFromCfgIds = array_diff($cfgIds, $commonElements);
    if ($delFromCfgIds)
    {
        foreach($delFromCfgIds as $id) deleteFromSupervisorConfig ($subject, $id);
    }
}

// Функция получения списка id элементов необходимого типа из БД
function getIdArrayFromDb(string $subject)
{
    $idArrayFromDb = [];
    if ($subject == 'modbus' || $subject == 'modbus_polling') $idArrayFromDb = Rs485::getBuses();
    if ($subject == 'dali_polling') $idArrayFromDb = Dali::getDaliBuses();
    // if ($subject == 'curtain_polling') $idArrayFromDb = Curtain::getRsMotors();

    return $idArrayFromDb;
}

// Функция получения списка id элементов необходимого типа из файла конфигурации supervisord
function getIdArrayFromCfg(string $subject)
{
    $procsArray = getSupervisorProcs();

    $idArrayFromCfg = [];
    foreach ($procsArray as $procName)
    {
        if (str_contains($procName, $subject))
        {
            $idArrayFromCfg[] = (int)substr($procName, strpos($procName, 'id') + 2);
        }
    }
    // Для модбас шины в конфиге присутсвуют 2 скрипта, поэтому нужно оставить только одно вхождение id
    if ($idArrayFromCfg) $idArrayFromCfg = array_unique($idArrayFromCfg);
    return $idArrayFromCfg;
}

// Функция добавления процесса в файл конфигурации supervisord
function addToSupervisorConfig(string $subject, int $subjectId)
{
    global $configFile;

    if ($subject == 'modbus') $scriptName = 'modbus_queue.php';
    if ($subject == 'modbus_polling') $scriptName = 'modbus_polling_loop.php';
    if ($subject == 'dali_polling') $scriptName = 'dali_polling_loop.php';
    // if ($subject == 'curtain_polling') $scriptName = 'curtain_polling_loop.php';

    
    $query = 'grep ' . escapeshellarg("\[program:${subject}_id${subjectId}\]") . ' ' .  $configFile;
    
    if (!exec($query))
    {
        $programBlock = <<<EOD

        [program:${subject}_id${subjectId}]
        command = php $scriptName $subjectId -DFOREGROUND
        directory = %(ENV_WORK_DIR)s/server/scripts
        autostart = true
        autorestart = true

        EOD;

        $contents = file_get_contents($configFile);
        $contents .= $programBlock;
        $contents = preg_replace("/([\r\n]{4,}|[\n]{2,}|[\r]{2,})/", "\n\n", $contents);
        file_put_contents($configFile, $contents);
        exec("supervisorctl reread");
        exec("supervisorctl add ${subject}_id${subjectId}");
    }
}

// Функция удаления процесса в файл конфигурации supervisord
function deleteFromSupervisorConfig(string $subject, int $subjectId)
{
    global $configFile;

    exec("supervisorctl stop ${subject}_id${subjectId}");
    exec("supervisorctl remove ${subject}_id${subjectId}");

    $contents = file_get_contents($configFile);
    $contents = preg_replace("/(?s)(\[program:${subject}_id${subjectId}]).*?((?=\[)|(?=$))/", '', $contents);
    $contents = preg_replace("/([\r\n]{4,}|[\n]{2,}|[\r]{2,})/", "\n\n", $contents);
    file_put_contents($configFile, $contents);
}
