<?php
/**
 * Основной скрипт, который запускается при возникновении события на любом из мегадевайсов. Реализовано замыкание
 * контактов какого-либо порта
 */

require_once 'include.php';
flush();
$debug = true;
$params = null;
$logstr = '';
$log = '';
$file = 'md.log';
$ports = []; // Массив из объектов портов, которые отправили данные о сработке
       
$url = $_SERVER['REQUEST_URI'];
// $url ='/md.php?pt=2&cnt=22';
$ip_device = $_SERVER['REMOTE_ADDR'];
// $ip_device = '10.200.3.11';

// Определяем переданные параметры
parse_str(parse_url($url, PHP_URL_QUERY), $parametersArray);

if ($debug)
{
    $q = parse_url($url, PHP_URL_QUERY);
    file_put_contents($file, $q . PHP_EOL, FILE_APPEND);
          
    // // АЦП (ADC). При переходе значения через уставку
    // // v - текущая уставка
    // if (isset($parametersArray['v'])) $v = $parametersArray['v'];
    // // dir - направление перехода (снизу вверх - 1, сверху вниз - 0)
    // if (isset($parametersArray['dir'])) $dir = $parametersArray['dir'];
    
    foreach ($parametersArray as $key=>$item)
    {
        // Получаем номер порта, вызвавшего скрипт
        if ($key == 'pt')
        {
            $pt = $item;
            $logstr .= 'порт: ' . $pt . PHP_EOL;
        }
                
        // Определяем текущее количество сработок порта
        if ($key == 'cnt')
        {
            $cnt = $item;
            $logstr .= 'кол-во нажатий: ' . $cnt . PHP_EOL;
        }
    
        // m = 1 - при размыкании порта
        // m = 2 - при длительном нажатии 
        if ($key == 'm')
        {
            $m = $item;
            $logstr .= 'm = ' . $m . PHP_EOL;
        }
    
        // Click-mode
        // click = 1 - одиночное нажатие
        // click = 2 - двойное нажатие
        if ($key == 'click')
        {
            $click = $item;
            $logstr .= 'click = ' . $click . PHP_EOL;
        }
                
        // Проверяем наличие других параметров
    
        // Флаг перезагрузки контроллера
        if ($key == 'st')
        {
            $st = $item;
            $logstr .= 'Ctr rebooted: ' . $st . PHP_EOL;
        }
    
        // Модули расширения
        if (str_contains($key, 'ext'))
        {
            // Номер порта модуля расширения
            $extPort = explode('ext', $key)[1];
            $logstr .= 'ext port: ' . $extPort . PHP_EOL;
            // Состояние порта (1 - замкнут, 0 - разомкнут)
            $extStatus = $item;
            $logstr .= 'ext status: ' . $extStatus . PHP_EOL;
        }
    
        // Wiegand
        // wg - адрес ключа или набранный код
        if ($key == 'wg')
        {
            $wg = $item;
            $logstr .= 'wg key code: ' . $wg . PHP_EOL;
        }
    }
    
    file_put_contents($file, $logstr . PHP_EOL, FILE_APPEND);
}


