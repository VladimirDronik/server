<?php

require_once '../include.php';

function nbit($number, $n) 
{
    return ($number >> $n) & 1;
}

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

    // Modbus::putTaskIntoQueue($daliDevicesAmount->id, 'read', 5);
    // sleep(3);
    $daliDevicesAmount = Modbus::getRegisterValue ($daliDevicesAmountRegister);
    echo "Найдено $daliDevicesAmount устройств" . PHP_EOL;
    
    // Обнуляем все данные об устройствах в таблице DALI устройств
    $sql = "UPDATE `dali_devices`
               SET `type` = null,
                   `failure` = null,
                   `status` = null,
                   `brightness` = null, 
                   `cct_control` = null, 
                   `cct_value` = null
             WHERE `dali_gateway` = $argv[1]";
    $stmt= System::$db->prepare($sql);
    $stmt->execute();

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
            echo "Устройство А$address:" . PHP_EOL;
            // Получаем данные устройства
            // Регистр 3003+A*5 - Статус устройства
            $daliStatus = Modbus::getRegisterValue ($daliAddressRegistersArray["dali_device_status_a$address"]);
            // bit 1 - неисправность устройства. 0 = ОК; 1 = неисправность
            $failure = nbit ($daliStatus,1);
            // bit 2 - состояние устройства. 0 = off; 1 = on
            if (nbit ($daliStatus,2) == 0) $status = "off";
            else $status = "on";
            
            // Регистр 3004+A*5 - Текущий уровень яркости
            $daliBrightness = Modbus::getRegisterValue ($daliAddressRegistersArray["dali_get_brightness_a$address"]);

            // Регистр 3322+A*5 - Варианты управления цветом
            $daliCctVariants = Modbus::getRegisterValue ($daliAddressRegistersArray["dali_cct_variants_a$address"]);
            // bit 1 - управление цветовой температурой. 0 = не поддерживается; 1 = поддерживается
            $cctControl = nbit ($daliCctVariants,1);

            echo "      Тип устройства: $daliDeviceType" . PHP_EOL;
            echo "      Устройство неисправно: $failure" . PHP_EOL;
            echo "      Статус устройства: $status" . PHP_EOL;
            echo "      Установленная яркость: $daliBrightness" . PHP_EOL;
            
            // Если устройство поддерживает управление цветовой температурой, то можем считать значение 
            if ($cctControl)
            {
                $daliCctValue = Modbus::getRegisterValue ($daliAddressRegistersArray["dali_get_temperature_a$address"]);
                echo "      Поддержка управления цветовой температурой: Да" . PHP_EOL;
                echo "      Цветовая температура: $daliCctValue" . PHP_EOL;
            }
            else $daliCctValue = null;
            
            // Проверим есть ли в бд запись об устройстве с адресом $address
            $isRowExistsQuery = System::$db->query("SELECT * FROM `dali_devices` WHERE `address` =  $address 
                                                       AND `dali_gateway` = $argv[1]");
            $isRowExists = $isRowExistsQuery->fetch(PDO::FETCH_OBJ);

            if (!$isRowExists)
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
            else
            {
                // Если запись была добавлена ранее, обновляем.
                $values = [ 
                    "type"          => $daliDeviceType,
                    "address"       => $address,
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
        
        // Если для всех найденых устройсв определены адреса выходим из цикла. Нет смысла продолжать опрос.
        if ($daliAcknoledgedAddresses == $daliDevicesAmount) break;
    }
    echo "Все устройства найдены и добавлены" . PHP_EOL;
}

exit (0);