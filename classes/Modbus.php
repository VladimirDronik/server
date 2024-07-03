<?php

// require_once '../vendor/autoload.php';
use Beanstalk\Client;
use PhpMqtt\Client\MqttClient;

class Modbus extends System {
    
    private static $response = null;
    public static $_busConnection = null;

    /**
     * Создание модбас устройства, на основе данных из БД по id
     */
    public static function getModbusDevice($idModbusDevice)
    {
        $sql = parent::$db->query(" SELECT `modbus_slavers`.`id` AS 'id',
                                           `modbus_slavers`.`name` AS 'name',
                                           `modbus_slavers_types`.`type` 'AS type',
                                           `modbus_slavers`.`address` AS 'address',
                                           `modbus_buses`.`device` AS 'busdevice',
                                           `modbus_buses`.`type` AS 'bustype',
                                           `modbus_buses`.`baudrate` AS 'baudrate',
                                           `modbus_buses`.`parity` AS 'parity',
                                           `modbus_buses`.`stopbits` AS 'stopbits',
                                           `modbus_buses`.`ip` AS 'ip',
                                           `modbus_buses`.`port` AS 'port' 
                                    FROM `modbus_slavers`
                                    INNER JOIN `modbus_slavers_types`
                                    ON `modbus_slavers`.`type` = `modbus_slavers_types`.`id`
                                    INNER JOIN `modbus_buses`
                                    ON `modbus_slavers`.`bus` = `modbus_buses`.`id`
                                    WHERE `modbus_slavers`.`id`= $idModbusDevice");
        if($sql->rowCount() > 0) return $sql->fetch(PDO::FETCH_OBJ);
        else 
        {
            echo "Modbus устройств с ID $idModbusDevice не найдено" . PHP_EOL;
            exit;
        }
    }

    /**
     * Получение параметров регистров устройств ModBus по их id
     */
    public static function getModbusRegister($modbusRegisterId) {

        $sql = parent::$db->query(" SELECT `modbus_registers`.`register_type` AS 'register_type',
                                           `modbus_registers`.`starting_register` AS 'starting_register',
                                           `modbus_registers`.`name` AS 'register_name',
                                           `modbus_registers`.`registers_quantity` AS 'registers_quantity',
                                           `modbus_registers`.`data_format` AS 'data_format',
                                           `modbus_registers`.`units` AS 'units',
                                           `modbus_registers`.`scale_unit` AS 'scale_unit',
                                           `modbus_registers`.`access` AS 'access',
                                           `modbus_slavers`.`id` AS 'slaver_id',
                                           `modbus_slavers`.`address` AS 'address',
                                           `modbus_buses`.`id` AS 'bus_id',
                                           `modbus_buses`.`device` AS 'bus_device'
                                    FROM `modbus_registers`  
                                    INNER JOIN `modbus_slavers`
                                    ON `modbus_slavers`.`id` = `modbus_registers`.`slaver_id`
                                    INNER JOIN `modbus_buses`
                                    ON `modbus_buses`.`id` = `modbus_slavers`.`bus`
                                    WHERE `modbus_registers`.`id`= $modbusRegisterId");
        if($sql->rowCount() > 0) return $sql->fetch(PDO::FETCH_OBJ);
        else 
        {
            echo "Modbus регистр с ID $modbusRegisterId не найден" . PHP_EOL;
            exit;
        }
    }

    /**
     * Получение списка ModbusRTU шин из БД
     */
    public static function getModbusRtuBuses()
    {
        $sql = parent::$db->query(" SELECT `modbus_buses`.`id` AS 'bus_id'
                                    FROM `modbus_buses`
                                    WHERE `modbus_buses`.`type` = 'rtu'");
        
        if($sql->rowCount() > 0)
        {
            $modbusRtuBuses = $sql->fetchAll(PDO::FETCH_OBJ);
            foreach ($modbusRtuBuses AS $modbusRtuBus) $modbusRtuBusesArray[] = $modbusRtuBus->bus_id;
            return $modbusRtuBusesArray;
        }
        
    }

    /**
     * Получение настроек шины из БД
     */
    public static function getModbusBusSettings(int $idBus)
    {
        $sql = parent::$db->query(" SELECT `modbus_buses`.`device` AS 'busdevice',
                                           `modbus_buses`.`type` AS 'bustype',
                                           `modbus_buses`.`baudrate` AS 'baudrate',
                                           `modbus_buses`.`length` AS 'length',
                                           `modbus_buses`.`parity` AS 'parity',
                                           `modbus_buses`.`stopbits` AS 'stopbits',
                                           `modbus_buses`.`ip_address` AS 'ip',
                                           `modbus_buses`.`port` AS 'port'
                                    FROM `modbus_buses`
                                    WHERE `modbus_buses`.`id`= $idBus");
        
        if ($sql->rowCount() > 0) return $sql->fetch(PDO::FETCH_OBJ);
        else
        {
            echo "Modbus шина с ID $modbusRegisterId не найдена" . PHP_EOL;
            exit;
        }
    }

    /**
     * Получение последнего значения регистра из БД
     */
    // public static function getRegisterValueFromDB(int $modbusRegisterId)
    // {
    //     $sql = parent::$db->query("SELECT `modbus_registers`.`last_value` AS last_value,
    //                                       `modbus_registers`.`units` AS units,
    //                                       `modbus_slavers`.`active` AS slaver_active
    //                                  FROM `modbus_registers`
    //                            INNER JOIN `modbus_slavers` ON `modbus_slavers`.`id` = `modbus_registers`.`slaver_id`
    //                                 WHERE `modbus_registers`.`id` = $modbusRegisterId");
    //     $register = $sql->fetch(PDO::FETCH_OBJ);
    //     if ((bool)$register->slaver_active) $lastValue = $register->last_value;
    //     else $lastValue = null;
    //     return $lastValue;
    // }

    /**
     * Получение всех модбас устройств, которые есть на шине по её номеру
     * Возвращает массив модбас устройств, в котором содержится адрес устройства на шине
     */
    public static function getAllDevicesOnBus($busId)
    {
        $sql = parent::$db->query("SELECT id, address FROM `modbus_slavers` WHERE `bus`= $busId");

        if($sql->rowCount() > 0)
        {
            $devices = $sql->fetchAll(PDO::FETCH_OBJ);
            foreach ($devices AS $device) $modbusArray[$device->id] = $device->address;
            return $modbusArray;
        }
    }

    /**
     * Запись значения регистра в БД
     */
    public static function setValue(int $id, mixed $value)
    {
        if (is_null ($value)) $value = 'NULL';
        else $value = "'$value'";
        $sql = parent::$db->exec("UPDATE `modbus_registers`
                                     SET `timestamp` = CURRENT_TIMESTAMP(3),
                                         `last_value` = $value
                                   WHERE `id` = $id");
    }

    /**
     * Установка флага активности для устройства на шине ModBus
     */
    public static function setSlaverActivity(int $slaver_id, int $activity)
    {
        $sql = parent::$db->exec("UPDATE `modbus_slavers` SET `active` = $activity WHERE `id` = $slaver_id");
    }

    /**
     * Получение флага активности для устройства на шине ModBus
     */
    public static function getSlaverActivity(int $slaver_id)
    {
        $sql = parent::$db->query("SELECT `active` FROM `modbus_slavers` WHERE `id` = $slaver_id");
        if ($sql->rowCount() > 0) return $sql->fetch(PDO::FETCH_OBJ)->active;
    }

    /**
     * Получение текущей временной метки регистра
     */
    // public static function getTimemark(int $registerId)
    // {
    //     $sql = parent::$db->query("SELECT `timestamp` FROM `modbus_registers` WHERE `id` = $registerId");
    //     $timestamp = $sql->fetch(PDO::FETCH_OBJ)->timestamp;
    //     $pieces = explode ('.', $timestamp);
    //     $timemark = strtotime($pieces[0]) * 1000 + (int)$pieces[1];
    //     return $timemark;
    // }

    /**
     * Получение списка регистров для опроса
     */
    // public static function getRegistersToPoll (int $polling_cycle, int $busId)
    // {
    //     $sql = parent::$db->query(" SELECT `modbus_registers`.`id` AS 'register_id',
    //                                        `modbus_buses`.`id` AS 'bus_id'
    //                                 FROM `modbus_registers`
    //                                 INNER JOIN `modbus_slavers`
    //                                 ON `modbus_slavers`.`id` = `modbus_registers`.`slaver_id`
    //                                 INNER JOIN `modbus_buses`
    //                                 ON `modbus_buses`.`id` = `modbus_slavers`.`bus`
    //                                 WHERE `modbus_buses`.`id`= $busId 
    //                                 AND `modbus_registers`.`polling` = 1
    //                                 AND `modbus_registers`.`polling_cycle` = $polling_cycle");
    
    //     while ($registers = $sql->fetch(PDO::FETCH_OBJ))
    //     {
    //         $registers_array[] = (int)$registers->register_id;
	// 	}

    //     if (isset($registers_array)) return $registers_array;
    //     else return null;
    // }

    /**
     * Цикл непрерывного опроса регистров устройств на шине $busId
     */
    // public static function pollingLoop (int $busId)
    // {
    //     while (true)
    //     {
    //         $registersToPoll = self::getRegistersToPoll(0, $busId);
    //         if (isset($registersToPoll))
    //         {
    //             foreach($registersToPoll as $registerId)
    //             {
    //                 $response = self::modbusRtu($registerId, 'read', 100);
    //                 if ($response && !$response['error'])
    //                 {
    //                     echo "Polling register ID $registerId... Response: {$response['response']}" . PHP_EOL;
    //                 }
    //             }
    //             $registersToPoll = [];
                
    //         }
    //         else echo "No registers to poll on bus ID $busId" . PHP_EOL;
    //     }
    // }

    /**
     *  Управление опросом регистра
     */
    // public static function pollingCtl (int $registerId, bool $polling, int $pollingCycle = null)
    // {
    //     if ($polling) $polling = 1;
    //     else 
    //     {
    //         $polling = 0;
    //         $pollingCycle = 'NULL';
    //     }

    //     $sql = parent::$db->exec("UPDATE `modbus_registers`
    //                                  SET `polling` = $polling,
    //                                      `polling_cycle` = $pollingCycle
    //                                WHERE `id` = $registerId");
    // }

    /**
     *  Получение id регистра по его alias
     */
    public static function getRegisterIdByAlias (int $slaverId, string $alias)
    {
        $sql = parent::$db->query(" SELECT `id` FROM `modbus_registers` 
                                    WHERE `slaver_id` = $slaverId
                                    AND `alias` = '$alias'");
        if($sql->rowCount() > 0) return $sql->fetch(PDO::FETCH_OBJ)->id;
    }

    /**
     *  Проверка доступности устройства
     */
    public static function checkModbusAvailible(int $modbusSlaverId = null)
    {
        if (isset($modbusSlaverId)) $additionalCondition = "WHERE `slaver_id` = $modbusSlaverId";
        else $additionalCondition = "JOIN `modbus_slavers` " . 
            "ON `modbus_slavers`.`id` = `modbus_registers`.`slaver_id` ";
        
        $sql = parent::$db->query(" SELECT `modbus_registers`.`id`, `modbus_registers`.`slaver_id`
                                    FROM `modbus_registers`
                                    $additionalCondition
                                    GROUP BY `slaver_id`");

        if($sql->rowCount() > 0)
        {
            while ($register = $sql->fetch(PDO::FETCH_OBJ))
            {
                $response = self::modbusRtu($register->id, 'read', 5, null, true);
                if ($response['error'])
                {
                    self::setSlaverActivity($register->slaver_id, 0);

                    if (isset($modbusSlaverId))
                    {
                        echo 'false';
                        return false;
                    }
                }
                else
                {
                    self::setSlaverActivity($register->slaver_id, 1);
                    if (isset($modbusSlaverId))
                    {
                        echo 'true';
                        return true;
                    }
                }
            }
            
        }
    }

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

    public static function modbusRaw(string $cmd, int $busId)
    {
        $uid = uniqid();
        $rawData = pack ('c*', ...array_map('hexdec', str_split(str_replace(' ', '', $cmd), 2)));
        $rawData .= self::crc16($rawData);

        $task = [
            'uid' => $uid,
            'protocol' => 'raw',
            'raw_data' => base64_encode($rawData),
        ];

        BeanstalkQueue::putTask($busId, $task, 5);
        $response = Mqtt::subscribe("modbus/$busId/response", $uid);

        return $response;
    }

    /**
     * Постановка задания на чтение/запись регистра(ов) в очередь
     * $force=true для принудительного опроса, даже если устройство неактивно
     */
    public static function modbusRtu(int $modbusRegisterId, string $action, int $priority, mixed $value = null, bool $force = false)
    {
        $uid = uniqid();

        $modbusRegister = self::getModbusRegister($modbusRegisterId);
        $isSlaverActive = self::getSlaverActivity($modbusRegister->slaver_id);
        if ($force) $isSlaverActive = true;

        if ($modbusRegister && $isSlaverActive)
        {
            if ($action == 'read')
            {
                if ($modbusRegister->register_type == 'coil') $modbusFunction = 1;
                if ($modbusRegister->register_type == 'input_discrete') $modbusFunction = 2;
                if ($modbusRegister->register_type == 'holding') $modbusFunction = 3;
                if ($modbusRegister->register_type == 'input') $modbusFunction = 4;
            }
    
            if ($action == 'write')
            {
                $priority = 0;
                if ($modbusRegister->register_type == 'coil' && $modbusRegister->registers_quantity == 1) $modbusFunction = 5;
                elseif ($modbusRegister->register_type == 'coil' && $modbusRegister->registers_quantity > 1) $modbusFunction = 15;
                elseif ($modbusRegister->register_type != 'coil' && $modbusRegister->registers_quantity > 1) $modbusFunction = 16;
                else $modbusFunction = 6;
            }
    
            $task = array (
                'protocol' => 'rtu',
                'register_id' => $modbusRegisterId,                         // ID регистра
                'slaver_id' => $modbusRegister->slaver_id,                  // ID устройства
                'function_code' => $modbusFunction,                         // Функция Modbus
                'slave_address' => $modbusRegister->address,                // Адрес ведомого устройства на шине Modbus
                'starting_address' => $modbusRegister->starting_register,   // Адрес первого регистра
                'quantity' => $modbusRegister->registers_quantity,          // Количество регистров для операций чтения
                'value' => (int)$value,                                     // Данные для операций записи
                'format' => $modbusRegister->data_format,                   // Формат считываемых данных
                'title' => $modbusRegister->register_name,                  // Название регистра
                'units' => $modbusRegister->units,                          // Единицы имерения
                'scale' => $modbusRegister->scale_unit,                     // Множитель значения
                'uid' => $uid,
            );
            
            BeanstalkQueue::putTask($modbusRegister->bus_id, $task, 5);
            $response = Mqtt::subscribe("modbus/{$modbusRegister->bus_id}/response", $uid);
            if ($response['error']) self::checkModbusAvailible($modbusRegister->slaver_id);
            else self::setValue($modbusRegisterId, $response['response']);
            return $response;
        }
    }

    public static function queue(int $busId)
    {
        function arrayFormat($item)
        {
            $result = dechex($item);
            if (strlen($result) < 2) $result = '0' . $result;
            return $result;
        }

        $queue = BeanstalkQueue::startQueue($busId);
        $bus = self::busConnection($busId);
        
        $writeFunctionCodesArray = [5, 6, 15, 16];

        while (true)
        {
            $job = $queue->reserve(); // Block until job is available.
            $task = json_decode($job['body']);
            
            if ($task->protocol == 'raw') $binRequest = base64_decode($task->raw_data);
            if ($task->protocol == 'rtu') $binRequest = modbusFunction($task);

            $request = array_map('arrayFormat', unpack('C*', $binRequest));
            $request = implode(" ", $request);

            if ($binaryData = $bus->send($binRequest))
            {
                $rawResponse = unpack('C*', $binaryData);
                $rawResponse = array_map('arrayFormat', unpack('C*', $binaryData));
                // $rawResponse = implode(" ", $rawResponse);
                $error = false;
                if ($task->protocol == 'rtu') $response = modbusFunction($task, true, $binaryData);
                var_dump($response);
            }
            else 
            {
                $rawResponse = null;
                $response = null;
                $error = true;
                $errorCode = "No response from device";
            }

            $topic = "modbus/$busId/response";
            $payload = [
                'uid' => $task->uid,
                'error' => $error,
                'protocol' => $task->protocol,
                'request' => $request,
                'raw_response' => $rawResponse,
            ];
            if (isset($response)) $payload += ['response' => $response,];
            if ($error) $payload += ['error_code' => $errorCode,];
            Mqtt::publish($topic, $payload);

            $queue->delete($job['id']);
        }
    }

    public static function busConnection(int $busId)
    {
            $bus = Modbus::getModbusBusSettings($busId);
            if ($bus)
            {
                $modbus = new ModbusRtu ();
                $modbus->deviceInit($bus->busdevice, $bus->baudrate, $bus->parity,
                                    $bus->length, $bus->stopbits, 'none');
                $modbus->deviceOpen();
                $modbus->debug = true;
                return $modbus;
            }
            else return false;
    }
}