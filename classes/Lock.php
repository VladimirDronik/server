<?php

/**
 * Класс работы со шторами
 */
class Lock extends Device
{

    public $openPort;
    public $closePort;
    public $time;
    private $place;
    private $device;
    private $idObject;

    public function __construct($idObject)
    {
        $this->initLock($idObject);
    }


    private function initLock($idObject)
    {
        $sql = parent::$db->query("SELECT `port_open`, `port_close`, `time`, `place`, `id_object` FROM Locks WHERE
                                    `id_object` = $idObject");

        $lock =  $sql->fetch(PDO::FETCH_OBJ);

        $this->openPort = $lock->port_open;
        $this->closePort = $lock->port_close;
        $this->time = $lock->time;
        $this->place = $lock->place;
        $this->idObject = $lock->id_object;

        if($lock->place == 'port')
        $this->device = Device::getDevice($idObject);

    }

    /**
     * Открытие замка
     */
    public static function open($idObject)
    {
        $lock = new Lock($idObject);
        self::setValue($lock, 'open', 'open', $lock->time);
    }

    /**
     * Закрытие замка
     */
    public static function close($idObject)
    {
        $lock = new Lock($idObject);
        self::setValue($lock, 'close', 'close', $lock->time);
    }


    private static function setValue($lock, $command, $status, $time)
    {

        if($command == 'open')
            $port = $lock->openPort;
        else $port = $lock->closePort;

        if($lock->place == 'port') {

            $port = Device::getNumPort($port);

            //Включаем порт на определенное время
            $mega = new Megad();
            $mega->set($port,1,$lock->device);
            usleep($time*1000000);
            $mega->set($port,0,$lock->device);

        } else {
            //Включаем устройство хитпро на определенное время
            $hitePro = HitePro::getDeviceParams($lock->device);
            HitePro::setHiteProCommand($hitePro->ip_address, $hitePro->password, $port, 1);
            usleep($time*1000000);
            HitePro::setHiteProCommand($hitePro->ip_address, $hitePro->password, $port, 0);
        }

        //Устанавливаем замку статус "открыто", связанной кнопке статус on
        $object = new Objects();
        $object->select($lock->idObject);
        $object->setStatus($status, true, false);

    }

}