$sql = System::$db->query(" SELECT `description` AS 'device_name'
                            FROM `devices`
                            WHERE `ip_address` = '$ip_device'");
$deviceName = $sql->fetch(PDO::FETCH_OBJ)->device_name;
        
if (array_key_exists('pt', $parametersArray))
{
    Megad::$ip_device = $ip_device;

    $mega = new Megad();

    $log = "Устройство $deviceName ($ip_device). ";

    if (array_key_exists('click', $parametersArray) || array_key_exists('m', $parametersArray))
    {
        foreach ($parametersArray as $key=>$value)
        {
            if ($key == 'pt')
            {
                $port = $mega->get($value); //взяли номер порта, который сработал - нашли нужный порт в таблице портов
                $log .= "Порт $value : ";
            }
                    
            if ($key == 'click')
            {
                if ($value == 1)
                {
                    $ports[] = [
                        'method' => $port->method,
                        'portObject' => $port->object,
                        'params' => null,
                        'method_params' => 'c',
                        'log' => "$log замыкание"
                    ];
                }
                if ($value == 2)
                {
                    $ports[] = [
                        'method' => $port->dc_method,
                        'portObject' => $port->object,
                        'params' => null,
                        'method_params' => 'dc',
                        'log' => "$log двойное замыкание"
                    ];
                }
            }

            if ($key == 'm')
            {
                if ($value == 1) 
                {
                    $ports[] = [
                        'method' => null,
                        'portObject' => $port->object,
                        'params' => $value,
                        'method_params' => null,
                        'log' => "$log размыкание"
                    ];
                }
                if ($value == 2) 
                {
                    $ports[] = [
                        'method' => $port->lc_method,
                        'portObject' => $port->object,
                        'params' => $m,
                        'method_params' => 'lc',
                        'log' => "$log длительное замыкание"
                    ];
                }  
            }
        }
    }
    elseif(count(preg_grep('/^ext[\d]*/', array_keys($parametersArray))) > 0)
    {
        foreach ($parametersArray as $key=>$value)
        {
            if ($key == 'pt')
            {
                $sql = System::$db->query(" SELECT `extension_module_types`.`name`,
                                                    `extension_modules`.`sda_port`,
                                                    `extension_modules`.`id`
                                            FROM `extension_module_types` 
                                            INNER JOIN `extension_modules` 
                                            ON `extension_module_types`.`id` = `extension_modules`.`extension_module_type_id`
                                            INNER JOIN `devices` ON `devices`.`id` = `extension_modules`.`device_id`
                                            WHERE `extension_modules`.`int_port` = $value
                                            AND `devices`.`ip_address` = '$ip_device'");
                $extDevice = $sql->fetch(PDO::FETCH_OBJ);
                        
                $log .= "Модуль расширения $extDevice->name ($extDevice->sda_port). ";
            }

            if (str_contains($key, 'ext'))
            {
                // Номер порта модуля расширения
                $extPort = (int)explode('ext', $key)[1];

                $sql = System::$db->query(" SELECT  `ports`.`id`,
                                                    `object`,
                                                    `status`,
                                                    `method`,
                                                    `dc_method`,
                                                    `lc_method`
                                            FROM `ports` 
                                            INNER JOIN `devices`
                                            ON `ports`.`id_device` = `devices`.`id` 
                                            WHERE `devices`.`ip_address` = '$ip_device'
                                            AND `ports`.`num_port` = $extPort
                                            AND `ports`.`extension_module_id` = $extDevice->id");
                $port = $sql->fetch(PDO::FETCH_OBJ);

                // Состояние порта (1 - замкнут, 0 - разомкнут)
                $extStatus = $value;
                if ($extStatus == 1) 
                {
                    $ports[] = [
                        'method' => $port->method,
                        'portObject' => $port->object,
                        'params' => null,
                        'method_params' => 'c',
                        'log' => "$log Порт $extPort : замыкание"
                    ];
                }
                else 
                {
                    $ports[] = [
                        'method' => null,
                        'portObject' => $port->object,
                        'params' => null,
                        'method_params' => null,
                        'log' => "$log Порт $extPort : размыкание"
                    ];
                }
            }
        }
    }
    else
    {
        foreach ($parametersArray as $key=>$value)
        {
            if ($key == 'pt')
            {
                $port = $mega->get($value); //взяли номер порта, который сработал - нашли нужный порт в таблице портов
                $ports[] = [
                    'method' => $port->method,
                    'portObject' => $port->object,
                    'params' => null,
                    'method_params' => 'c',
                    'log' => "$log Порт $value : замыкание"
                ];
            }
        }
    }   

    foreach ($ports as $port)
    {
        // System::addLog("Messages", "method = $port['method']", "port");
        if($port['method']) Action::runAction($port['method'], 'device', $port['portObject'], $port['params'], $port['method_params']);
        //Если метода нет, тогда выполняем действия для объекта, исходя из его типа и состояния
        else if ($port['params'] != 2) Action::runWithoutMethod($port['portObject']);
        System::addLog("Messages", $port['log'], "port");
    }
}
    
            
    

if (array_key_exists('st', $parametersArray))
{
    foreach ($parametersArray as $key=>$value)
    {
        if ($key == 'st')
        {
            System::addLog("Messages", "Контроллер $deviceName ($ip_device) был перезагружен.", "port");
            // TODO: Восстанавливать состояние портов из БД
        }
    }
}