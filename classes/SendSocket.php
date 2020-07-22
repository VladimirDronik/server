<?php

/**
 * Created by PhpStorm.
 * User: kinord
 * Date: 09.05.20
 * Time: 19:36
 */
use Views;
use Messages;

class SendSocket
{

    private $data;
    private $users;
    private $views;
    private $message;
    private $currentUser;
    private $param1;
    private $param2;

    function __construct($data, $users, Views $views, Messages $message)
    {
        $this->data = $data;
        $this->currentUser = $data[1];

        $this->param1 = $data[2];
        $this->param2 = $data[3];

        $this->users = $users;
        $this->views = $views;
        $this->message = $message;
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
     * Отпарвка  данных со страницы термометров
     */
    public function readyTemperatures()
    {
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
     * Отправка событий
     */
    public function readyEvents()
    {
        //Отвечаем на запрос получения всех данных страницы событий
        $this->send($this->views->getEvents('w'));
        $this->send($this->views->getEvents('m'));
        $this->send($this->views->getEvents('y'));
    }

    public function testMessage()
    {
        //TODO: Убрать это
        //Messages::send($this->param1, $this->param2);
        $data1 = $this->views->getScenesItems();
        echo $data1;
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
        
        //Вызываем метод, отвечающий за внесение изменений в БД и активацию действий
        if ($debugmode) $this->views->resData($fulldata);
        else
            passthru("(php -f thread.php views resData '$fulldata' & ) >> /dev/null 2>&1");

        //отдаем данные об изменении всем другим зарегестрированным клиентам
        foreach ($this->users as $user) {

            $webconnection = $user;
            $webconnection->send($fulldata);
        }
    }


}