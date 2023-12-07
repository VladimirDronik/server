<?php
/** Скрипт watchdog запускается по крону каждую минуту
 * и шлет серверу эхо-запрос, если данные от сервера не вернулись, то данный скрипт останавливает
 * сервекр и запускает его снова  **/

require_once __DIR__ . '/include.php';

$user = 'watchdog';
$message = 'watchdog';

//Флаг рестарта
$restart = false;


/*
//Пингуем туннель, если не работает, то пробуем его запустить
exec("ping -c 5 $VPN_server",$output, $status);
if ($status!=0)
    //перезапускаем сервер сокетов
    exec("/etc/init.d/xl2tpd restart");
*/



// connect to a local tcp-server
$instance = stream_socket_client($localsocket, $errno, $errstr, 30);

//Если сервер не откликается, то перезапускаем его
if (!$instance) {
   $restart = true;
}

//else {

    //Читаем файл первый раз на предмет ошибок
    $handle = @fopen("watchdog.txt", "r");
    if ($handle) {
        while (($buffer = fgets($handle, 4096)) !== false) {
            if ($buffer != 'OK') $restart = true;
        }
        if (!feof($handle)) {
            $restart = true;
        }
        fclose($handle);
    }


   if ($restart == false) {


       //Шлем тестовое сообщеение через сокет
       fwrite($instance, json_encode(['user' => $user, 'message' => $message]) . "\n");

       sleep(1);

       //Читаем строку из файла
       $handle = @fopen("watchdog.txt", "r");
       if ($handle) {
           while (($buffer = fgets($handle, 4096)) !== false) {
               if ($buffer != 'OK') $restart = true;
           }
           if (!feof($handle)) {
               $restart = true;
           }
           fclose($handle);
       }
   }

   if (file_exists('watchdog.txt'))
    unlink('watchdog.txt');
//}


//Если есть флаг рестарта сервера сокетов
if ($restart)
{
    System::addLog('error', 'Server is restarted from the watchdog', 'socket_server');

    // Если остановка сервера вызывает ошибку
    // exec("php server.php stop", $output);
    // if($output[2] == 'Workerman[server.php] stop fail') exec('/sbin/reboot'); //Перезапускаем весь сервер

    if ($options = getenv('SERVER_OPTIONS')) $options .= " ";
    passthru("(php -f server.php restart " . isset($options) . "& ) >> /dev/null 2>&1");
}

return true;


