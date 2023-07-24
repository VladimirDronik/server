<?php

/**
 * Класс работы со шторами.
 * 
 * Для приводов с фазным управлением и сухим контактом доступны методы: open и close.
 * Повторный вызов метода останавливает привод.
 * 
 * Для приводов с RS485 доступны методы: open, close, stop, openPercent.
 * Для унификации методов повторный вызов метода open или close останавливает привод.
 */

 class Curtain extends Device
{

    // public $openPort;
    // public $closePort;
    // public $time;
    // private $place;
    // private $device;
    // private $idObject;

    public function __construct($idObject)
    {
        $this->initCurtain($idObject);
    }


    private function initCurtain($idObject)
    {
        $sql = parent::$db->query("SELECT curtains.port_open AS `port_open`,
                                    curtains.port_close AS `port_close`,
                                    curtains.time AS `time`,
                                    curtains.place AS `place`,
                                    curtains.address AS `address`,
                                    curtains.group AS `group`,
                                    curtains.percent AS `percent`,
                                    curtains.id_object AS `id_object`,
                                    devices.ip_address AS `gateway_ip`,
                                    devices.port AS `gateway_port`
                                    FROM curtains LEFT JOIN devices ON devices.id=curtains.device_id
                                    WHERE curtains.id_object = $idObject");

        $curtain =  $sql->fetch(PDO::FETCH_OBJ);

        $this->idObject = $curtain->id_object;
        $this->place = $curtain->place;
        
        if($curtain->place == 'rs485')
        {
            $this->address = $curtain->address;
            $this->group = $curtain->group;
            $this->gateway_ip = $curtain->gateway_ip;
            $this->gateway_port = $curtain->gateway_port;
            $this->percent = $curtain->percent;
        }
        else
        {
            $this->openPort = $curtain->port_open;
            $this->closePort = $curtain->port_close;
            $this->time = $curtain->time;
            if($curtain->place == 'port' || $curtain->place == 'phase') 
            $this->device = Device::getDevice($idObject);
        }
    }

    /**
     * Сборка пакета для отправки (для штор с RS485)
     */
    private function packetAssembling ($transactionId, $address, $group, $cmd, $percent=null, $direction=null)
    {
        $modbusMsg = '55';
        $modbusMsg .= str_pad(dechex($address), 2, "0", STR_PAD_LEFT);
        $modbusMsg .= str_pad(dechex($group), 2, "0", STR_PAD_LEFT);
        $modbusMsg .= $cmd;
        if (isset($percent)) 
        {
            $modbusMsg .= str_pad(dechex($percent), 2, "0", STR_PAD_LEFT);
        }
        if (isset($direction)) 
        {
            $modbusMsg .= str_pad(dechex($direction), 2, "0", STR_PAD_LEFT);
        }
        $packet = '';
        $packet .= $transactionId; // Transaction ID
        $packet .= '0000'; // Protocol Identifier, 0000 = Master
        $packet .= str_pad((dechex((string)(strlen($modbusMsg)/2))), 4, "0", STR_PAD_LEFT); // Cmd length
        $packet .= $modbusMsg;
        $packet .= modbusTcpCrc($modbusMsg); // CRC-16 Modbus
        //var_dump ($packet);
        return $packet;
    }
    
    /**
     * Открытие шторы
     */
    public static function open($idObject)
    {
        $curtain = new Curtain($idObject);
        if($curtain->place == 'rs485')
        {
            if (self::getInfo($idObject) != 0) self::stop($idObject);
            else
            {
                $packet = hex2bin(self::packetAssembling(modbusTcpTransactionId(), 
                                    $curtain->address, $curtain->group, '0301')); // 0301 - команда открытия
                modbusTcpSendCmd($packet, $curtain->gateway_ip, $curtain->gateway_port);
                self::percent_db($idObject, 100);
            }
        }
        elseif ($curtain->place == 'port')
        {
            $mega = new Megad();
            $port = Device::getNumPort($curtain->openPort);
            $mega->set($port,1,$curtain->device);
            usleep(500000);
            $mega->set($port,0,$curtain->device);
        }
        elseif ($curtain->place == 'phase')
        {
            $mega = new Megad();
            if ($mega->status(Device::getNumPort($curtain->closePort), 'get', $curtain->device) != 'OFF')
            {
                $mega->set(Device::getNumPort($curtain->closePort),0,$curtain->device);
                usleep(500000);
            }

            if ($mega->status(Device::getNumPort($curtain->openPort), 'get', $curtain->device) != 'OFF')
                $mega->set(Device::getNumPort($curtain->openPort),0,$curtain->device);
            else
            {
                $mega->set(Device::getNumPort($curtain->openPort),1,$curtain->device);
                sleep($curtain->time+1);
                $mega->set(Device::getNumPort($curtain->openPort),0,$curtain->device);
            }
        }
    }

    /**
     * Закрытие шторы
     */
    public static function close($idObject)
    {
        $curtain = new Curtain($idObject);
        if($curtain->place == 'rs485')
        {
            if (self::getInfo($idObject) != 0) self::stop($idObject);
            else
            {
                $packet = hex2bin(self::packetAssembling(modbusTcpTransactionId(), 
                                    $curtain->address, $curtain->group, '0302')); // 0302 - команда закрытия
                modbusTcpSendCmd($packet, $curtain->gateway_ip, $curtain->gateway_port);
                self::percent_db($idObject, 0);
            }
        }
        elseif ($curtain->place == 'port')
        {
            $mega = new Megad();
            $port = Device::getNumPort($curtain->closePort);
            $mega->set($port,1,$curtain->device);
            usleep(500000);
            $mega->set($port,0,$curtain->device);
        }
        elseif ($curtain->place == 'phase')
        {
            $mega = new Megad();
            if ($mega->status(Device::getNumPort($curtain->openPort), 'get', $curtain->device) != 'OFF')
            {
                $mega->set(Device::getNumPort($curtain->openPort),0,$curtain->device);
                usleep(500000);
            }

            if ($mega->status(Device::getNumPort($curtain->closePort), 'get', $curtain->device) != 'OFF')
                $mega->set(Device::getNumPort($curtain->closePort),0,$curtain->device);
            else
            {
                $mega->set(Device::getNumPort($curtain->closePort),1,$curtain->device);
                sleep($curtain->time+1);
                $mega->set(Device::getNumPort($curtain->closePort),0,$curtain->device);
            }
        }
    }

    /**
     * Остановка привода (для приводов с RS-485)
     */
    public static function stop($idObject)
    {
        $curtain = new Curtain($idObject);
        if($curtain->place == 'rs485')
        {
            $packet = hex2bin(self::packetAssembling(modbusTcpTransactionId(), 
                                $curtain->address, $curtain->group, '0303')); // 0303 - команда остановки
            modbusTcpSendCmd($packet, $curtain->gateway_ip, $curtain->gateway_port);
            self::getPercent($idObject);
        }
    }

    /**
     * Открытие шторы на процент (для приводов с RS-485)
     */
    public static function openPercent($idObject, $percent)
    {
        if ($percent >= 0 && $percent <= 100)
        {
            $curtain = new Curtain($idObject);
            if($curtain->place == 'rs485')
            {
                $packet = hex2bin(self::packetAssembling(modbusTcpTransactionId(), 
                                    $curtain->address, $curtain->group, '0304', $percent)); 
                                    // 0304 - команда установки процента открытия
                $response = hexdec(substr((bin2hex(modbusTcpSendCmd($packet, $curtain->gateway_ip, $curtain->gateway_port))), -2));
                // var_dump ($response);
        
                if ($response > 100)
                {
                    if (self::getMotorType($idObject) == 17)
                    {
                        echo "[WARNING] Set the limits first!\n";
                        self::percent_db($idObject, $response);
                    }
                    else
                    {
                        self::open($idObject);
                        while (self::getInfo($idObject) != 0) usleep(500000);
                        self::close($idObject);
                        while (self::getInfo($idObject) != 0) usleep(500000);
                        $response = hexdec(substr((bin2hex(modbusTcpSendCmd($packet, $curtain->gateway_ip, $curtain->gateway_port))), -2));
                        // var_dump ($response);
                    }
                }

                self::percent_db($idObject, $response);
            }
            // elseif ($curtain->place == 'port')
            // {
            //     // $mega = new Megad();
            //     $currentPercent = $curtain->percent;
            //     if ($percent == 0) 
            //     {
            //         // $mega = new Megad();
            //         // $port = Device::getNumPort($curtain->closePort);
            //         // $mega->set($port,1,$curtain->device);
            //         // usleep(500000);
            //         // $mega->set($port,0,$curtain->device);
            //         // self::percent_db($idObject, '0');
            //         self::close($idObject);
            //     }
            //     elseif ($percent == 100)
            //     {
            //         // $mega = new Megad();
            //         // $port = Device::getNumPort($curtain->openPort);
            //         // $mega->set($port,1,$curtain->device);
            //         // usleep(500000);
            //         // $mega->set($port,0,$curtain->device);
            //         // self::percent_db($idObject, '100');
            //         self::open($idObject);
            //     }
            //     elseif ($currentPercent < $percent)
            //     {
            //         self::portPercent($curtain, Device::getNumPort($curtain->openPort), $percent);
            //         self::percent_db($idObject, $percent);
            //     }
            //     else
            //     {
            //         self::portPercent($curtain, Device::getNumPort($curtain->closePort), $percent);
            //         self::percent_db($idObject, $percent);
            //     }
            // }
            // elseif ($curtain->place == 'phase')
            // {
            //     $currentPercent = $curtain->percent;
            //     $mega = new Megad();
            //     $port = Device::getNumPort($curtain->closePort);
            //     $mega->set($port,1,$curtain->device);
            //     sleep($curtain->time+1);
            //     $mega->set($port,0,$curtain->device);
            //     self::percent_db($idObject, '0');
            // }
        }
    }

    /**
     * Включение порта контроллера для открытия шторы на порту на заданный процент
     */
    // private static function portPercent($curtain, $port, $percent)
    // {
    //     $mega = new Megad();
    //     $mega->set($port,1,$curtain->device);
    //     usleep(500000);
    //     $mega->set($port,0,$curtain->device);
    //     usleep((abs($percent-$curtain->percent)/100)*($curtain->time*1000000)-500000);
    //     $mega->set($port,1,$curtain->device);
    //     usleep(500000);
    //     $mega->set($port,0,$curtain->device);
    // }

    // private static function setValue($curtain, $command, $status, $time)
    // {
    //     //var_dump ($curtain);
    //     if($command == 'open')
    //         $port = $curtain->openPort;
    //     else $port = $curtain->closePort;

    //     if($curtain->place == 'port') {

    //         $port = Device::getNumPort($port);

    //         //Включаем порт на определенное время

    //         $mega = new Megad();

    //         if(($status == 'open') || ($status == 'close')) {
    //             $mega->set($port,1,$curtain->device);
    //             usleep(500000);
    //             $mega->set($port,0,$curtain->device);
    //         }else {
    //             $mega->set($port,1,$curtain->device);
    //             usleep(500000);
    //             $mega->set($port,0,$curtain->device);
    //             usleep($time*1000000);
    //             $mega->set($port,1,$curtain->device);
    //             usleep(500000);
    //             $mega->set($port,0,$curtain->device);
    //         }


    //     } else {
    //         //Включаем устройство хитпро на определенное время
    //         $hitePro = HitePro::getDeviceParams($curtain->device);

    //         if(($status == 'open') || ($status == 'close')) {
    //             HitePro::setHiteProCommand($hitePro->ip_address, $hitePro->password, $port, 1);
    //             usleep(500000);
    //             HitePro::setHiteProCommand($hitePro->ip_address, $hitePro->password, $port, 0);
    //         } else {
    //             HitePro::setHiteProCommand($hitePro->ip_address, $hitePro->password, $port, 1);
    //             usleep( 500000);
    //             HitePro::setHiteProCommand($hitePro->ip_address, $hitePro->password, $port, 0);
    //             usleep($time * 1000000);
    //             HitePro::setHiteProCommand($hitePro->ip_address, $hitePro->password, $port, 1);
    //             usleep( 500000);
    //             HitePro::setHiteProCommand($hitePro->ip_address, $hitePro->password, $port, 0);
    //         }
    //     }

    //     //Устанавливаем шторе статус "открыто", связанной кнопке статус on
    //     $object = new Objects();
    //     $object->select($curtain->idObject);
    //     $object->setStatus($status, true, false);

    // }

    /**
     * Получение процента открытия шторы (для штор с RS485)
     */
    public static function getPercent($idObject)
    {
        $curtain = new Curtain($idObject);
        $packet = hex2bin(self::packetAssembling(modbusTcpTransactionId(), 
                            $curtain->address, $curtain->group, '010201')); // 010201 - команда считывания текущего процента открытия
        $response = hexdec(substr((bin2hex(modbusTcpSendCmd($packet, $curtain->gateway_ip, $curtain->gateway_port))), -2));
        self::percent_db($idObject, $response);
        // var_dump ($response);
    }

    /**
     * Запись процента открытия в БД
     */
    private function percent_db($idObject, $value)
    {
        //Заносим процент открытия в БД
        parent::$db->query("UPDATE curtains SET `percent` = $value WHERE id_object='$idObject'");
    }

    /**
     * Считывание текущего состояния двигателя (для штор с RS485)
     */
    public static function getInfo($idObject)
    {
        $curtain = new Curtain($idObject);
        $packet = hex2bin(self::packetAssembling(modbusTcpTransactionId(), 
                            $curtain->address, $curtain->group, '010501')); 
                            // 010501 - команда считывания текущего состояния привода
        $response = hexdec(substr((bin2hex(modbusTcpSendCmd($packet, $curtain->gateway_ip, $curtain->gateway_port))), -2));
                            // Возможные ответы
                            // 0 - остановлен
                            // 1 - открывается
                            // 2 - закрывается
                            // 3 - режим настройки
        //var_dump ($response);
        return $response;
    }

    /**
     * Считывание типа двигателя (для штор с RS485)
     */
    public static function getMotorType($idObject)
    {
        $curtain = new Curtain($idObject);
        $packet = hex2bin(self::packetAssembling(modbusTcpTransactionId(), 
                            $curtain->address, $curtain->group, '01F001')); 
                            // 01F001 - в ответ 17(0x11) - рулонка, 17(0x11) - жалюзи
        $response = hexdec(substr((bin2hex(modbusTcpSendCmd($packet, $curtain->gateway_ip, $curtain->gateway_port))), -2));
        //var_dump ($response);
        return $response;
    }

    /**
     * Сброс конечных положений (для штор с RS485)
     */
    public static function resetLimits($idObject)
    {
        $curtain = new Curtain($idObject);
        $packet = hex2bin(self::packetAssembling(modbusTcpTransactionId(), 
                            $curtain->address, $curtain->group, '0307')); 
                            // 0307 - команда сброса выставленных пределов
        $response = hexdec(substr((bin2hex(modbusTcpSendCmd($packet, $curtain->gateway_ip, $curtain->gateway_port))), -2));
        //var_dump ($response);
    }

    /**
     * Изменение направления привода (для штор с RS485)
     */
    public static function changeDirection($idObject, $direction) // 0 - мотор слева, 1 - мотор справа
    {
        $curtain = new Curtain($idObject);
        $packet = hex2bin(self::packetAssembling(modbusTcpTransactionId(), 
                            $curtain->address, $curtain->group, '020301', $direction)); 
                            // 020301 - команда сброса выставленных пределов
                            // $direction = 0 - левый мотор или $direction = 1 - правый мотор
        $response = hexdec(substr((bin2hex(modbusTcpSendCmd($packet, $curtain->gateway_ip, $curtain->gateway_port))), -2));
        //var_dump ($response)
    }

}
