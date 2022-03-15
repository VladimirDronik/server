<?php

/**
 * Created by PhpStorm.
 * User: kinord
 * Date: 09.05.20
 * Time: 19:36
 */
use Views;
use Messages;
use Cameras;
use Page;

class SendSocket
{

    private $data;
    private $users;
    private $views;
    private $message;
    private $currentUser;
    private $param1;
    private $param2;
    private $device;
    private $cameras;
    private $page;

    function __construct($data, $users, Views $views, Messages $message, Device $device, Cameras $cameras, Page $page)
    {
        $this->data = $data;
        $this->currentUser = $data[1];

        $this->param1 = $data[2];
        $this->param2 = $data[3];

        $this->users = $users;
        $this->views = $views;
        $this->message = $message;
        $this->device = $device;
        $this->cameras = $cameras;
        $this->page = $page;
    }

    /**
     * Функция отправки сообщения пользователю
     * @param $data - данные для отправки
     */
    private function send($data)
    {
        $webconnection = $this->users[$this->currentUser];
        $webconnection->send("$data");
    }

    /**
     * Отправка команды ping для проверка живой ли сервер. Ожидается pong
     */
    public function ping()
    {
        $this->send('{"status": "pong"}');
    }

    /**
     * Запрос кнопок, которые относятся к выбранной комнате
     */
    public function readyRoom()
    {
        $this->send($this->views->getRoomItems($this->param1));
    }

    /**
     * Отпарвка  данных со страницы термометров для температурых пресетов
     */
    public function readyTemperatures()
    {
        echo $this->views->getTemperatures($this->param1);
        $this->send($this->views->getTemperatures($this->param1));
    }

    /**
     * Отпарвка  данных о графиках температуру
     */
    public function getTempLog()
    {
        $this->send($this->views->getGraphs($this->param1, $this->param2));
    }

    /**
     * Отпарвка данных диммера
     */
    public function getDimmer()
    {
        $this->send($this->views->getDimmer($this->param1));
    }

    /**
     * Получение сообщений
     */
    public function getMessages()
    {
        echo "##########################################";
        echo $this->message->getMessages($this->param1);
        $this->send($this->message->getMessages($this->param1));
    }

    /**
     * Удаление всех сообщений
     */
    public function deleteMessages()
    {
        $this->send($this->message->deleteMessages());
    }

    public function getCountMessages()
    {
        $this->send($this->message->getCountMessages());
    }

    /**
     * Пометка сообщения прочитанным
     */
    public function messageRead()
    {
        $this->send($this->message->messageRead($this->param1));
    }

    /**
     * Отдает список превью камер
     */
    public function getAllCameras()
    {
        $this->send($this->cameras->getAllCameras());
    }

    /**
     * Отдает ссылку на изображение камеры
     *
     * $this->param1 - ИД камеры, для которой получаем ссылку
     */
    public function getLinkCamera()
    {
        $this->send($this->cameras->getCamera($this->param1));
    }

    /**
     * Отправка событий
     */
    public function readyEvents()
    {
        //Отвечаем на запрос получения всех данных страницы событий
        $this->send($this->views->getEvents('w'));
        $this->send($this->views->getEvents('m'));
        $this->send($this->views->getEvents('y'));
    }

    /**
     * Отправка счетчиков
     */
    public function getCounts() {
        $data = $this->views->getCounts();
        $this->send($data);
    }

    public function getCountsGraphs() {

        $data = $this->views->getCountsGraphs($this->param1, $this->param2);
        $this->send($data);
    }

    public function testMessage()
    {
        //TODO: Убрать это
        //Messages::send($this->param1, $this->param2);
        $data1 = $this->views->getScenesItems();
        $this->send($data1);
    }

    /**
     * Отправка элементов сцен
     */
    public function readyScenes()
    {
        $this->send($this->views->getScenesItems());
    }

    /**
     * Отправка меню
     */
    public function getMenu()
    {
        $this->send($this->views->getMenu());
    }


