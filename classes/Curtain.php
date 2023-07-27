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
    private static $_curtain = null;

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
                                    devices.port AS `gateway_port`,
                                    devtypes.name AS `gateway_type`
                                    FROM curtains 
                                    JOIN devices ON devices.id=curtains.device_id
                                    JOIN devtypes ON devtypes.id=devices.type
                                    WHERE curtains.id_object = $idObject");

        $curtain =  $sql->fetch(PDO::FETCH_OBJ);
        $this->idObject = $curtain->id_object;
        $this->place = $curtain->place;
        
        if($curtain->place == 'rs485')
        {
            $this->address = $curtain->address;
            $this->group = $curtain->group;
            if ($curtain->gateway_type == 'ModbusTCP')
            {
                $this->gateway_ip = $curtain->gateway_ip;
                $this->gateway_port = $curtain->gateway_port;
            }
            $this->percent = $curtain->percent;
            $this->gateway_type = $curtain->gateway_type;
        }
        else
        {
            $this->openPort = $curtain->port_open;
            $this->closePort = $curtain->port_close;
            $this->time = $curtain->time;
            if($curtain->place == 'port' || $curtain->place == 'phase') 
            $this->device = Device::getDevice($idObject);
        }
        self::$_curtain = $this;
    }

    /**
     * Сборка пакета для отправки (для штор с RS485)
     */
    private function packetAssembling ($address, $group, $cmd, $percent=null, $direction=null)
    {
        $packet = array_merge([0x55, $address, $group], $cmd);
        $packet = pack ('c*', ...$packet);
        if ($percent) $packet .= pack ("c", $percent);
        if ($direction) $packet .= pack ("c", $direction);
        return $packet;
    }

    private function sendCmd ($packet)
	{
        $curtain = self::$_curtain;

	    if ($curtain->gateway_type == "ModbusTCP")
        {
            $modbus = new ModbusTcp();
            $modbus->debug = false;
            $modbus->socketCreate();
            $modbus->socketSetOption();
            $modbus->socketConnect($curtain->gateway_ip, $curtain->gateway_port);
            $f = $modbus->modbusTcpTransactionId()."\x00\x00".pack('n', strlen($packet)).$packet.$modbus->crc16($packet);
            $modbus->socketWrite($f);
            $result = $modbus->socketRead();
            $result = unpack('C*', $result);
            $result = $result[count($result)];
            $modbus->socketClose();
        }
        else
        {
            if ($curtain->gateway_type == "JetHomeModbusSerial0") $dev = '/dev/ttyUSB0';
            if ($curtain->gateway_type == "JetHomeModbusSerial1") $dev = '/dev/ttyUSB1';

            $modbus = new PhpSerialModbus;
            $modbus->deviceInit($dev, 9600, 'none', 8, 1, 'none');
            $modbus->deviceOpen();
            $modbus->debug = false;
            $modbus->sendRawQuery($packet.$modbus->crc16($packet),false);
            $result = $modbus->getResponse(true);
            $result = unpack('C*', $result);
            $result = $result[count($result)-2];
            $modbus->deviceClose();
        }
        return $result;
	}
    
    /**
     * Считывание текущего состояния двигателя (для штор с RS485)
     */
    public static function getInfo()
    {
        $curtain = self::$_curtain;
        $packet = self::packetAssembling($curtain->address, $curtain->group, [0x01, 0x05, 0x01]); // 010501 - команда считывания текущего состояния привода
        $response = self::sendCmd($packet);
            // Возможные ответы
            // 0 - остановлен
            // 1 - открывается
            // 2 - закрывается
            // 3 - режим настройки
        return $response;
    }

    /**
     * Открытие шторы
     */
    public function open()
    {
        $curtain = self::$_curtain;
        if($curtain->place == 'rs485')
        {
            if (self::getInfo() != 0) self::stop();
            else
            {
                $packet = self::packetAssembling($curtain->address, $curtain->group, [0x03, 0x01]); // 0301 - команда открытия
                self::sendCmd($packet);
                self::percent_db(100);
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
    public static function close()
    {
        $curtain = self::$_curtain;
        if($curtain->place == 'rs485')
        {
            if (self::getInfo() != 0) self::stop();
            else
            {
                $packet = self::packetAssembling($curtain->address, $curtain->group, [0x03, 0x02]); // 0302 - команда закрытия
                self::sendCmd($packet);
                self::percent_db(0);
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
    public static function stop()
    {
        $curtain = self::$_curtain;
        if($curtain->place == 'rs485')
        {
            $packet = self::packetAssembling($curtain->address, $curtain->group, [0x03, 0x03]); // 0303 - команда остановки
            self::sendCmd($packet);
            self::getPercent();
        }
    }

    /**
     * Открытие шторы на процент (для приводов с RS-485)
     */
    public static function openPercent($percent)
    {
        if ($percent >= 0 && $percent <= 100)
        {
            $curtain = self::$_curtain;
            $packet = self::packetAssembling($curtain->address, $curtain->group, [0x03, 0x04], $percent); 
                // 0304 - команда установки процента открытия
            $response = self::sendCmd($packet);

            if ($response > 100)
            {
                if (self::getMotorType() == 17)
                {
                    echo "[WARNING] Set the limits first!\n";
                }
                else
                {
                    self::open();
                    while (self::getInfo() != 0) usleep(500000);
                    self::close();
                    while (self::getInfo() != 0) usleep(500000);
                    $response = self::sendCmd($packet);
                }
            }
            self::percent_db($response);
        }
    }

    /**
     * Получение процента открытия шторы (для штор с RS485)
     */
    public static function getPercent()
    {
        $curtain = self::$_curtain;
        $packet = self::packetAssembling($curtain->address, $curtain->group, [0x01, 0x02, 0x01]); 
            // 010201 - команда считывания текущего процента открытия
        $response = self::sendCmd($packet);
        self::percent_db($response);
    }

    /**
     * Запись процента открытия в БД
     */
    private function percent_db($value)
    {
        $curtain = self::$_curtain;
        //Заносим процент открытия в БД
        parent::$db->query("UPDATE curtains SET `percent` = $value WHERE id_object='$curtain->idObject'");
    }

    /**
     * Считывание типа двигателя (для штор с RS485)
     */
    public static function getMotorType()
    {
        $curtain = self::$_curtain;
        $packet = self::packetAssembling($curtain->address, $curtain->group, [0x01, 0xF0, 0x01]); 
            // 01F001 - в ответ 17(0x11) - рулонка, ???17(0x11)??? - жалюзи
        $response = self::sendCmd($packet);
        return $response;
    }

    /**
     * Сброс конечных положений (для штор с RS485)
     */
    public static function resetLimits()
    {
        $curtain = self::$_curtain;
        $packet = self::packetAssembling($curtain->address, $curtain->group, [0x03, 0x07]); 
            // 0307 - команда сброса выставленных пределов
       self::sendCmd($packet);
    }

    /**
     * Изменение направления привода (для штор с RS485)
     */
    public static function changeDirection($direction) // 0 - мотор слева, 1 - мотор справа
    {
        $curtain = self::$_curtain;
        $packet = self::packetAssembling($curtain->address, $curtain->group, [0x02, 0x03, 0x01], $direction); 
            // 020301 - команда сброса выставленных пределов
            // $direction = 0 - левый мотор или $direction = 1 - правый мотор
        self::sendCmd($packet);
    }
}
