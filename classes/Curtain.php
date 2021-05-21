<?php

/**
 * Класс работы со шторами
 */
class Curtain extends Device
{

    public $openPort;
    public $closePort;
    public $time;
    private $place;
    private $device;
    private $idObject;

    public function __construct($idObject)
    {
        $this->initCurtain($idObject);
    }


    private function initCurtain($idObject)
    {
        $sql = parent::$db->query("SELECT `port_open`, `port_close`, `time`, `place`, `id_object` FROM curtains WHERE
                                    `id_object` = $idObject");

        $curtain =  $sql->fetch(PDO::FETCH_OBJ);

        $this->openPort = $curtain->port_open;
        $this->closePort = $curtain->port_close;
        $this->time = $curtain->time;
        $this->place = $curtain->place;
        $this->idObject = $curtain->id_object;

        if($curtain->place == 'port')
        $this->device = Device::getDevice($idObject);

    }

    /**
     * Открытие шторы
     */
    public static function open($idObject)
    {
        $curtain = new Curtain($idObject);
        self::setValue($curtain, 'open', 'open', $curtain->time);
    }

    /**
     * Открытие шторы
     */
    public static function close($idObject)
    {
        $curtain = new Curtain($idObject);
        self::setValue($curtain, 'close', 'close', $curtain->time);
    }

    public static function openPercent($idObject, $percent)
    {
        $object = new Objects();
        $object->select($idObject);

        $curtain = new Curtain($idObject);

        //Если штора закрыта полностью
        if($object->status == 'close') {
            $time = $curtain->time*$percent/100;
            $command = 'open';
        }
        elseif ($object->status == 'open') { //Если штора открыта полностью
            $time = $curtain->time*(100 - $percent)/100;
            $command = 'close';
        }

        else { //Если штора была отркыта на процент
            $time = $curtain->time*($percent-$object->status)/100;
            if($object->status < $percent) {
                $command = 'open';
            }else {
                $command = 'close';
            }

        }

        //Если полностью открыта или полностью закрыта
        if($percent == 100)
            $status = 'open';
        elseif($percent == 0)
            $status = 'close';
        else
            $status = $percent;


        self::setValue($curtain, $command, $status, abs($time));
    }


    private static function setValue($curtain, $command, $status, $time)
    {

        if($command == 'open')
            $port = $curtain->openPort;
        else $port = $curtain->closePort;

        if($curtain->place == 'port') {

            $port = Device::getNumPort($port);

            //Включаем порт на определенное время
            $mega = new Megad();
            $mega->set($port,1,$curtain->device);
            usleep(500000);
            $mega->set($port,0,$curtain->device);
            usleep($time*1000000);
            $mega->set($port,1,$curtain->device);
            usleep(500000);
            $mega->set($port,0,$curtain->device);

        } else {
            //Включаем устройство хитпро на определенное время
            $hitePro = HitePro::getDeviceParams($curtain->device);
            HitePro::setHiteProCommand($hitePro->ip_address, $hitePro->password, $port, 1);
            usleep($time*1000000);
            HitePro::setHiteProCommand($hitePro->ip_address, $hitePro->password, $port, 0);
        }

        //Устанавливаем шторе статус "открыто", связанной кнопке статус on
        $object = new Objects();
        $object->select($curtain->idObject);
        $object->setStatus($status, true, false);

    }

}