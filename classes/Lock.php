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
    private $type;

    public function __construct($idObject)
    {
        $this->initLock($idObject);
    }


    private function initLock($idObject)
    {
        $sql = parent::$db->query("SELECT `port_open`, `port_close`, `time`, `place`, `id_object`, `type` FROM locks WHERE
                                    `id_object` = $idObject");

        $lock =  $sql->fetch(PDO::FETCH_OBJ);

        $this->openPort = $lock->port_open;
        $this->closePort = $lock->port_close;
        $this->time = $lock->time;
        $this->place = $lock->place;
        $this->idObject = $lock->id_object;
        $this->type =  $lock->type;

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

        $disable = 0;
        $enable = 1;

        //В зависимости от типа замка назначаем порт и время
        switch ($lock->type) {
            case 'Electromechanical':
                if($command == 'open')
                    $port = $lock->openPort;
                else $port = $lock->closePort;
                $time = 1;
                break;

            case 'Magnetic':
                $port = $lock->openPort;
                if ($time == null)
                    $time = 0;

                if($command == 'open') {
                        $enable = 0;
                        $disable = 1;
                } else {
                        $enable = 1;
                        $disable = 0;
                }
                break;

            case 'Latch':
                $port = $lock->openPort;
                $time = 1;
                break;

        }


        if($lock->place == 'port') {

            $port = Device::getNumPort($port);
            $mega = new Megad();

            if(($lock->type == 'Magnetic') && ($time == null)) {
                $mega->set($port, $enable, $lock->device);
            } else {
                $mega->set($port, $enable, $lock->device);
                usleep($time*1000000);
                $mega->set($port, $disable, $lock->device);

            }


        } else {
            //Включаем устройство хитпро на определенное время
            $hitePro = HitePro::getDeviceParams($lock->device);

            HitePro::setHiteProCommand($hitePro->ip_address, $hitePro->password, $port, $enable);
            usleep($time*1000000);
            HitePro::setHiteProCommand($hitePro->ip_address, $hitePro->password, $port, $disable);
        }

        //Устанавливаем замку статус "открыто", связанной кнопке статус on
        $object = new Objects();
        $object->select($lock->idObject);
        $object->setStatus($status, true, false);

    }

}