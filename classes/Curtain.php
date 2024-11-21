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

class Curtain extends System
{
    public $curtain = null;
    public $object = null;

    public function __construct(int $idObject)
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
                $this->object = new Objects();
                $this->object->select($this->curtain->id_object);
                
                if ($this->curtain->vendor == 'aok') require_once __DIR__ . '/Rs485Protocols/aok.php';
                if ($this->curtain->vendor == 'onviz') require_once __DIR__ . '/Rs485Protocols/onviz.php';
            }
            else echo "[Error] Привод штоор (ID $idObject) не найден" . PHP_EOL;
        }
        else echo "[Error] Не определен ID привода штор" . PHP_EOL;
    }

    /**
     * Отправка команды (для штор с RS485)
     */
    private function sendCmd (string $packet, bool $needResponse)
	{
        $bus = new Rs485($this->curtain->bus_id);
        $response = $bus->sendRaw($packet, $needResponse);
        return $response;
	}

    /**
     * Открытие шторы
     */
    public function open()
    {
        if($this->curtain->place == 'rs485')
        {
            $protocol = getProtocol('open', $this->curtain);
            $response = $this->sendCmd($protocol['cmd'], $protocol['isResponse']);
            
            if (isset($response))
            {
                $this->putPercentToDb(100);
                echo "Штора (ID {$this->curtain->id_object}): Отправлена команда открытия" . PHP_EOL;
                // if ($this->object->status != "open")
                // {
                //     $aliceCapabilities = [
                //         "type" => "devices.capabilities.on_off",
                //         "state" => [
                //             "instance" => "on",
                //             "value" => 1
                //         ]
                //     ];
                //     $payload = [
                //         "object_id" => $this->object->id,
                //         "capabilities" => $aliceCapabilities,
                //         "properties" => null
                //     ];
                //     $mqtt = new Mqtt();
                //     $mqtt->publish('alice/callback', $payload, false);
                // }
                $this->setRsMotorActivity(1);
                return true;
            }
            else
            {
                echo "Привод штор (ID {$this->curtain->id_object}) недоступен" . PHP_EOL;
                $this->setRsMotorActivity(0);
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
            $protocol = getProtocol('close', $this->curtain);
            $response = $this->sendCmd($protocol['cmd'], $protocol['isResponse']);
            
            if (isset($response))
            {
                $this->putPercentToDb(0);
                echo "Штора (ID {$this->curtain->id_object}): Отправлена команда закрытия" . PHP_EOL;

                // if ($this->object->status != "close")
                // {
                //     $aliceCapabilities = [
                //         "type" => "devices.capabilities.on_off",
                //         "state" => [
                //             "instance" => "on",
                //             "value" => 0
                //         ]
                //     ];
                //     $payload = [
                //         "object_id" => $this->object->id,
                //         "capabilities" => $aliceCapabilities,
                //         "properties" => null
                //     ];
                //     $mqtt = new Mqtt();
                //     $mqtt->publish('alice/callback', $payload, false);
                // }
                $this->setRsMotorActivity(1);
                return true;
            }
            else
            {
                echo "Привод штор (ID {$this->curtain->id_object}) недоступен" . PHP_EOL;
                $this->setRsMotorActivity(0);
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
            $protocol = getProtocol('stop', $this->curtain);
            $response = $this->sendCmd($protocol['cmd'], $protocol['isResponse']);

            if (isset($response))
            {
                // $this->getPercent();
                echo "Штора (ID {$this->curtain->id_object}): Отправлена команда остановки" . PHP_EOL;
                $this->setRsMotorActivity(1);
                sleep(1);
                $this->getPercent();
                return true;
            }
            else
            {
                echo "Привод штор (ID {$this->curtain->id_object}) недоступен" . PHP_EOL;
                $this->setRsMotorActivity(0);
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
            if ($this->curtain->is_inverse) $inversePercent = 100 - $percent;
            else $inversePercent = $percent;

            $protocol = getProtocol('setPercent', $this->curtain, $inversePercent);

            $response = $this->sendCmd($protocol['cmd'], $protocol['isResponse']);

            if (isset($response))
            {
                $this->putPercentToDb($percent);
                echo "Штора (ID {$this->curtain->id_object}): Отправлена команда открытия на $percent%" . PHP_EOL;
                if ($this->curtain->percent != $percent)
                {
                    $aliceCapabilities = [
                        "type" => "devices.capabilities.range",
                        "state" => [
                            "instance" => "open",
                            "value" => $percent
                        ]
                    ];
                    $payload = [
                        "object_id" => $this->object->id,
                        "capabilities" => $aliceCapabilities,
                        "properties" => null
                    ];
                    $mqtt = new Mqtt();
                    $mqtt->publish('alice/callback', $payload, false);
                }
                $this->setRsMotorActivity(1);
                return true;
            }
            else
            {
                echo "Привод штор (ID {$this->curtain->id_object}) недоступен" . PHP_EOL;
                $this->setRsMotorActivity(0);
                return false;
            }
        }
    }

    /**
     * Получение процента открытия шторы (для штор с RS485)
     */
    public function getPercent(bool $force = false)
    {
        $protocol = getProtocol('getPercent', $this->curtain);
        $response = $this->sendCmd($protocol['cmd'], $protocol['isResponse']);

        if (isset($response) && isset($response[$protocol['targetByte']]))
        {
            $percent = hexdec($response[$protocol['targetByte']]);

            if ($this->curtain->is_inverse) $percent = 100 - $percent;

            $this->putPercentToDb($percent);
            echo "Штора (ID {$this->curtain->id_object}): Открыта на $percent%" . PHP_EOL;

            if ($this->curtain->percent != $percent) {
                $aliceCapabilities = [
                    "type" => "devices.capabilities.range",
                    "state" => [
                        "instance" => "open",
                        "value" => $percent
                    ]
                ];
                $payload = [
                    "object_id" => $this->object->id,
                    "capabilities" => $aliceCapabilities,
                    "properties" => null
                ];
                $mqtt = new Mqtt();
                $mqtt->publish('alice/callback', $payload, false);
            }
            $this->setRsMotorActivity(1);
            return true;
        }
        else
        {
            echo "Привод штор (ID {$this->curtain->id_object}) недоступен" . PHP_EOL;
            $this->setRsMotorActivity(0);
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
     * Назначение адреса (для штор с RS485)
     * 
     * ONVIZ:
     * Метод для приводов рулонных штор:
     * - сброс привода (удерживать кнопку на приводе до второго звукового сигнала)
     * - отправка команды по адресу 0xFE 0xFE
     * Метод для приводов раздвижных штор:
     * - удерживать кнопку сброса до двух миганий либо непрерывного мигания светодиода (~5 сек)
     * - отправка команды по адресу 0x00 0x00
     * 
     * A-OK:
     * - нажать кнопку ввода в режим программирования
     * - отправить команду (ответ не предусмотрен)
     */
    public function setAddress()
    {
        for ($i=1; $i<=10; $i++)
        {
            $protocol = getProtocol('setAddress', $this->curtain);
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
        // if (!$response) self::setRsMotorActivity($this->curtain->id_object, 0);
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
    private function setRsMotorActivity(int $activity)
    {
        parent::$db->exec("UPDATE `curtains` SET `active` = $activity WHERE `id_object` = {$this->curtain->id_object}");
    }

    /**
     * Получение флага активности для привода с RS485
     */
    public function getRsMotorActivity()
    {
        $sql = parent::$db->query("SELECT `active` FROM `curtains` WHERE `id_object` = {$this->curtain->id_object}");
        $activity = $sql->fetch(PDO::FETCH_OBJ);
        return $activity->active;
    }

    /**
     *  Проверка доступности привода с RS485
     */
    public static function checkRsMotorAvailible(int $rsMotorId = null)
    {
        if (isset($rsMotorId)) $additionalCondition = "AND `id_object` = $rsMotorId";
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
                if ($response) $curtain->setRsMotorActivity(1);
                else $curtain->setRsMotorActivity(0);
            }
        }
    }
}