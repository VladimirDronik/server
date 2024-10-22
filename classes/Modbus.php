<?php

use Beanstalk\Client;
use PhpMqtt\Client\MqttClient;

class Modbus extends System {
    
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
                echo "Checking slaver ID $register->slaver_id:  ";
                $response = self::sendModbus($register->id, 'read', null, true);

                if (isset($response))
                {
                    echo "OK" . PHP_EOL;
                    self::setSlaverActivity($register->slaver_id, 1);
                    if (isset($modbusSlaverId)) return true;
                }
                else
                {
                    echo "FAIL" . PHP_EOL;
                    if (isset($modbusSlaverId)) return false;
                }
            }
        }
    }

    public static function crc16($data)
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
     * Постановка задания на чтение/запись регистра(ов) в очередь
     * $force=true для принудительного опроса, даже если устройство неактивно
     */
    public static function sendModbus(int $modbusRegisterId, string $action, mixed $value = null, bool $force = false, int $priority = null)
    {
        if (isset($value)) if (!is_array($value)) $value = [ $value ];
        $uid = uniqid();
        $modbusRegister = self::getModbusRegister($modbusRegisterId);
        $isSlaverActive = self::getSlaverActivity($modbusRegister->slaver_id);
        if ($force) $isSlaverActive = true;

        if ($modbusRegister && $isSlaverActive)
        {
            if ($action == 'read')
            {
                if (!isset($priopity)) $priopity = 100;
                if ($modbusRegister->register_type == 'coil') $modbusFunction = 1;
                if ($modbusRegister->register_type == 'discrete') $modbusFunction = 2;
                if ($modbusRegister->register_type == 'holding') $modbusFunction = 3;
                if ($modbusRegister->register_type == 'input') $modbusFunction = 4;
            }
    
            if ($action == 'write')
            {
                if (!isset($priopity)) $priopity = 50;
                if ($modbusRegister->register_type == 'coil') $modbusFunction = 15;
                else $modbusFunction = 16;
            }
    
            $task = array (
                'protocol' => 'modbus',
                'register_id' => $modbusRegisterId,                         // ID регистра
                'slaver_id' => $modbusRegister->slaver_id,                  // ID устройства
                'function_code' => $modbusFunction,                         // Функция Modbus
                'slave_address' => $modbusRegister->address,                // Адрес ведомого устройства на шине Modbus
                'starting_address' => $modbusRegister->starting_register,   // Адрес первого регистра
                'quantity' => $modbusRegister->registers_quantity,          // Количество регистров для операций чтения
                'value' => $value,                                          // Данные для операций записи
                'format' => $modbusRegister->data_format,                   // Формат считываемых данных
                'title' => $modbusRegister->register_name,                  // Название регистра
                'units' => $modbusRegister->units,                          // Единицы имерения
                'scale' => $modbusRegister->scale_unit,                     // Множитель значения
                'uid' => $uid,
                'needResponse' => true
            );

            BeanstalkQueue::putTask($modbusRegister->bus_id, $task, 5);
            $response = Mqtt::subscribeRs485("rs485/{$modbusRegister->bus_id}/response", $uid);

            if ($response['error'] === true) {
                return null;
            }
            else {
                if ($action == 'read') {
                    self::setValue($modbusRegisterId, $response['response']);
                    $response = $response['response'];
                }
                else {
                    if ($response['response'] > 0) $response = 1;
                    else $response = null;
                } 
                return $response;
            }
        }
    }
}