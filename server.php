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
//var_dump($users);
    // создаём локальный tcp-сервер, чтобы отправлять на него сообщения из кода нашего сайта
    $inner_tcp_worker = new Worker("tcp://127.0.0.1:5678");

    //совмесное использование порта группой воркеров
    $inner_tcp_worker->reusePort = true;
    // создаём обработчик сообщений, который будет срабатывать,
    // когда на локальный tcp-сокет приходит сообщение
    $inner_tcp_worker->onMessage = function($connection, $data) use (&$users) {
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
            }

          
            
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

        $views = new Views();
        $user = new Users();

        $objjson = json_decode($data);
        $data_array = explode(';',$objjson->{'status'});


        if($objjson->{'status'} == 'ping') {

            $message = '{"status": "pong"}';

            $webconnection = $users[$data_array[1]];
            $webconnection->send($message);
        } else {

            //Если клиент изменил данные и уведомил об этом сервер (например нажали кнопку)
            if ((($objjson->{'status'}) == 'itemChange') || (($objjson->{'status'}) == 'settingChange') || (($objjson->{'status'}) == 'eventChange') || (($objjson->{'status'}) == 'temperaturesChange')) {


                //Вызываем метод, отвечающий за внесение изменений в БД и активацию действий
                if ($debugmode) $views->resData($data);
                else
                    passthru("(php -f thread.php views resData '$data' & ) >> /dev/null 2>&1");


                //отдаем данные об изменении всем другим зарегестрированным клиентам
                foreach ($users as $user) {

                    $webconnection = $user;
                    $webconnection->send($data);
                }

                //Если клиент отправил данные на получение или изменение юзера
            } elseif ((($objjson->{'status'}) == 'adduser') || (($objjson->{'status'}) == 'edituser') || (($objjson->{'status'}) == 'deleteuser') || ($data_array[0] == 'checkuser')) {

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
            } else { //Выполняется, если клиент шлет какой-то другой запрос, например на получение всех данных при загрузке страницы
                /*
                            if ($data_array[0] == 'ready?menu') {

                                //отправляем пользователю меню
                                $data1 = $views->getMenu();
                                $webconnection = $users[$data_array[1]];
                                $webconnection->send($data1);
                            }
                */

                //формируем и отвечаем на запрос на получение всех данных с главной страницы
                if ($data_array[0] == 'ready?dashboard') {

                    try {

                        //Получаем данные из БД
                        $data1 = $views->getGroupItems();
                        $data2 = $views->getMainItems();

                    } catch (\Throwable $e) {
                        $errorFlag = true;
                    }

                    if (isset($users[$data_array[1]])) {

                        // Получаем id клиента, который делает запрос и отправляем ему json с первоначальными настройками
                        $webconnection = $users[$data_array[1]];
                        $webconnection->send("$data1");
                        $webconnection->send("$data2");

                    } //else  $errorFlag = true;

                    //Если где-то что-то пошло не так, то ставим флаг ошибки для скрипта watchdog
                    if ($errorFlag == true) {
                        $file = 'watchdog.txt';
                        file_put_contents($file, 'ERROR', FILE_APPEND | LOCK_EX);
                    }


                }


                //Получаем комнаты и элементы внутри них
                if ($data_array[0] == 'ready?room') {
                    //Получаем данные из БД
                    $data1 = $views->getRoomItems($data_array[2]);

                    $webconnection = $users[$data_array[1]];
                    $webconnection->send("$data1");
                }
                /*
                            //отвечаем температурами термостатов по запросу клиента
                            if ($data_array[0] == 'ready?dashboard_termostat') {

                                $data1 = $views->getRoomItems('temp');

                                $webconnection = $users[$data_array[1]];
                                $webconnection->send($data1);
                            }
                */


                //Формируем и отвечаем на запрос на получение данных на странице термометров
                if ($data_array[0] == 'ready?temperatures') {

                    $data1 = $views->getTemperatures($data_array[2]);

                    // Получаем id клиента, который делает запрос и отправляем ему json
                    $webconnection = $users[$data_array[1]];
                    $webconnection->send("$data1");
                }


                //Формируем и отвечаем на запрос на получение данных на странице термометров для построения графиков
                if ($data_array[0] == 'getTempLog') {

                    $data1 = $views->getGraphs($data_array[2], $data_array[3]);

                    // Получаем id клиента, который делает запрос и отправляем ему json
                    $webconnection = $users[$data_array[1]];
                    $webconnection->send("$data1");

                }

                //Отвечаем на запрос: предоставление информации о конкретном димере
                if ($data_array[0] == 'getDimer') {

                    $data1 = $views->getDimmer($data_array[2]);

                    // Получаем id клиента, который делает запрос и отправляем ему json
                    $webconnection = $users[$data_array[1]];
                    $webconnection->send("$data1");

                }


                /*
                            //формируем и отвечаем на запрос на получение всех данных для любой сцены
                            if ($data_array[0] == 'ready?scene') {

                                //Получаем данные из БД
                                $data1 = $views->getScenesItems();

                                // Получаем id клиента, который делает запрос и отправляем ему json с первоначальными настройками //
                                $webconnection = $users[$data_array[1]];

                                $webconnection->send("$data1");

                            }
                */
                /*
                            //формируем и отвечаем на запрос на получение всех данных страницы настроек
                            if ($data_array[0] == 'ready?settings') {

                                //Отдаем все настройки
                                $data1 = $views->getAllSettings();

                                //Формируем ответ со всеми юзерами, котоыре доступны
                                $data2 = $user->get_all_users();

                                // Получаем id клиента, который делает запрос и отправляем ему json с первоначальными настройками //
                                $webconnection = $users[$data_array[1]];
                                $webconnection->send("$data1");
                                $webconnection->send("$data2");


                            }
                */
                /*
                            //Формируем и отвечаем на запрос на получение всех данных для страницы события
                           if ($data_array[0] == 'ready?events') {

                                //Отвечаем на запрос получения всех данных страницы событий
                                $data1 = $views->getEvents('w');
                                $data2 = $views->getEvents('m');
                                $data3 = $views->getEvents('y');

                                $webconnection = $users[$data_array[1]];
                                $webconnection->send("$data1");
                                $webconnection->send("$data2");
                                $webconnection->send("$data3");

                                }
                */
            };
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

