<?php

require_once '../include.php';

function nbit($number, $n) 
{
    return ($number >> $n) & 1;
}

// Получаем массив существующих адресов устройств 
$sql = System::$db->query("SELECT `address` FROM `dali_devices` WHERE `dali_gateway` = $argv[1] ORDER BY `address`");
while ($existingDaliDevice = $sql->fetch(PDO::FETCH_OBJ)) $existingDaliDevicesArray[] = $existingDaliDevice->address;

// var_dump ($existingDaliDevicesArray);
// var_dump (count($existingDaliDevicesArray));

// Получаем регистр управления сборкой шины DALI
$sql = System::$db->query("SELECT `id` FROM `modbus_registers` WHERE `slaver_id` = $argv[1] AND `alias` = 'dali_assembling'");
$assemblingRegister = $sql->fetch(PDO::FETCH_OBJ)->id;

// Записываем команду запуска сборки шины
Modbus::putTaskIntoQueue($assemblingRegister, 'write', 0, 0x01);

// Запускаем непрерывное считывание значения регистра
Modbus::pollingCtl($assemblingRegister, true, 0);

// Вычитывем из БД значение регистра
$query = System::$db->prepare("SELECT `last_value` FROM `modbus_registers` WHERE `id` = $assemblingRegister");

$start = time();
echo "Выполняется поиск устройств на шине.";
do 
{
    sleep(1);
    $query->execute();
    $registerValue = $query->fetch(PDO::FETCH_OBJ)->last_value;
    echo ".";
} 
while ((int)$registerValue === 17 && (time() - $start) < 60);
echo PHP_EOL;

// Завершаем непрерывное считывание значения регистра
Modbus::pollingCtl($assemblingRegister, false);

