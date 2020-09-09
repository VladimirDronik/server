<?php
/** Тестовый  скрипт для проверки работоспособности отправки данных клиенту **/



use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/include.php';



class webSocket
{

    private $command;
    public $address;
    private $user_id;
    private $id_device;
    private $items;


    private function runSocket()
    {

        $worker = new Worker();

        $worker->onWorkerStart = function () {

            // Websocket protocol for client.
            $ws_connection = new AsyncTcpConnection('ws://'.$this->address.'/?user=collector');


            $ws_connection->onConnect = function ($connection) {
                $user = 'collector';
                $connection->send(json_encode(['status' => $this->command, 'items' => $this->items]));
            };


            $ws_connection->onMessage = function ($connection, $data) {

                $dataRes = json_decode($data);

                //Добавляем данные в таблицу устройств
                if($dataRes->mode = 'get_devices')
                $this->addingDeviceToDB($dataRes->devices);

                if($dataRes->mode = 'get_status')
                $this->resStatus($this->user_id, $this->id_device, $dataRes->status);

                $connection->close();

                exit;
            };

            $ws_connection->onError = function ($connection, $code, $msg) {
                echo "Error: $msg\n";
            };
            $ws_connection->onClose = function ($connection) {
                echo "Connection closed\n";
            };

            $ws_connection->connect();
        };



      $worker->run();


    }


    /**
     * Добавление устройств объекта в БД на коллекторе
     * @param $devices
     */
    private function addingDeviceToDB($devices)
    {

        foreach ($devices AS $key => $device) {

            $ukey = $this->user_id.$key;

            system::$db->query("REPLACE INTO devices (`user_id`, `ukey`, `name`, `type`, `room`, `model`, `manufacturer`, `capabilities`, `did`)
                                VALUES ($this->user_id, $ukey, '$device->name', '$device->type', '$device->room', '$device->model',
                                '$device->manufacturer', '$device->capabilities', $key)");

        }

    }

    /**
     * Получение всех устройст пользователя
     */
    public function getDevices($user_id)
    {
        $this->command = 'get_devices';
        $this->user_id = $user_id;
        $this->runSocket();
    }

    /**
     * Получение статуса устройства
     * @param $user_id - id пользователя, у которого делаем запрос
     * @param $id_device - id устройства, статус которого запрашиваем
     */
    public function getStatus($user_id, $id_device)
    {
       $this->id_device = $id_device;
       $this->command = 'get_status;'.$id_device;
       $this->user_id = $user_id;
       $this->runSocket();
    }

    /**
     * Установка статуса выбранному устройству
     * @param $user_id - id пользователя (удаленного сервера)
     * @param $id_device - id устройства, у которого меняем статус
     * @param $status - стутус, который устанавливаем устройству
     */
    public function setStatus($id_device, $status)
    {
        $this->command = 'itemChange';
        $obj = array('id' => (int)$id_device, 'status' => $status);
        $this->items[] = $obj;
       // $this->items = "[{\"id\":$id_device, \"status\":\"$status\"}]";
        $this->runSocket();
    }

    /**
     * Эта функуция выполняется при получения статуса с удаленного устройства
     * @param $user_id
     * @param $id_device
     * @param $status
     */
    private function resStatus($user_id, $id_device, $status)
    {
       //Сюда вставить действия, которые необходимо выполнить при получении состояния устройства
    }




}