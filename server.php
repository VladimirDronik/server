<?php
/** Сервер запущен как демон. Осуществляет обмен данными с клиентом. В этом скрипте прописаны основные методы
 * работ с сокетами. Так же в этом скрипте реализована функция пориема сообщений от клиента через сокет и выполнение
 действий в зависимости от того, какие данные шлет клиент
 *
 *  * #Запуск демона сервера сокетов на cron
 * @reboot cd /var/www/socket_test && php server.php start -d
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/include.php';
use Workerman\Worker;



/**
 * Включение debug mode. Помимо запрета вывода системной информации в этом режиме
 * предусмотрен запуск выполнения некоторых функций параллельно работе данного скрипта
 * если debugmode выключен и выполнение этих функций в теле данного скрипта, если debugmode
 * включен.
 * При этом в режиме отладки эти функции будут осуществлять вывод своих данных непосредственно на экран.
 */

if ($argv[2] == 'debug')
    $debugmode = true;
else
    $system_message = false;



if ($debugmode) print_r("\n\n\n
==========================================================================
=================== SERVER IS RUNNING ON DEBUG MODE ======================
==========================================================================\n\n\n");


// массив для связи соединения пользователя и необходимого нам параметра
$users = [];

// создаём ws-сервер, к которому будут подключаться все наши пользователи
$ws_worker = new Worker("websocket://0.0.0.0:8000");

//по умолчанию создается только один воркер
$ws_worker->count = 1;
$ws_worker->reloadable = true;

// создаём обработчик, который будет выполняться при запуске ws-сервера
$ws_worker->onWorkerStart = function() use (&$users)
{


    // создаём локальный tcp-сервер, чтобы отправлять на него сообщения из кода нашего сайта
    $inner_tcp_worker = new Worker("tcp://127.0.0.1:5678");

    //совмесное использование порта группой воркеров
    $inner_tcp_worker->reusePort = true;

    // создаём обработчик сообщений, который будет срабатывать,
    // когда на локальный tcp-сокет приходит сообщение
    $inner_tcp_worker->onMessage = function($connection, $data) use (&$users) {

        global $debugmode;
        $data = json_decode($data);


        //Если отправили сообщение от скрипта watchdog
        if( $data->message == 'watchdog'){

            global $system_message;

            $state = 'OK';

            try {

                System::checkConnection();

            }  catch (\Throwable $e) {

                $state = 'ERROR';
            }


            $file = 'watchdog.txt';

            //Записываем в файл что всё ок и сервер работает
            file_put_contents($file, $state, FILE_APPEND | LOCK_EX);

            file_put_contents('server.log',date("Y-m-d H:i:s")." -> \n".$data->message." is ".$state."\n\n", FILE_APPEND | LOCK_EX); 
            
            if ($system_message)
               print_r("Watchdog is $state\n");
	
        }



        //Здесь рассылаем приходящие от сторонних скриптов сообщения всем ползователям или конкретному
        if ($data->user=='all') {

           foreach ($users as $user) {
            $webconnection = $user;
            $webconnection->send($data->message);
            }

           } else {
                    if (isset($users[$data->user])) {
                    $webconnection = $users[$data->user];
                    $webconnection->send($data->message);
                   }

file_put_contents('server.log',date("Y-m-d H:i:s")." -> client send :\n".$data->message."\n\n", FILE_APPEND | LOCK_EX); 
            }


  
      if ($debugmode)
            print_r('Received message: '.$data->message." \n");
	     	
    };
    $inner_tcp_worker->listen();
};




//функция обработки нового подключения пользователя
$ws_worker->onConnect = function($connection) use (&$users)
{


    $connection->onWebSocketConnect = function($connection) use (&$users)
    {

        global $system_message;

        // при подключении нового пользователя сохраняем get-параметр, который же сами и передали со страницы сайта
         $users[$_GET['user']] = $connection;



        //System::addlog('User '.$_GET['user'].' is connected');

        if ($system_message)
        print_r('User '.$_GET['user']." is connected \n");

    };


};



/** Получение данных от клиента */
$ws_worker->onMessage = function($connection, $data) use (&$users)
{
    global $debugmode;


   if ($debugmode) print_r("=====================================\n");


echo "\n".date("Y-m-d H:i:s")." -> client send:\n";
var_dump($data);
echo "\n";


if ($data!='watchdog')
file_put_contents('server.log',date("Y-m-d H:i:s")." -> client send:\n".$data."\n\n", FILE_APPEND | LOCK_EX);


        $views = new Views();
        $user = new Users();
        $messages = new Messages();
        $device = new Device();
        $cameras = new Cameras();
        $page = new Page();


        $objjson = json_decode($data);
        $data_array = explode(';',$objjson->{'status'});

        $send = new SendSocket($data_array, $users, $views, $messages, $device, $cameras, $page);

        $status = $data_array[0];


            if (($status == 'adduser') || ($status == 'edituser') || ($status == 'deleteuser') || ($status == 'checkuser')) {

                $method = $objjson->{'status'};
                //Отвечаем на запрос разрешения вывода данных или запрещение
                if ($data_array[0] == 'checkuser') {

                    $data1 = $user->checkuser($data_array[1]);
                    $webconnection = $users[$data_array[1]];
                    $webconnection->send("$data1");


                } else {

                    //Вызываем метод в зависимости от статуса
                    $user->$method($objjson->items[0]->id, $objjson->items[0]->dashboard, $objjson->items[0]->old_id);

                    //Формируем ответ со всеми юзерами, котоыре доступны
                    $data1 = $user->get_all_users();

                    $webconnection = $users[$objjson->iduser];
                    $webconnection->send("$data1");

                }
            }



            switch ($status) {

                case 'set_status':
                    $send->setStatus($data);
                    break;

                case 'get_status':
                    $send->getStatus($data_array[1]);
                    break;

                case 'get_devices':
                    $send->getDevices();
                    break;

                case 'ping':
                    $send->ping();
                    break;

                case 'ready?dashboard':
                    $send->readyDashboard();
                    break;

                case 'ready?scenes':
                    $send->readyScenes();
                    break;

                case 'ready?room':
                    $send->readyRoom();
                    break;

                case 'ready?temperatures':
                    $send->readyTemperatures();
                    break;

                case 'getTempLog':
                    $send->getTempLog();
                    break;

                case 'getMessages':
                    $send->getMessages();
                    break;

                case 'getCountMessages':
                    $send->getCountMessages();
                    break;

                case 'deleteMessages':
                    $send->deleteMessages();
                    break;

                case 'messageRead':
                    $send->messageRead();
                    break;

                case 'getDimer':
                    $send->getDimmer();
                    break;

                case 'ready?events':
                    $send->readyEvents();
                    break;

                case 'getCounts':
                    $send->getCounts();
                    break;

                case 'getCountsGraphs':
                    $send->getCountsGraphs();
                    break;

                case 'testMessage': //TODO: Убрать это
                    $send->testMessage();
                    break;

                case 'getAllCameras':
                    $send->getAllCameras();
                    break;

                case 'getLinkCamera':
                    $send->getLinkCamera();
                    break;

                case 'singleMessage':
                    break;

                case 'getMenu':
                    $send->getMenu();
                    break;

                case 'getPage':
                    $send->getPage();
                    break;

                case 'getInternalPage':
                    $send->getInternalPage();
                    break;

                case 'setInternalPage':
                    $send->setInternalPage($data);
                    break;

                case 'pagesItemChange':
                    $send->changePageItem();
                    break;

                case 'setPageElements':
                    $send->setPageElement($data);
                    break;

                case 'getConditioner':
                    $send->getConditioner();
                    break;

                default: //itemChange, settingChange, eventChange, temperaturesChange
                    $send->changeReseive($data, $debugmode);
                    break;

            }



};


$ws_worker->onClose = function($connection) use(&$users)
{
    // удаляем параметр при отключении пользователя
    $user = array_search($connection, $users);
    unset($users[$user]);
};


// Run worker
Worker::runAll();