if ((int)$registerValue == 6) 
{
    echo "[ERROR] Сбор шины провален" . PHP_EOL;
    exit ("Сбор сети провален. Код ошибки: 0x06.");
}
if ((int)$registerValue == 0)
{
    echo "[OK] Сбор шины выполнен" . PHP_EOL;
    
    // Определяем количество устройств на шине
    $sql = System::$db->query("SELECT `id`
                                 FROM `modbus_registers`
                                WHERE `slaver_id` = $argv[1]
                                  AND `alias` = 'dali_devices_amount'");
    $daliDevicesAmountRegister = $sql->fetch(PDO::FETCH_OBJ)->id;

    $daliDevicesAmount = Modbus::getRegisterValue ($daliDevicesAmountRegister);
    echo "Найдено $daliDevicesAmount устройств" . PHP_EOL;

    // Обнуляем все данные об устройствах в таблице DALI устройств
    // $sql = "UPDATE `dali_devices`
    //            SET `type` = null,
    //                `failure` = null,
    //                `status` = null,
    //                `brightness` = null, 
    //                `cct_control` = null, 
    //                `cct_value` = null
    //          WHERE `dali_gateway` = $argv[1]";
    // $stmt= System::$db->prepare($sql);
    // $stmt->execute();

    // Теперь проверим на каких адресах расположены устройства.
    // Поиск можно остановить, когда все будут определены адреса для всех найденных устройств.
    // Для этого добавляем 1 к $daliAcknoledgedAddresses, когда устройство на адресе обнаружено.
    $daliAcknoledgedAddresses = 0;

    for ($address = 0; $address < 64; $address ++)
    {
        // Получаем массив всех регистров устройства $argv[1] для адреса $address с атрибутом ro
        $sql = System::$db->query("SELECT `id`, `alias` FROM `modbus_registers` 
                                    WHERE `slaver_id` = $argv[1] AND `access` = 'ro' AND `alias` LIKE 'dali%a$address'");
        $daliAddressRegistersArray = array();
        while ($daliAddressRegister = $sql->fetch(PDO::FETCH_OBJ))
            $daliAddressRegistersArray[$daliAddressRegister->alias] = (int)$daliAddressRegister->id;

        // Проверям есть ли на шине устройство с адресом $address
        $daliDeviceType = Modbus::getRegisterValue ($daliAddressRegistersArray["dali_is_on_bus_a$address"]);

        if (isset($daliDeviceType)) 
        {
            $daliAcknoledgedAddresses++;
            // Проверим есть ли в бд запись об устройстве с адресом $address
            $isDeviceExistsQuery = System::$db->query("SELECT * FROM `dali_devices` WHERE `address` =  $address 
                                                       AND `dali_gateway` = $argv[1]");
            $isDeviceExists = $isDeviceExistsQuery->fetch(PDO::FETCH_OBJ);
            
            if ($isDeviceExists)
            {
                // Проверяем есть ли на адресе устройство. Если нет, то записываем его свойства в БД.
                // Если есть, то пропускаем устройство и переходим к следующему адресу.
                if (is_null($isDeviceExists->type))
                {
                    echo "Устройство А$address:" . PHP_EOL;
                    // Обновляем запись в БД.
                    $values = [ 
                        "type"          => $daliDeviceType,
                        "failure"       => $failure,
                        "status"        => $status,
                        "brightness"    => $daliBrightness,
                        "cct_control"   => $cctControl,
                        "cct_value"     => $daliCctValue,
                    ];
                    $stmt = System::$db->prepare("UPDATE `dali_devices`
                                                     SET `type` = :type,
                                                         `address` = :address,
                                                         `failure` = :failure,
                                                         `status` = :status,
                                                         `brightness` = :brightness,
                                                         `cct_control` = :cct_control,
                                                         `cct_value` = :cct_value
                                                   WHERE `address` =  $address AND `dali_gateway` = $argv[1]");
                    $stmt->execute($values);
                }  
            }
            else
            {
                // Если записи не существует, добавляем.
                $placeholders = ":name,:type,:dali_gateway,:address,:failure,:status,:brightness,:cct_control,:cct_value";
                $values = [
                    "name"          => "Устройство А$address", 
                    "type"          => $daliDeviceType,
                    "dali_gateway"  => $argv[1],
                    "address"       => $address,
                    "failure"       => $failure,
                    "status"        => $status,
                    "brightness"    => $daliBrightness,
                    "cct_control"   => $cctControl,
                    "cct_value"     => $daliCctValue,
                ];
                $columns = "name,type,dali_gateway,address,failure,status,brightness,cct_control,cct_value";
                $stmt = System::$db->prepare("INSERT INTO dali_devices ($columns) VALUES ($placeholders)");
                $stmt->execute($values); 
            }
        }
        
        // Если для всех найденых устройсв определены адреса выходим из цикла. Нет смысла продолжать опрос.
        if ($daliAcknoledgedAddresses == $daliDevicesAmount) break;
    }
    echo "Все устройства найдены и добавлены" . PHP_EOL;
}

exit (0);

// Получаем регистр управления сборкой шины DALI
$sql = System::$db->query("SELECT `id` FROM `modbus_registers` WHERE `slaver_id` = $argv[1] AND `alias` = 'dali_assembling'");
$assemblingRegister = $sql->fetch(PDO::FETCH_OBJ)->id;

// Записываем команду запуска расширения шины
Modbus::putTaskIntoQueue($assemblingRegister, 'write', 0, 0x02);

// Запускаем непрерывное считывание значения регистра
Modbus::pollingCtl($assemblingRegister, true, 0);

// Вычитывем из БД значение регистра
$query = System::$db->prepare("SELECT `last_value` FROM `modbus_registers` WHERE `id` = $assemblingRegister");

$start = time();
echo "Выполняется поиск устройст на шине.";
do 
{
    sleep(1);
    $query->execute();
    $registerValue = $query->fetch(PDO::FETCH_OBJ)->last_value;
    // var_dump ($registerValue);
    echo ".";
} 
while ((int)$registerValue === 18 && (time() - $start) < 60);
echo PHP_EOL;

// Завершаем непрерывное считывание значения регистра
Modbus::pollingCtl($assemblingRegister, false);

if ((int)$registerValue == 6) echo "[ERROR] Расширение шины провалено" . PHP_EOL;
if ((int)$registerValue == 0)
{
    echo "[OK] Расширение шины выполнено" . PHP_EOL;
    
    // Определяем количество устройств на шине
    $sql = System::$db->query("SELECT `id`, `last_value`
                                 FROM `modbus_registers`
                                WHERE `slaver_id` = $argv[1]
                                  AND `alias` = 'dali_devices_amount'");
    $daliDevicesAmount = $sql->fetch(PDO::FETCH_OBJ);

    Modbus::putTaskIntoQueue($daliDevicesAmount->id, 'read', 5);
    sleep(3);
    echo "Найдено $daliDevicesAmount->last_value устройств" . PHP_EOL;

    do 
    {

        $processedAddressesAmount;
    } 
    while ((int)$registerValue === 18 && (time() - $start) < 60);

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
        if ($failure) $state = "Нет";
        else $state = "Да";

        $stmt->execute(["dali_cct_variants_a$address"]); 
        $daliCctVariants = $stmt->fetch(PDO::FETCH_OBJ)->last_value;
        
        $cctControl = nbit ($daliCctVariants,1);

        echo "      Тип устройства: $daliDeviceType" . PHP_EOL;
        echo "      Устройство исправно: $state" . PHP_EOL;
        echo "      Статус устройства: $status" . PHP_EOL;
        echo "      Установленная яркость: $daliBrightness" . PHP_EOL;

        if ($cctControl)
        {
            $stmt->execute(["dali_get_temperature_a$address"]); 
            $daliCctValue = $stmt->fetch(PDO::FETCH_OBJ)->last_value;
            echo "      Поддержка управления цветовой температурой: Да" . PHP_EOL;
            echo "      Цветовой температурой: $daliCctValue" . PHP_EOL;
        }
        else $daliCctValue = null;

        $sql = "UPDATE `dali_devices`
                   SET `type` = ?, `brightness` = ?, `status` = ?, `failure` = ?, `cct_control` = ?, `cct_value` = ?
                 WHERE `address` = $address";
        $stmt= System::$db->prepare($sql);
        $stmt->execute([$daliDeviceType, $daliBrightness, $status, $failure, $cctControl, $daliCctValue]);
    }
    exit;
}
