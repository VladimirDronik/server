<?php

require_once '../include.php';

function nbit($number, $n) 
{
    return ($number >> $n) & 1;
}

// Удаляем все регистры устройств DALI из БД
$sql = "DELETE FROM `modbus_registers` WHERE `starting_register` >= 3000 AND `slaver_id` = $argv[1]";
System::$db->prepare($sql)->execute();

// Удаляем все устройства DALI из БД
$sql = "DELETE FROM `dali_devices` WHERE `dali_gateway` = $argv[1]";
System::$db->prepare($sql)->execute();

// Получаем регистр управления сборкой шины DALI
$sql = System::$db->query("SELECT `id` FROM `modbus_registers` WHERE `slaver_id` = $argv[1] AND `alias` = 'dali_assembling'");
$assemblingRegister = $sql->fetch(PDO::FETCH_OBJ)->id;

// Записываем команду запуска сборки шины
Modbus::putTaskIntoQueue($assemblingRegister, 'write', 0, 0x01);

// Запускаем непрерывное считывание значения регистра
Modbus::pollingCtl($assemblingRegister, true, 0);

// Вычитывем из БД значение регистра
$query = System::$db->prepare("SELECT `last_value`
                                 FROM `modbus_registers`
                                WHERE `id` = $assemblingRegister");

while (true)
{
    sleep (1);
    $query->execute();
    $registerValue = $query->fetch(PDO::FETCH_OBJ)->last_value;
    var_dump ($registerValue);
    if ((int)$registerValue != 17) break;
}

// Завершаем непрерывное считывание значения регистра
Modbus::pollingCtl($assemblingRegister, false);

if ((int)$registerValue == 6) echo "[ERROR] Сбор шины провален" . PHP_EOL;
if ((int)$registerValue == 0)
{
    echo "[OK] Сбор шины выполнен" . PHP_EOL;
    
    // Определяем количество устройств на шине
    $sql = System::$db->query("SELECT `id`, `last_value`
                                 FROM `modbus_registers`
                                WHERE `slaver_id` = $argv[1]
                                  AND `alias` = 'dali_devices_amount'");
    $daliDevicesAmount = $sql->fetch(PDO::FETCH_OBJ);

    Modbus::putTaskIntoQueue($daliDevicesAmount->id, 'read', 5);
    sleep(3);
    echo "Найдено $daliDevicesAmount->last_value устройств" . PHP_EOL;

    for ($address = 0; $address < $daliDevicesAmount->last_value; $address ++)
    {
        echo "  Устройство с адресом A".(int)$address.":" . PHP_EOL;
        // Список регистров для устройства DALI. Указаны только уникальные значения
        $registers = [
            [
                "name" => "Установка уровня яркости устройства А$address",
                "alias" => "dali_set_brightness_a$address",
                "starting_address" => 3000+$address*5, 
                "access" => "rw"
            ],
            [
                "name" => "Команда управления устройством А$address",
                "alias" => "dali_sent_cmd_a$address",
                "starting_address" => 3001+$address*5,
                "access" => "rw"
            ],
            [
                "name" => "Присутствие на шине устройства А$address",
                "alias" => "dali_is_on_bus_a$address",
                "starting_address" => 3002+$address*5,
                "access" => "ro"
            ],
            [
                "name" => "Запрос состояния устройства А$address",
                "alias" => "dali_device_status_a$address",
                "starting_address" => 3003+$address*5,
                "access" => "ro"
            ],
            [
                "name" => "Запрос текущего уровня яркости устройства А$address",
                "alias" => "dali_get_brightness_a$address",
                "starting_address" => 3004+$address*5,
                "access" => "ro"
            ],
            [
                "name" => "Установка цветовой температуры устройства А$address",
                "alias" => "dali_set_temperature_a$address",
                "starting_address" => 3320+$address*5,
                "access" => "rw"
            ],
            [
                "name" => "Регулирование цветовой температурой устройства А$address",
                "alias" => "dali_set_brightness_by_step_a$address",
                "starting_address" => 3321+$address*5,
                "access" => "rw",
            ],
            [
                "name" => "Запрос вариантов управления цветом устройства А$address",
                "alias" => "dali_cct_variants_a$address",
                "starting_address" => 3322+$address*5,
                "access" => "ro"
            ],
            [
                "name" => "Запрос статуса устройства А$address",
                "alias" => "dali_temperature_status_a$address",
                "starting_address" => 3323+$address*5,
                "access" => "ro"
            ],
            [
                "name" => "Запрос цветовой температуры устройства А$address",
                "alias" => "dali_get_temperature_a$address",
                "starting_address" => 3324+$address*5,
                "access" => "ro",
            ],
        ];
        
        // Запрос для добавления регистров
        $addRegistersQuery =  System::$db->prepare("INSERT INTO modbus_registers (slaver_id,name,register_type,alias,
                                       starting_register,registers_quantity,data_format,access,polling) 
                                       VALUES ($argv[1],:name,'holding',:alias,:starting_address,1,'u16',:access,0)");
        
        // // Запрос для проверки наличия регистров в БД
        // $select = System::$db->prepare('SELECT * FROM `modbus_registers`
        //                                 WHERE `starting_register` = :starting_address AND `slaver_id` = '.$argv[1]);
        
        // Список устройств DALI
        $daliDevices = [
            'name' => "Устройство А$address",
            'address' => $address, 
        ];
        // Запрос для добавления устройств DALI
        $addDaliDevicesQuery = System::$db->prepare("INSERT INTO dali_devices (dali_gateway, name, address) 
                                                     VALUES ($argv[1], :name, :address)");

        // Добавляем регистры в БД и считываем из них
        foreach ($registers as $row) 
        {
            $addRegistersQuery->execute($row);
            // echo "register $row[starting_address] added" . PHP_EOL;
            $sql = System::$db->query("SELECT `id` 
                                         FROM `modbus_registers` 
                                        WHERE `slaver_id` = $argv[1] 
                                          AND `alias` = '$row[alias]'
                                        --   AND `starting_register` = $row[starting_address]
                                          AND `access` = 'ro'");

            $addedRegister = $sql->fetch(PDO::FETCH_OBJ);
            if ($addedRegister) Modbus::putTaskIntoQueue($addedRegister->id, 'read', 5);
        }
        
        $addDaliDevicesQuery->execute($daliDevices);
        
        
        sleep (3);
        $stmt = System::$db->prepare("SELECT `modbus_registers`.`last_value` FROM `modbus_registers`
                                  INNER JOIN `dali_devices` ON `dali_devices`.`dali_gateway` = `modbus_registers`.`slaver_id`
                                         AND `dali_devices`.`address` = $address
                                       WHERE `modbus_registers`.`alias` = ?");
        
        $stmt->execute(["dali_is_on_bus_a$address"]); 
        $daliDeviceType = $stmt->fetch(PDO::FETCH_OBJ)->last_value;

        $stmt->execute(["dali_get_brightness_a$address"]);
        $daliBrightness = $stmt->fetch(PDO::FETCH_OBJ)->last_value;
        
        $stmt->execute(["dali_device_status_a$address"]); 
        $daliStatus = $stmt->fetch(PDO::FETCH_OBJ)->last_value;

        if (nbit ($daliStatus,2) == 0) $status = "off";
        else $status = "on";

        $failure = nbit ($daliStatus,1);

        $stmt->execute(["dali_cct_variants_a$address"]); 
        $daliCctVariants = $stmt->fetch(PDO::FETCH_OBJ)->last_value;
        
        $cctControl = nbit ($daliCctVariants,1);

        if ($cctControl)
        {
            $stmt->execute(["dali_get_temperature_a$address"]); 
            $daliCctValue = $stmt->fetch(PDO::FETCH_OBJ)->last_value;
        }
        else $daliCctValue = null;

        $sql = "UPDATE `dali_devices`
                   SET `type` = ?, `brightness` = ?, `status` = ?, `failure` = ?, `cct_control` = ?, `cct_value` = ?
                 WHERE `address` = $address";
        $stmt= System::$db->prepare($sql);
        $stmt->execute([$daliDeviceType, $daliBrightness, $status, $failure, $cctControl, $daliCctValue]);

    }

    // var_dump ($row["alias"]);

}


