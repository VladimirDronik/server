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
    private $curtain = null;
    private $object = null;
    private $protocol = null;

    // $force=true для принудительного опроса, даже если устройство неактивно
    public function __construct(int $idObject, bool $force = false)
    {
        if (isset($idObject))
        {
            $sql = parent::$db->query(" SELECT `curtains`.`id_object`,
                                               `curtains`.`type`,
                                               `curtains`.`vendor`,
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

            if($sql->rowCount() > 0)
            {
                $this->curtain = $sql->fetch(PDO::FETCH_OBJ);
                if (!$this->curtain->active && !$force)
                {
                    
                    $motorId = $this->curtain->id_object;
                    echo "[Error] Привод с ID $motorId недоступен" . PHP_EOL;
                    System::addLog("Error", "Привод штор (ID $motorId) недоступен", "port");
                    exit(1);
                }
                else
                {
                    $this->object = new Objects();
                    $this->object->select($this->curtain->id_object);

                    if ($this->curtain->vendor == 'aok') $this->protocol = new Aok();
                    if ($this->curtain->vendor == 'onviz') $this->protocol = new Onviz();
                }
            }
            else 
            {
                echo "[Error] Привод штоор (ID $idObject) не найден" . PHP_EOL;
                exit(1);
            }
        }
        else
        {
            echo "[Error] Не определен ID привода штор" . PHP_EOL;
            exit(1);
        }
    }
    

    /**
     * Сборка пакета для отправки (для штор с RS485)
     */
    // private function packetAssembling (mixed $address, mixed $group, string $cmd, mixed $param1=null, mixed $param2=null)
    // {
    //     if ($this->curtain->vendor == 'onviz')
    //     {
    //         $firstByte = '55';
    //         $address = $this->addZero($address);
    //         $group = $this->addZero($group);
    //     }

    //     if ($this->curtain->vendor == 'aok')
    //     {
    //         $firstByte = '9a';
    //         $address = $this->addZero($address);
    //         $group = $this->getAokChannel($group);
    //         $verifyCode = hexdec($address) ^ hexdec(str_split($group, 2)[0]) ^ hexdec(str_split($group, 2)[1]);
    //         if (strlen($cmd) == 4) $verifyCode = $verifyCode ^ hexdec(str_split($cmd, 2)[0]) ^ hexdec(str_split($cmd, 2)[1]);
    //         else $verifyCode = $verifyCode ^ hexdec($cmd) ^ hexdec($param1);
    //     }

    //     $packet = $firstByte . $address . $group . $cmd;
    //     if (isset($param1)) $packet .= $this->addZero($param1);
    //     if (isset($param2)) $packet .= $this->addZero($param2);
    //     if (isset($verifyCode)) $packet .= dechex($verifyCode);

    //     return $packet;
    // }

    // private function addZero($param)
    // {
    //     $param = dechex($param);
    //     if (strlen($param) < 2) return '0' . $param;
    //     else return $param;
    // }

    /**
     * Отправка команды (для штор с RS485)
     */
    private function sendCmd (string $packet, bool $needResponse, $targetByte = null)
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
            $bus = new Rs485();
            $response = $bus->rawCmd($packet, $this->curtain->bus_id, $needResponse, $targetByte);
            return $response;
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
    // public function getInfo()
    // {
    //     $packet = $this->packetAssembling($this->curtain->address, $this->curtain->group, '010501');
    //     $response = $this->sendCmd($packet);
    //     if ($response) return $response;
    // }

    /**
     * Открытие шторы
     */
    public function open()
    {
        if($this->curtain->place == 'rs485')
        {
            $protocol = $this->protocol->getProtocol('open', $this->curtain);
            $response = $this->sendCmd($protocol['cmd'], $protocol['isResponse']);
            
            if (isset($response))
            {
                $this->putPercentToDb(100);
                echo "Штора (ID {$this->curtain->id_object}): Отправлена команда открытия" . PHP_EOL;
                return true;
            }
            else
            {
                self::setRsMotorActivity($this->curtain->id_object, 0);
                echo "Привод штор (ID {$this->curtain->id_object}) недоступен" . PHP_EOL;
                return false;
            }

        }
        elseif ($this->curtain->place == 'port')
        {
            $mega = new Megad();
            $deviceId = Device::getDevice($this->curtain->id_object);
            $port = Device::getNumPort($this->curtain->openPort);
            $mega->set($port, 1, $deviceId);
            usleep(500000);
            $mega->set($port, 0, $deviceId);
            $this->object->setStatus('open', true, false);
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
            $this->object->setStatus('open', true, false);
        }
    }

    /**
     * Закрытие шторы
     */
    public function close()
    {
        if($this->curtain->place == 'rs485')
        {
            $protocol = $this->protocol->getProtocol('close', $this->curtain);
            $response = $this->sendCmd($protocol['cmd'], $protocol['isResponse']);
            
            if (isset($response))
            {
                $this->putPercentToDb(0);
                echo "Штора (ID {$this->curtain->id_object}): Отправлена команда закрытия" . PHP_EOL;
                return true;
            }
            else
            {
                self::setRsMotorActivity($this->curtain->id_object, 0);
                echo "Привод штор (ID {$this->curtain->id_object}) недоступен" . PHP_EOL;
                return false;
            }
        }
        elseif ($this->curtain->place == 'port')
        {
            $mega = new Megad();
            $deviceId = Device::getDevice($this->curtain->id_object);
            $port = Device::getNumPort($this->curtain->closePort);
            $mega->set($port, 1, $deviceId);
            usleep(500000);
            $mega->set($port, 0, $deviceId);
            $this->object->setStatus('close', true, false);
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
            $this->object->setStatus('close', true, false);
        }
    }

    /**
     * Остановка привода (для приводов с RS-485)
     */
    public function stop()
    {
        if($this->curtain->place == 'rs485')
        {
            $protocol = $this->protocol->getProtocol('stop', $this->curtain);
            $response = $this->sendCmd($protocol['cmd'], $protocol['isResponse']);

            if (isset($response))
            {
                // $this->getPercent();
                echo "Штора (ID {$this->curtain->id_object}): Отправлена команда остановки" . PHP_EOL;
                return true;
            }
            else
            {
                self::setRsMotorActivity($this->curtain->id_object, 0);
                echo "Привод штор (ID {$this->curtain->id_object}) недоступен" . PHP_EOL;
                return false;
            }
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
            
            $protocol = $this->protocol->getProtocol('setPercent', $this->curtain, $percent);
            $response = $this->sendCmd($protocol['cmd'], $protocol['isResponse']);

            if (isset($response))
            {
                $this->putPercentToDb($percent);
                echo "Штора (ID {$this->curtain->id_object}): Отправлена команда открытия на $percent%" . PHP_EOL;
                return true;
            }
            else
            {
                self::setRsMotorActivity($this->curtain->id_object, 0);
                echo "Привод штор (ID {$this->curtain->id_object}) недоступен" . PHP_EOL;
                return false;
            }
        }
    }

    /**
     * Получение процента открытия шторы (для штор с RS485)
     */
    public function getPercent(bool $force = false)
    {
        $protocol = $this->protocol->getProtocol('getPercent', $this->curtain);
        $response = $this->sendCmd($protocol['cmd'], $protocol['isResponse'], $protocol['targetByte']);

        if (isset($response))
        {
            $percent = hexdec($response);
            $this->putPercentToDb($percent);
            echo "Штора (ID {$this->curtain->id_object}): Открыта на $percent%" . PHP_EOL;
            return true;
        }
        else
        {
            self::setRsMotorActivity($this->curtain->id_object, 0);
            echo "Привод штор (ID {$this->curtain->id_object}) недоступен" . PHP_EOL;
            return false;
        }
    }

    /**
     * Запись процента открытия в БД
     */
    public function putPercentToDb(int $value)
    {
        parent::$db->query("UPDATE curtains SET `percent` = $value WHERE id_object = " . $this->curtain->id_object);
        if ($value > 0) $this->object->setStatus('open', true, false);
        else $this->object->setStatus('close', true, false);
    }

    /**
     * Сброс конечных положений (для штор с RS485)
     * 0307 - команда сброса выставленных пределов
     */
    // public static function resetLimits()
    // {
    //     $packet = self::packetAssembling($this->curtain->address, $this->curtain->group, '0307');
    //     $response = $this->sendCmd($packet);
    //     if (!$response) self::setRsMotorActivity($this->curtain->id_object, 0);
    // }

    /**
     * Изменение направления привода (для штор с RS485)
     * 02 03 01 XX
     * 00 - мотор слева
     * 01 - мотор справа
     * Для некоторых приводов срабатывает команда 01 03 01, меняет значение на противоположное 
     */
    // public function changeDirection(int $direction = null) // 0 - мотор слева, 1 - мотор справа
    // {
    //     $packet = $this->packetAssembling($this->curtain->address, $this->curtain->group, '020301', $direction);
    //     $response = $this->sendCmd($packet);

    //     if ($response && !$response['error'])
    //     {
    //         echo "Изменено положение привода шторы (ID {$this->curtain->id_object})" . PHP_EOL;
    //         return true;
    //     }
    //     else
    //     {
    //         self::setRsMotorActivity($this->curtain->id_object, 0);
    //         return false;
    //     }
    // }

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
        for ($i=1; $i<=10; $i++)
        {
            $protocol = $this->protocol->getProtocol('setAddress', $this->curtain);
            $response = $this->sendCmd($protocol['cmd'], $protocol['isResponse']);
            
            if ($protocol['isResponse'])
            {
                if (isset($response))
                {
                    if ($response[2] == $this->curtain->address && $response[3] == $this->curtain->group)
                    {
                        echo "Адрес {$this->curtain->address} и группа {$this->curtain->group} установлены" . PHP_EOL;
                        break;
                    }
                    else
                    {
                        echo "Адрес и группа не установлены, ответ пришел с неправильного адреса" . PHP_EOL;
                        echo "Текущие настройки - адрес: " . hexdec($response[2]) . 
                            ", группа: " . hexdec($response[3]) . PHP_EOL;
                        break;
                    }
                }
                else
                {
                    echo "Запрос #$i на установку адреса отправлен, но привод не ответил" . PHP_EOL;
                    if ($i == 10) echo "Адрес и группа не установлены, привод не ответил на запросы" . PHP_EOL;
                }
            }
            else
            {
                echo "Запрос #$i на установку адреса отправлен" . PHP_EOL;
                sleep (1);
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
        while ($motor = $sql->fetch(PDO::FETCH_OBJ)) $rsMotors[] = (int)$motor->id_object;
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
        // else $additionalCondition = "AND `active` = 0";
        else $additionalCondition = null;
        
        $sql = parent::$db->query(" SELECT `id_object`
                                    FROM `curtains`
                                    WHERE `place` = 'rs485'
                                    $additionalCondition");
        if($sql->rowCount() > 0)
        {
            while ($rsMotor = $sql->fetch(PDO::FETCH_OBJ))
            {
                $curtain = new Curtain ($rsMotor->id_object, true);
                $response = $curtain->getPercent();
                if ($response) self::setRsMotorActivity($rsMotor->id_object, 1);
                else self::setRsMotorActivity($rsMotor->id_object, 0);
            }
        }
    }
}