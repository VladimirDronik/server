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
    public static function getModbusDevice($idModbusDevice) {

        $sql = parent::$db->query("SELECT `modbus_slavers`.`id` AS id, `modbus_slavers`.`name` AS name, `modbus_slavers_types`.`type` AS type, 
        `modbus_slavers`.`address` AS address, `modbus_buses`.`device` AS busdevice, `modbus_buses`.`type` AS bustype,
        `modbus_buses`.`baudrate` AS baudrate, `modbus_buses`.`parity` AS parity, `modbus_buses`.`stopbits` AS stopbits,
        `modbus_buses`.`ip` AS ip, `modbus_buses`.`port` AS port 
          FROM `modbus_slavers`  
          INNER JOIN `modbus_slavers_types` ON modbus_slavers.type = modbus_slavers_types.id 
          INNER JOIN `modbus_buses` ON modbus_slavers.bus = modbus_buses.id WHERE `modbus_slavers`.`id`= $idModbusDevice");
        
        $device = $sql->fetch(PDO::FETCH_OBJ);
        return $device;
    }

    /**
     * Получение параметров регистров устройств ModBus по их id
     */
    public static function getModbusRegister($modbusRegisterId) {

        $sql = parent::$db->query("SELECT `modbus_registers`.`register_type` AS register_type,
                                          `modbus_registers`.`starting_register` AS starting_register,
                                          `modbus_registers`.`name` AS register_name,
                                          `modbus_registers`.`registers_quantity` AS registers_quantity,
                                          `modbus_registers`.`data_format` AS data_format,
                                          `modbus_registers`.`units` AS units,
                                          `modbus_registers`.`scale_unit` AS scale_unit,
                                          `modbus_registers`.`access` AS access,
                                          `modbus_slavers`.`id` AS slaver_id,
                                          `modbus_slavers`.`address` AS address,
                                          `modbus_buses`.`id` AS bus_id,
                                          `modbus_buses`.`device` AS bus_device
                                     FROM `modbus_registers`  
                               INNER JOIN `modbus_slavers` ON `modbus_slavers`.`id` = `modbus_registers`.`slaver_id`
                               INNER JOIN `modbus_buses` ON `modbus_buses`.`id` = `modbus_slavers`.`bus`
                                    WHERE `modbus_registers`.`id`= $modbusRegisterId");
        
        $modbusRegister = $sql->fetch(PDO::FETCH_OBJ);
        return $modbusRegister;
    }

    /**
     * Получение списка ModbusRTU шин из БД
     */
    public static function getModbusRtuBuses()
    {
        $sql = parent::$db->query("SELECT `modbus_buses`.`id` AS bus_id
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
        $sql = parent::$db->query("SELECT `modbus_buses`.`device` AS busdevice,
                                          `modbus_buses`.`type` AS bustype,
                                          `modbus_buses`.`baudrate` AS baudrate,
                                          `modbus_buses`.`length` AS length,
                                          `modbus_buses`.`parity` AS parity,
                                          `modbus_buses`.`stopbits` AS stopbits,
                                          `modbus_buses`.`ip_address` AS ip,
                                          `modbus_buses`.`port` AS port
                                     FROM `modbus_buses`
                                    WHERE `modbus_buses`.`id`= $idBus");
        
        if ($sql->rowCount() > 0) return $sql->fetch(PDO::FETCH_OBJ);
        else return false;
    }

    

    /**
     * Получение последнего значения регистра из БД
     */
    public static function getRegisterValueFromDB(int $modbusRegisterId)
    {
        $sql = parent::$db->query("SELECT `modbus_registers`.`last_value` AS last_value,
                                          `modbus_registers`.`units` AS units,
                                          `modbus_slavers`.`active` AS slaver_active
                                     FROM `modbus_registers`
                               INNER JOIN `modbus_slavers` ON `modbus_slavers`.`id` = `modbus_registers`.`slaver_id`
                                    WHERE `modbus_registers`.`id` = $modbusRegisterId");
        $register = $sql->fetch(PDO::FETCH_OBJ);
        if ((bool)$register->slaver_active) $lastValue = $register->last_value;
        else $lastValue = null;
        return $lastValue;
    }

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
     * Опрос всех RO регистров, которые есть у устройства, для занесения данных в БД
     */
    public static function checkRegistersOnDevice($idDevice)
    {
       //TODO:: здесь нужно реализовать опрос шины выбранного устройства и реализовать запись в БД, например с помощью метода setValue ниже
    }

    /**
     * Запуск команды на устройстве modbus по его id
     */
    public static function runCommand($idRegister, $value) {
        //Извлекаем данные из таблицы регистров
        $sql = parent::$db->query("SELECT * FROM `modbus_registers` WHERE `id`=$idRegister");
        $register = $sql->fetch(PDO::FETCH_OBJ);

            //Смотрим какой тип регистра, если на запись, то создаем задание в очереди с высоким приоритетом, если на чтение, 
            //то читаем из таблицы и возвращаем результат
            if ($register->access == 'ro') {
                return $register->last_value;
            } elseif ($register->access == 'rw') { //Запись данных в шину модбас
    
                $modbusDevice = self::getModbusDevice($register->slaver_id); //Здесь получаем устройство модбаса, с которым хотим работать со всеми его параметрами
                //TODO:: здесь нужно сделать запись в очередь с высоким приоритетом
            }
        }

    /**
     * Запись значения регистра в БД с регистрами при опросе шины
     */
    public static function setValue(int $id, mixed $value)
    {
        if (is_null ($value)) $value = 'NULL';
        else $value = "'$value'";
        // var_dump ($value);
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
     * Получение текущей временной метки регистра
     */
    public static function getTimemark(int $registerId)
    {
        $sql = parent::$db->query("SELECT `timestamp` FROM `modbus_registers` WHERE `id` = $registerId");
        $timestamp = $sql->fetch(PDO::FETCH_OBJ)->timestamp;
        $pieces = explode ('.', $timestamp);
        $timemark = strtotime($pieces[0]) * 1000 + (int)$pieces[1];
        return $timemark;
    }

    /**
     * Получение списка регистров для опроса
     */
    public static function getRegistersToPoll (int $polling_cycle, int $busId)
    {
        $sql = parent::$db->query("SELECT `modbus_registers`.`id` AS register_id,
                                          `modbus_registers`.`timestamp` AS timestamp,
                                          `modbus_buses`.`id` AS bus_id
                                     FROM `modbus_registers`
                               INNER JOIN `modbus_slavers` ON `modbus_slavers`.`id` = `modbus_registers`.`slaver_id`
                               INNER JOIN `modbus_buses` ON `modbus_buses`.`id` = `modbus_slavers`.`bus`
                                    WHERE `modbus_buses`.`id`= $busId 
                                      AND `modbus_registers`.`polling` = 1
                                      AND `modbus_registers`.`polling_cycle` = $polling_cycle");
    
        while ($registers = $sql->fetch(PDO::FETCH_OBJ))
        {
            if ($polling_cycle == 0)
            {
                $pieces = explode ('.', $registers->timestamp);
                $timestamp = strtotime($pieces[0]) * 1000 + (int)$pieces[1];
                $registers_array[(int)$registers->register_id] = $timestamp;
            }
            else
            {
                $registers_array[] = (int)$registers->register_id;
            }
		}

        if (isset($registers_array)) return $registers_array;
        else return false;
    }

    /**
     * Цикл непрерывного опроса регистров устройств на шине $busId
     */
    public static function pollingLoop (int $polling_cycle, int $busId)
    {
        $registersArray = [];

            while (true)
            {
                $diff = [];
                usleep(500000);
                $registersUpdatedArray = self::getRegistersToPoll($polling_cycle, $busId);
                if ($registersUpdatedArray)
                {
                //Ищем изменения, эти регистры отправить в очередь повторно
                    $diff = array_diff($registersUpdatedArray, $registersArray);
                    if ($diff)
                    {
                        foreach ($diff as $value)
                        {
                            $registerId = array_search($value, $diff);
                            $output = [];
                            exec("ps aux | grep '[m]odbus_queue.php $busId'", $output);
                            $queryString = "SELECT `modbus_slavers`.`active`
                                            FROM `modbus_slavers`
                                            JOIN `modbus_registers` ON `modbus_slavers`.`id` = `modbus_registers`.`slaver_id`
                                            WHERE `modbus_registers`.`id`= $registerId";
                            $sql = parent::$db->query($queryString);
                            $isSlaverActive = $sql->fetch(PDO::FETCH_OBJ)->active;

                            if ($output && $isSlaverActive)
                            {
                                echo (new datetime())->format('Y-m-d H:i:s.v') .
                                    "   Polling register id " .
                                    $registerId . " is sent to the queue" . PHP_EOL;
                                self::putTaskIntoQueue($registerId, 'read', 100);
                            }
                        }
                    }
                    //Обновляем исходный массив
                    $registersArray = array_replace($registersArray, $diff);
                }
                // else echo date("Y-m-d H:i:s.u") . "   Registers are not found on " . $busId . " bus" . PHP_EOL;
            }
    }

    /**
     *  Управление опросом регистра
     */
    public static function pollingCtl (int $registerId, bool $polling, int $pollingCycle = null)
    {
        if ($polling) $polling = 1;
        else 
        {
            $polling = 0;
            $pollingCycle = 'NULL';
        }

        $sql = parent::$db->exec("UPDATE `modbus_registers`
                                     SET `polling` = $polling,
                                         `polling_cycle` = $pollingCycle
                                   WHERE `id` = $registerId");
    }

    /**
     *  Считывание значения из регистра(ов) и возврат результата
     */
    public static function getRegisterValue (int $registerId, int $priority = null)
    {
        $referenceTimemark = self::getTimemark($registerId);
        if (!isset($priority)) $priority = 5;
        self::putTaskIntoQueue($registerId, 'read', $priority);
        $start = time()*1000;
        do
        {
            usleep(500000);
            $currentTimemark = self::getTimemark($registerId);
        }
        while ($currentTimemark === $referenceTimemark && (time()*1000-$start) < 5000);

        if ($currentTimemark === $referenceTimemark) exit ("[Error] Нет ответа от modbus устройства");
        else
        {
            $sql = parent::$db->query(" SELECT `last_value`, `data_format`
                                        FROM `modbus_registers` WHERE `id` = $registerId");
            $value = $sql->fetch(PDO::FETCH_OBJ);
            switch ($value->data_format)
            {
                case 'bool':
                    $result = filter_var($value->last_value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'string':
                    $result = $value->last_value;
                    break;
                default:
                    $result = filter_var($value->last_value, FILTER_VALIDATE_INT);
            }
            return $result;
        }
    }

    


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
        else $additionalCondition = "JOIN `modbus_slavers` ". 
            "ON `modbus_slavers`.`id` = `modbus_registers`.`slaver_id` " .
            "WHERE `modbus_slavers`.`active` = 0";
        
        $sql = parent::$db->query(" SELECT `modbus_registers`.`id`
                                    FROM `modbus_registers`
                                    $additionalCondition
                                    GROUP BY `slaver_id`");

        if($sql->rowCount() > 0)
            while ($register = $sql->fetch(PDO::FETCH_OBJ))
                self::putTaskIntoQueue($register->id, 'read', 5);
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
     */
    public static function modbusRtu(int $modbusRegisterId, string $action, int $priority, mixed $value = null, string $uid = null)
    {
        // if (!isset($uid)) $uid = uniqid();
        $uid = uniqid();

        $modbusRegister = self::getModbusRegister($modbusRegisterId);
        if ($modbusRegister)
        {
            // var_dump($modbusRegister);
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
            
            // $beanstalk = new Client();
            // $beanstalk->connect();
            // $beanstalk->useTube($modbusRegister->bus_id);
    
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
            
            // $beanstalk->put($priority, 0, 5, json_encode($task));
            BeanstalkQueue::putTask($modbusRegister->bus_id, $task, 5);
            $response = Mqtt::subscribe("modbus/{$modbusRegister->bus_id}/response", $uid);
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
            // var_dump ($task);
            
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
            }
            else 
            {
                $rawResponse = null;
                $response = null;
                $error = true;
                $errorCode = "No response from device";
            }

                // var_dump ($response);
                // Modbus::setValue($task->register_id, $response);
                // Modbus::setSlaverActivity($task->slaver_id, $activity);
            // }

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