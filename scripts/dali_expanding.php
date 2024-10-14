<?php

require_once '../include.php';

$daliGatewayId = $argv[1];

// Получаем массив существующих адресов устройств
$sql = System::$db->query(" SELECT `address`
                            FROM `dali_devices`
                            WHERE `dali_gateway` = $daliGatewayId
                            ORDER BY `address`");
while ($existingDaliDevice = $sql->fetch(PDO::FETCH_OBJ))
    $existingDaliDevicesArray[] = $existingDaliDevice->address;

// Получаем регистр управления сборкой шины DALI
$sql = System::$db->query(" SELECT `id`
                            FROM `modbus_registers`
                            WHERE `slaver_id` = $daliGatewayId
                            AND `alias` = 'dali_bus_assembling'");
$assemblingRegister = $sql->fetch(PDO::FETCH_OBJ)->id;

// // Записываем команду запуска сборки шины
// Modbus::putTaskIntoQueue($assemblingRegister, 'write', 0, 0x02);

// // Запускаем непрерывное считывание значения регистра
// Modbus::pollingCtl($assemblingRegister, true, 0);

// // Вычитывем из БД значение регистра
// $query = System::$db->prepare("SELECT `last_value` FROM `modbus_registers` WHERE `id` = $assemblingRegister");

// $start = time();
// echo "Выполняется поиск устройств на шине.";
// do 
// {
//     sleep(1);
//     $query->execute();
//     $registerValue = $query->fetch(PDO::FETCH_OBJ)->last_value;
//     echo ".";
// } 
// while ((int)$registerValue === 18 && (time() - $start) < 60);
// echo PHP_EOL;

// // Завершаем непрерывное считывание значения регистра
// Modbus::pollingCtl($assemblingRegister, false);

$response = Modbus::sendModbus($assemblingRegister, 'write', 0x02);
if (!isset($response)) exit(1);

// Ждем окончания процесса сборки шины
echo "Выполняется поиск устройств на шине";
while ($registerValue = (int)Modbus::sendModbus($assemblingRegister, 'read') == 0x12) echo ".";
if ((int)$registerValue == 0x06)
{
    echo "[FAIL]" . PHP_EOL;
    exit (1);
}
if ((int)$registerValue == 0) echo "[OK]" . PHP_EOL;

// Определяем количество устройств на шине
$sql = System::$db->query(" SELECT `id`
                            FROM `modbus_registers`
                            WHERE `slaver_id` = $daliGatewayId
                            AND `alias` = 'dali_devices_quantity'");
$daliDevicesAmountRegister = $sql->fetch(PDO::FETCH_OBJ)->id;

$daliDevicesAmount = Modbus::sendModbus($daliDevicesAmountRegister, 'read');
echo "Найдено $daliDevicesAmount устройств" . PHP_EOL;

    // Теперь проверим на каких адресах расположены устройства.
    // Поиск выполняется по всем устройствам 
    // $daliAcknoledgedAddresses = 0;
if ($daliDevicesAmount > 0)
{
    for ($address = 0; $address < 64; $address ++)
    {
        // Получаем массив всех регистров устройства $daliGatewayId для адреса $address с атрибутом ro
        $sql = System::$db->query("SELECT `id`, `alias` FROM `modbus_registers` 
                                    WHERE `slaver_id` = $daliGatewayId AND `access` = 'ro' AND `alias` LIKE 'dali%a$address'");
        $daliAddressRegistersArray = [];
        while ($daliAddressRegister = $sql->fetch(PDO::FETCH_OBJ))
            $daliAddressRegistersArray[$daliAddressRegister->alias] = (int)$daliAddressRegister->id;

        // Проверям есть ли на шине устройство с адресом $address
        $response = Modbus::sendModbus($daliAddressRegistersArray["dali_is_on_bus_a$address"], 'read');
        if (isset($response)) $daliDeviceType = $response;
        else $daliDeviceType = null;

        if (isset($daliDeviceType))
        {
            echo "Устройство А$address:" . PHP_EOL;
            // Проверим есть ли в бд запись об устройстве с адресом $address
            $isDeviceExistsQuery = System::$db->query(" SELECT *
                                                        FROM `dali_devices`
                                                        WHERE `address` =  $address
                                                        AND `dali_gateway` = $daliGatewayId");
            $isDeviceExists = $isDeviceExistsQuery->fetch(PDO::FETCH_OBJ);
            if (!$isDeviceExists)
            {
                echo "Новое устройство" . PHP_EOL;
                // Проверяем есть ли на адресе устройство. Если нет, то записываем его свойства в БД.
                // Если есть, то пропускаем устройство и переходим к следующему адресу.
                // Получаем данные устройства
                // Регистр 3003+A*5 - Статус устройства
                $daliStatus = Modbus::sendModbus($daliAddressRegistersArray["dali_device_status_a$address"], 'read');
                // bit 1 - неисправность устройства. 0 = ОК; 1 = неисправность
                $failure = Dali::nbit ($daliStatus, 1);
                // bit 2 - состояние устройства. 0 = off; 1 = on
                if (Dali::nbit ($daliStatus, 2) == 0) $status = "off";
                else $status = "on";
                
                // Регистр 3004+A*5 - Текущий уровень яркости
                $daliBrightness = Modbus::sendModbus($daliAddressRegistersArray["dali_get_brightness_a$address"], 'read');
                $daliBrightness = Dali::arcpowerTopercent($daliBrightness);

                // Регистр 3322+A*5 - Варианты управления цветом
                $daliCctVariants = Modbus::sendModbus($daliAddressRegistersArray["dali_cct_variants_a$address"], 'read');
                // bit 1 - управление цветовой температурой. 0 = не поддерживается; 1 = поддерживается
                $cctControl = Dali::nbit ($daliCctVariants, 1);

                echo "      Тип устройства: $daliDeviceType" . PHP_EOL;
                echo "      Устройство неисправно: $failure" . PHP_EOL;
                echo "      Статус устройства: $status" . PHP_EOL;
                echo "      Установленная яркость: $daliBrightness" . PHP_EOL;

                // Если устройство поддерживает управление цветовой температурой, то можем считать значение 
                if ($cctControl)
                {
                    $daliCctValue = Modbus::sendModbus($daliAddressRegistersArray["dali_get_temperature_a$address"], 'read');
                    echo "      Поддержка управления цветовой температурой: Да" . PHP_EOL;
                    echo "      Цветовая температура: $daliCctValue" . PHP_EOL;
                }
                else $daliCctValue = null;

                // Добавляем запись в БД.
                $placeholders = ":name,:type,:dali_gateway,:address,:failure,:brightness,:is_cct,:cct";
                $values = [
                    "name"          => "Устройство А$address", 
                    "type"          => $daliDeviceType,
                    "dali_gateway"  => $daliGatewayId,
                    "address"       => $address,
                    "failure"       => $failure,
                    "brightness"    => $daliBrightness,
                    "is_cct"        => $cctControl,
                    "cct"           => $daliCctValue,
                ];
                $columns = "name,type,dali_gateway,address,failure,brightness,is_cct,cct";
                $stmt = System::$db->prepare("INSERT INTO dali_devices ($columns) VALUES ($placeholders)");
                $stmt->execute($values);
            }
        }
    }
    echo "Все устройства найдены и добавлены" . PHP_EOL;
}

exit (0);