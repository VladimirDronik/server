<?php

/**
 * Класс работы со шторами.
 * 
 * Для приводов с фазным управлением и сухим контактом доступны методы: open и close.
 * Повторный вызов метода останавливает привод.
 * 
 * Для приводов с RS485 доступны методы: open, close, stop, setPercent.
 * Для унификации методов повторный вызов метода open или close останавливает привод.
 */

class Curtain extends Device
{
    public $curtain = null;

    public function __construct($idObject = null)
    {
        if (isset($idObject))
        {
            $sql = parent::$db->query(" SELECT `curtains`.`id_object`,
                                               `curtains`.`type`,
                                               `curtains`.`port_open` AS 'openPort',
                                               `curtains`.`port_close` AS 'closePort',
                                               `curtains`.`time`,
                                               `curtains`.`place`,
                                               `curtains`.`address`,
                                               `curtains`.`group`,
                                               `curtains`.`percent`,
                                               `curtains`.`bus_id`,
                                               `curtains`.`is_inverse`,
                                               `curtains`.`active`,
                                               `modbus_buses`.`type` AS 'bus_type'
                                        FROM `curtains`
                                        LEFT JOIN `modbus_buses` ON `modbus_buses`.`id` = `curtains`.`bus_id`
                                        WHERE `curtains`.`id_object` = $idObject");

            if($sql->rowCount() > 0) $this->curtain = $sql->fetch(PDO::FETCH_OBJ);
            else 
            {
                echo "Объект не найден" . PHP_EOL;
                exit;
            }
        }
    }
    

    /**
     * Сборка пакета для отправки (для штор с RS485)
     */
    private function packetAssembling (mixed $address, mixed $group, string $cmd, mixed $param1=null, mixed $param2=null)
    {
        $packet = '55' . $this->format($address) . $this->format($group) . $cmd;
        if (isset($param1)) $packet .= $this->format($param1);
        if (isset($param2)) $packet .= $this->format($param2);
        return $packet;
    }

    private function format($param)
    {
        $param = dechex($param);
        if (strlen($param) < 2) return '0' . $param;
        else return $param;
    }

    /**
     * Отправка команды (для штор с RS485)
     */
    private function sendCmd (string $packet, string $cmd=null, bool $force = false)
	{
	    if ($this->curtain->bus_type == "tcp")
        {
            // TODO: Переделать реализацию для ModbusTCP
            $modbus = new ModbusTcp();
            $modbus->debug = false;
            $modbus->socketCreate();
            $modbus->socketSetOption();
            $modbus->socketConnect($this->curtain->gateway_ip, $this->curtain->gateway_port);
            $f = $modbus->modbusTcpTransactionId()."\x00\x00".pack('n', strlen($packet)).$packet;
            $modbus->socketWrite($f);
            $result = $modbus->socketRead();
            $result = unpack('C*', $result);
            $result = $result[count($result)];
            $modbus->socketClose();
        }
        
        if ($this->curtain->bus_type == "rtu")
        {
            $isRSMotorActive = $this->curtain->active;
            if ($force) $isRSMotorActive = true;

            if ($isRSMotorActive)
            {
                $response = Modbus::modbusRaw($packet, $this->curtain->bus_id);
                return $response;
            }
        }
	}
    
    /**
     * Считывание текущего состояния двигателя (для штор с RS485)
     *  010501 - команда считывания текущего состояния привода
     * Возможные ответы 01 01 XX
     * 00 - остановлен
     * 01 - открывается
     * 02 - закрывается
     * 03 - режим настройки
     */
    public function getInfo()
    {
        $packet = $this->packetAssembling($this->curtain->address, $this->curtain->group, '010501');
        $response = $this->sendCmd($packet);
        if ($response) return $response;
    }

    /**
     * Открытие шторы
     */
    public function open()
    {
        $object = new Objects();
        $object->select($this->curtain->id_object);

        if($this->curtain->place == 'rs485')
        {
            // 0301 - команда открытия
            $packet = $this->packetAssembling($this->curtain->address, $this->curtain->group, '0301');
            $response = $this->sendCmd($packet);
            if ($response && !$response['error']) $this->putPercentToDb(100);
            else self::setRsMotorActivity($this->curtain->id_object, 0);
        }
        elseif ($this->curtain->place == 'port')
        {
            $mega = new Megad();
            $deviceId = Device::getDevice($this->curtain->id_object);
            $port = Device::getNumPort($this->curtain->openPort);
            $mega->set($port, 1, $deviceId);
            usleep(500000);
            $mega->set($port, 0, $deviceId);
            $object->setStatus('open', true, false);
        }
        elseif ($this->curtain->place == 'phase')
        {
            $mega = new Megad();
            $deviceId = Device::getDevice($this->curtain->id_object);
            $closePort = Device::getNumPort($this->curtain->closePort);
            $openPort = Device::getNumPort($this->curtain->openPort);
            
            if ($mega->status($closePort, 'get', $deviceId) != 'OFF')
            {
                $mega->set($closePort, 0, $deviceId);
                usleep(500000);
            }

            if ($mega->status($openPort, 'get', $deviceId) != 'OFF')
                $mega->set($openPort, 0, $deviceId);
            else
            {
                $mega->set($openPort, 1, $deviceId);
                sleep($this->curtain->time+1);
                $mega->set($openPort, 0, $deviceId);
            }
            $object->setStatus('open', true, false);
        }
    }

    /**
     * Закрытие шторы
     */
    public function close()
    {
        $object = new Objects();
        $object->select($this->curtain->id_object);

        if($this->curtain->place == 'rs485')
        {
            // 0302 - команда закрытия
            $packet = $this->packetAssembling($this->curtain->address, $this->curtain->group, '0302');
            $response = $this->sendCmd($packet);
            if ($response && !$response['error']) $this->putPercentToDb(0);
            else self::setRsMotorActivity($this->curtain->id_object, 0);
        }
        elseif ($this->curtain->place == 'port')
        {
            $mega = new Megad();
            $deviceId = Device::getDevice($this->curtain->id_object);
            $port = Device::getNumPort($this->curtain->closePort);
            $mega->set($port, 1, $deviceId);
            usleep(500000);
            $mega->set($port, 0, $deviceId);
            $object->setStatus('close', true, false);
        }
        elseif ($this->curtain->place == 'phase')
        {
            $mega = new Megad();
            $deviceId = Device::getDevice($this->curtain->id_object);
            $closePort = Device::getNumPort($this->curtain->closePort);
            $openPort = Device::getNumPort($this->curtain->openPort);

            if ($mega->status($openPort, 'get', $deviceId) != 'OFF')
            {
                $mega->set($openPort, 0, $deviceId);
                usleep(500000);
            }

            if ($mega->status($closePort, 'get', $deviceId) != 'OFF')
                $mega->set($closePort, 0, $deviceId);
            else
            {
                $mega->set($closePort, 1, $deviceId);
                sleep($this->curtain->time+1);
                $mega->set($closePort, 0, $deviceId);
            }
            $object->setStatus('close', true, false);
        }
    }

    /**
     * Остановка привода (для приводов с RS-485)
     */
    public function stop()
    {
        if($this->curtain->place == 'rs485')
        {
            // 0303 - команда остановки
            $packet = $this->packetAssembling($this->curtain->address, $this->curtain->group, '0303');
            $response = $this->sendCmd($packet);
            if ($response && !$response['error']) $this->getPercent();
            else self::setRsMotorActivity($this->curtain->id_object, 0);
        }
    }

    /**
     * Открытие шторы на процент (для приводов с RS-485)
     */
    public function setPercent(int $percent)
    {
        if ($percent >= 0 && $percent <= 100)
        {
            if ($this->curtain->is_inverse) $percent = 100 - $percent;
            var_dump ($percent);
            // 0304 - команда установки процента открытия
            $packet = $this->packetAssembling($this->curtain->address, $this->curtain->group, '0304', $percent);
            $response = $this->sendCmd($packet);
            if (!$response['error']) $this->putPercentToDb($percent);
            else return $response;
        }
    }

    /**
     * Получение процента открытия шторы (для штор с RS485)
     * 010201 - команда считывания текущего процента открытия
     * Ответ 01 01 XX
     */
    public function getPercent(bool $force = false)
    {
        $packet = $this->packetAssembling($this->curtain->address, $this->curtain->group, '010201');
        $response = $this->sendCmd($packet, null, $force);
        if ($response && !$response['error']) $this->putPercentToDb(hexdec($response['raw_response'][6]));
        else self::setRsMotorActivity($this->curtain->id_object, 0);
    }

    /**
     * Запись процента открытия в БД
     */
    public function putPercentToDb(int $value)
    {
        parent::$db->query("UPDATE curtains SET `percent` = $value WHERE id_object = " . $this->curtain->id_object);
    }

    /**
     * Сброс конечных положений (для штор с RS485)
     * 0307 - команда сброса выставленных пределов
     */
    public static function resetLimits()
    {
        $packet = self::packetAssembling($this->curtain->address, $this->curtain->group, '0307');
        $response = $this->sendCmd($packet);
        if (!$response) self::setRsMotorActivity($this->curtain->id_object, 0);
    }

    /**
     * Изменение направления привода (для штор с RS485)
     * 02 03 01 XX
     * 00 - мотор слева
     * 01 - мотор справа
     * Для некоторых приводов срабатывает команда 01 03 01, меняет значение на противоположное 
     */
    public function changeDirection(int $direction = null) // 0 - мотор слева, 1 - мотор справа
    {
        $packet = $this->packetAssembling($this->curtain->address, $this->curtain->group, '020301', $direction);
        $response = $this->sendCmd($packet);
        if (!$response) self::setRsMotorActivity($this->curtain->id_object, 0);
    }

    /**
     * Смена адреса (для штор с RS485)
     */
    public function changeAddress(int $newAddress, int $newGroup)
    {
        $packet = $this->packetAssembling($this->curtain->address, $this->curtain->group, '020002', $newAddress, $newGroup);
        $response = $this->sendCmd($packet);
        if (!$response) self::setRsMotorActivity($this->curtain->id_object, 0);
    }

    /**
     * Назначение адреса (для штор с RS485)
     * Метод для приводов рулонных штор:
     * - сброс привода (удерживать кнопку на приводе до второго звукового сигнала)
     * - отправка команды по адресу 0xFE 0xFE
     * Метод для приводов раздвижных штор:
     * - удерживать кнопку сброса до двух миганий либо непрерывного мигания светодиода (~5 сек)
     * - отправка команды по адресу 0x00 0x00
     */
    public function setAddress()
    {
        if ($this->curtain->type == 'roller')
        {
            $address = 'FE';
            $group = 'FE';
        }
        if ($this->curtain->type == 'curtain')
        {
            $address = '00';
            $group = '00';
        }

        for ($i=1; $i<=10; $i++)
        {
            $packet = $this->packetAssembling($address, $group, '020002', $this->curtain->address, $this->curtain->group);
            $response = $this->sendCmd($packet);
            if ($response['error'])
            {
                echo "Запрос #$i на установку адреса отправлен, но привод не ответил" . PHP_EOL;
                if ($i == 10) echo "Адрес и группа не установлены, привод не ответил на запросы" . PHP_EOL;
            }
            else
            {
                if ($response['raw_response'][2] == $this->curtain->address && $response['raw_response'][3] == $this->curtain->group)
                {
                    echo "Адрес {$this->curtain->address} и группа {$this->curtain->group} установлены" . PHP_EOL;
                    break;
                }
                else
                {
                    echo "Адрес и группа не установлены, ответ пришел с неправильного адреса" . PHP_EOL;
                    echo "Текущие настройки - адрес: " . hexdec($response['raw_response'][2]) . 
                        ", группа: " . hexdec($response['raw_response'][3]) . PHP_EOL;
                    break;
                }
            }
        }
    }

    /**
     * Сброс (для штор с RS485)
     */
    public function reset()
    {
        $packet = self::packetAssembling($this->curtain->address, $this->curtain->group, '0308');
        $response = $this->sendCmd($packet);
        if (!$response) self::setRsMotorActivity($this->curtain->id_object, 0);
    }

    /**
     * Получение списка приводов штор с RS485
     */
    public static function getRsMotors()
    {
        $sql = parent::$db->query(" SELECT `curtains`.`id_object`
                                    FROM `curtains`
                                    WHERE `curtains`.`place` = 'rs485'");
        
        $rsMotors = [];
        while ($motor = $sql->fetch(PDO::FETCH_OBJ))
            $rsMotors[] = (int)$motor->id_object;
        
        return $rsMotors;  
    }

    /**
     * Установка флага активности для привода с RS485
     */
    private static function setRsMotorActivity(int $rsMotorId, int $activity)
    {
        parent::$db->exec("UPDATE `curtains` SET `active` = $activity WHERE `id_object` = $rsMotorId");
    }

    /**
     * Получение флага активности для привода с RS485
     */
    public static function getRsMotorActivity(int $rsMotorId)
    {
        $sql = parent::$db->query("SELECT `active` FROM `curtains` WHERE `id_object` = $rsMotorId");
        $activity = $sql->fetch(PDO::FETCH_OBJ);
        return $activity->active;
    }

    /**
     *  Проверка доступности привода с RS485
     */
    public static function checkRsMotorAvailible(int $rsMotorId = null)
    {
        if (isset($rsMotorId)) $additionalCondition = "AND `id_object` = $rsMotorId";
        else $additionalCondition = "AND `active` = 0";
        
        $sql = parent::$db->query(" SELECT `id_object`
                                    FROM `curtains`
                                    WHERE `place` = 'rs485'
                                    $additionalCondition");
        if($sql->rowCount() > 0)
        {
            while ($rsMotor = $sql->fetch(PDO::FETCH_OBJ))
            {
                $curtain = new Curtain ($rsMotor->id_object);
                $response = $curtain->getPercent();
                if ($response['error']) self::setRsMotorActivity($rsMotor->id_object, 0);
                else self::setRsMotorActivity($rsMotor->id_object, 1);
            }
        }
    }

    // private function responseProcessing(array $response)
    // {
    //     if ($response['error']) $this->setRsMotorActivity($this->curtain->id_object, 0);
    //     else $this->setRsMotorActivity($this->curtain->id_object, 1);
    // }
}
