<?php

/**
 * Created by PhpStorm.
 * User: kinord
 * Date: 09.05.20
 * Time: 19:36
 */
use Views;

class SendSocket
{

    private $data;
    private $users;
    private $views;
    private $currentUser;
    private $param1;
    private $param2;

    function __construct($data, $users, Views $views)
    {
        $this->data = $data;
        $this->currentUser = $data[1];

        $this->param1 = $data[2];
        $this->param2 = $data[3];

        $this->users = $users;
        $this->views = $views;
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
     * Получение данных со страницы термометров
     */
    public function readyTemperatures()
    {
        $this->send($this->views->getTemperatures($this->param1));
    }

    /**
     * Получение данных о графиках температуру
     */
    public function getTempLog()
    {
        $this->send($this->views->getGraphs($this->param1, $this->param2));
    }

    /**
     * Получение данных диммера
     */
    public function getDimmer()
    {
        $this->send($this->views->getDimmer($this->param1));
    }

    /**
     * Получение событий
     */
    public function readyEvents()
    {
        //Отвечаем на запрос получения всех данных страницы событий
        $this->send($this->views->getEvents('w'));
        $this->send($this->views->getEvents('m'));
        $this->send($this->views->getEvents('y'));
    }

    /**
     * Получение всех данных для главной страницы
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

            // Получаем id клиента, который делает запрос и отправляем ему json с первоначальными настройками
            $this->send($data1);
            $this->send($data2);

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