    /**
     * Отправка  всех данных для главной страницы
     */
    public function readyDashboard()
    {
        try {
            //Получаем данные из БД
            $data1 = $this->views->getGroupItems();
            $data2 = $this->views->getMainItems();

        } catch (\Throwable $e) {
            $errorFlag = true;
        }

        if (isset($this->currentUser)) {

            //Сравниваем ID юзера с имеющимися в БД и принимаем решение о допуске
            //Добавляем ID-push, если он не был добавлен до этого
            if (Users::checkuser($this->currentUser, $this->param1)) {

                // Получаем id клиента, который делает запрос и отправляем ему json с первоначальными настройками
                $this->send($data1);
                $this->send($data2);

                var_dump($data1);
                var_dump($data2);
            } else
                $this->send('{ "status": "error", "errorMessage": "access denied"}');



        } //else  $errorFlag = true;

        //Если где-то что-то пошло не так, то ставим флаг ошибки для скрипта watchdog
        if ($errorFlag == true) {
            $file = './watchdog.txt';
            file_put_contents($file, 'ERROR', FILE_APPEND | LOCK_EX);
        }

    }

    /**
     * Функция вызывается, если на мобильном устройтсве нажали. Выполняем действие и передаем имзменения
     * всем подключенным клиентам
     * @param $debugmode - флаг отладки вебсокетов
     */
    public function changeReseive($fulldata, $debugmode)
    {
        if ($debugmode) print_r("Client send: $fulldata\n");
        
        //Вызываем метод, отвечающий за внесение изменений в БД и активацию действий
        if ($debugmode) $this->views->resData($fulldata);
        else
            passthru("(php -f thread.php views resData '$fulldata' & ) >> /dev/null 2>&1");

    /*
        //отдаем данные об изменении всем другим зарегестрированным клиентам
        foreach ($this->users as $user) {

            $webconnection = $user;
            $webconnection->send($fulldata);
        }
    */
    }

    /**
     * Функция отдает сокету все устройства, которые необходимы для заполнения таблицы на коллекторе
     * (для Алисы)
     */
    public function getDevices()
    {
        //Отправляем данные коллектору
        $this->currentUser = 'collector';
        $this->send($this->device->getDevicesForCollector());
    }

    /**
     * Получение статуса конкретного устройства
     */
    public function getStatus($idDevice)
    {
        //Отправляем данные коллектору
        $this->currentUser = 'collector';
        $this->send($this->device->getStatus($idDevice));
    }

    /**
     * Получение запрашиваемой страницы
     */
    public function getPage()
    {
        $this->send($this->views->getPage($this->param1));
    }

    /**
     * Получение внутренней страницы для элемента
     */
    public function getInternalPage()
    {
        $this->send($this->views->getInternalPage($this->param1));
    }


    /**
     * Установка значения элемента для внутренней страницы
     */
    public function setInternalPage($fulldata)
    {
        $this->page->setIntPages($fulldata);
    }

    /**
     * Изменеие статуса какого-либо собъекта
     * @param $fulldata - данные, которые пришли из сокета
     */
    public function setStatus($fulldata)
    {

        $data_array = json_decode($fulldata);


        $idObject = $data_array->items[0]->id;
        $status = $data_array->items[0]->status;
        $instance = $data_array->items[0]->instance;

        if($status == 1)
            $status = 'on';
        elseif($status == 0)
            $status = 'off';


        $object = new Objects();
        $object->select($idObject);

        if (($object->type == 'lamp') || ($object->type == 'socket') || ($object->type == 'relay')) {

            $object->setStatus($status);
            //TODO: вместо этого сделать реализацию конкретного объекта и его метода, например лампы.

        } elseif ($object->type == 'dimmer') {

            $dimmer = new Dimmer($idObject);

            //Если передаем яркость
            if ($instance == 'brightness')
            $dimmer->setValue($status);
            elseif ($instance == 'on')
                $dimmer->setValue($status);


        } elseif ($object->type == 'curtain') {
            if ($status == 'on')
                Curtain::open($idObject);
            else
                Curtain::close($idObject);
        }

        elseif ($object->type == 'virtual') {

        $virtual = new Virtuals($idObject);

        if ($status == 'on')
            $virtual->on('view',$idObject);
        elseif ($status == 'off')
            $virtual->off('view',$idObject);


        }



    }


    public function changePageItem()
    {
        var_dump($this->param1);
    }



}