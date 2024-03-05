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

use Beanstalk\Client;

class Curtain extends Device
{
    private $curtain;

    public function __construct($idObject, $busId = null)
    {
        $this->initCurtain($idObject, $busId);
    }

    private function initCurtain($idObject, $busId = null)
    {
        if (isset($idObject))
        {
            $sql = parent::$db->query(" SELECT `curtains`.`port_open` AS 'openPort',
                                               `curtains`.`port_close` AS 'closePort',
                                               `curtains`.`time`,
                                               `curtains`.`place`,
                                               `curtains`.`address`,
                                               `curtains`.`group`,
                                               `curtains`.`percent`,
                                               `curtains`.`bus_id`,
                                               `modbus_buses`.`type` AS 'bus_type'
                                        FROM `curtains`
                                        LEFT JOIN `modbus_buses` ON `modbus_buses`.`id` = `curtains`.`bus_id`
                                        WHERE `curtains`.`id_object` = $idObject");

            if ($this->curtain = $sql->fetch(PDO::FETCH_OBJ))
            {
                $this->curtain->id_object = $idObject;
            }
        }
        else
        {
            if (isset($busId))
            {
                $sql = parent::$db->query(" SELECT `modbus_buses`.`type` AS 'bus_type'
                                            FROM `modbus_buses`
                                            WHERE `modbus_buses`.`id` = $busId");

                $this->curtain = $sql->fetch(PDO::FETCH_OBJ);
                $this->curtain->bus_id = $busId;
                $this->curtain->id_object = null;
            }
        }
    }

    /**
     * Функция расчета CRC суммы (для штор с RS485)
     */
    private static function crc16($data)
	{
		$crc = 0xFFFF;
		for ($i = 0; $i < strlen($data); $i++)
		{
			$crc ^=ord($data[$i]);
     		for ($j = 8; $j !=0; $j--)
			{
				if (($crc & 0x0001) !=0)
				{
					$crc >>= 1;
					$crc ^= 0xA001;
				}
				else $crc >>= 1;
			}		
		}
		$highCrc=floor($crc/256);
		$lowCrc=($crc-$highCrc*256);
		return chr($lowCrc).chr($highCrc);
	}

    /**
     * Сборка пакета для отправки (для штор с RS485)
     */
    private static function packetAssembling (int $address, int $group, array $cmd, array $params=null)
    {
        $packet = array_merge([0x55, $address, $group], $cmd);
        $packet = pack ('c*', ...$packet);
        if (isset($params)) $packet .= pack ("c*", ...$params);
        $packet .= self::crc16($packet);
        return $packet;
    }

    /**
     * Отправка команды (для штор с RS485)
     */
    private function sendCmd (string $packet, string $cmd=null)
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
            $beanstalk = new Client();
            $beanstalk->connect();
            $beanstalk->useTube($this->curtain->bus_id);
            $task = [
                'mode' => 'rs485_curtains',
                'object_id' => $this->curtain->id_object,
                'raw_data' => base64_encode($packet),
                'command' => $cmd
            ];
            $beanstalk->put(5, 0, 5, json_encode($task));
        }
	}
    
    /**
     * Считывание текущего состояния двигателя (для штор с RS485)
     */
    public static function getInfo()
    {
        // 010501 - команда считывания текущего состояния привода
        // Возможные ответы
        // 0 - остановлен
        // 1 - открывается
        // 2 - закрывается
        // 3 - режим настройки
        $packet = self::packetAssembling($this->curtain->address, $this->curtain->group, [0x01, 0x05, 0x01]);
        self::sendCmd($packet);
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
            // $packet = self::packetAssembling($this->curtain->address, $this->curtain->group, [0x03, 0x01]);
            // $this->sendCmd($packet);
            // $this->percent_db(100);
            $this->setPercent(100);
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
            // $packet = self::packetAssembling($this->curtain->address, $this->curtain->group, [0x03, 0x02]);
            // $this->sendCmd($packet);
            // $this->percent_db(0);
            $this->setPercent(0);
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
            $packet = self::packetAssembling($this->curtain->address, $this->curtain->group, [0x03, 0x03]);
            $this->sendCmd($packet);
            $this->getPercent();
        }
    }

    /**
     * Открытие шторы на процент (для приводов с RS-485)
     */
    public function setPercent(int $percent)
    {
        if ($percent >= 0 && $percent <= 100)
        {
            // 0304 - команда установки процента открытия
            $packet = self::packetAssembling($this->curtain->address, $this->curtain->group, [0x03, 0x04], [$percent]);
            $this->sendCmd($packet, 'setPercent');
        }
    }

    /**
     * Получение процента открытия шторы (для штор с RS485)
     */
    public function getPercent()
    {
        // 010201 - команда считывания текущего процента открытия
        $packet = self::packetAssembling($this->curtain->address, $this->curtain->group, [0x01, 0x02, 0x01]);
        $this->sendCmd($packet, 'getPercent');
    }

    /**
     * Запись процента открытия в БД
     */
    public function putPercentToDb(int $value)
    {
        //Заносим процент открытия в БД
        parent::$db->query("UPDATE curtains SET `percent` = $value WHERE id_object = " . $this->curtain->id_object);
    }

    /**
     * Считывание типа двигателя (для штор с RS485)
     */
    public function getMotorType()
    {
        $packet = $this->packetAssembling($this->curtain->address, $this->curtain->group, [0x01, 0xF0, 0x01]); 
            // 01F001 - в ответ 17(0x11) - рулонка, ???17(0x11)??? - жалюзи
        $response = $this->sendCmd($packet, 'getMotorType');
        // return $response;
    }

    /**
     * Сброс конечных положений (для штор с RS485)
     */
    public static function resetLimits()
    {
        // 0307 - команда сброса выставленных пределов
        $packet = self::packetAssembling($this->curtain->address, $this->curtain->group, [0x03, 0x07]); 
        $this->sendCmd($packet);
    }

    /**
     * Изменение направления привода (для штор с RS485)
     */
    public function changeDirection(int $direction) // 0 - мотор слева, 1 - мотор справа
    {
        // 020301 - команда сброса выставленных пределов
        // $direction = 0 - левый мотор или $direction = 1 - правый мотор
        $packet = $this->packetAssembling($this->curtain->address, $this->curtain->group, [0x02, 0x03, 0x01], [$direction]);
        $this->sendCmd($packet);
    }

    /**
     * Смена адреса (для штор с RS485)
     */
    public function changeAddress(int $newAddress, int $newGroup)
    {
        $packet = $this->packetAssembling($this->curtain->address, $this->curtain->group, [0x02, 0x00, 0x02], [$newAddress, $newGroup]);
        $this->sendCmd($packet);
    }

    /**
     * Назначение адреса (для штор с RS485)
     * Для установки адреса после сброса
     */
    public function setAddress(int $newAddress, int $newGroup)
    {
        $packet = $this->packetAssembling(0xFE, 0xFE, [0x02, 0x00, 0x02], [$newAddress, $newGroup]);
        $this->sendCmd($packet);
    }

    /**
     * Сброс (для штор с RS485)
     */
    public function reset()
    {
        $packet = self::packetAssembling($this->curtain->address, $this->curtain->group, [0x03, 0x08]);
        $this->sendCmd($packet);
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
}
