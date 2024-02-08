<?php

require_once '../include.php';

$daliGatewayId = $argv[1];

function nbit($number, $n) 
{
    return ($number >> $n) & 1;
}

// Получаем массив существующих адресов устройств
$sql = System::$db->query("SELECT `address` FROM `dali_devices` WHERE `dali_gateway` = $daliGatewayId ORDER BY `address`");
while ($existingDaliDevice = $sql->fetch(PDO::FETCH_OBJ)) $existingDaliDevicesArray[] = $existingDaliDevice->address;

// var_dump ($existingDaliDevicesArray);
// var_dump (count($existingDaliDevicesArray));

// Получаем регистр управления сборкой шины DALI
$sql = System::$db->query("SELECT `id` FROM `modbus_registers` WHERE `slaver_id` = $daliGatewayId AND `alias` = 'dali_assembling'");
$assemblingRegister = $sql->fetch(PDO::FETCH_OBJ)->id;

// Записываем команду запуска сборки шины
Modbus::putTaskIntoQueue($assemblingRegister, 'write', 0, 0x02);

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
while ((int)$registerValue === 18 && (time() - $start) < 60);
echo PHP_EOL;

// Завершаем непрерывное считывание значения регистра
Modbus::pollingCtl($assemblingRegister, false);

if ((int)$registerValue == 6) 
{
    echo "[ERROR] Расширение шины провалено" . PHP_EOL;
    exit ("Расширение шины провалено. Код ошибки: 0x06.");
}
if ((int)$registerValue == 0)
{
    echo "[OK] Расширение шины выполнено" . PHP_EOL;
    
    // Определяем количество устройств на шине
    $sql = System::$db->query("SELECT `id`
                                 FROM `modbus_registers`
                                WHERE `slaver_id` = $daliGatewayId
                                  AND `alias` = 'dali_devices_amount'");
    $daliDevicesAmountRegister = $sql->fetch(PDO::FETCH_OBJ)->id;

    $daliDevicesAmount = Modbus::getRegisterValue ($daliDevicesAmountRegister);
    echo "Найдено $daliDevicesAmount устройств" . PHP_EOL;

    // Теперь проверим на каких адресах расположены устройства.
    // Поиск выполняется по всем устройствам 
    // $daliAcknoledgedAddresses = 0;

    for ($address = 0; $address < 64; $address ++)
    {
        // Получаем массив всех регистров устройства $daliGatewayId для адреса $address с атрибутом ro
        $sql = System::$db->query("SELECT `id`, `alias` FROM `modbus_registers` 
                                    WHERE `slaver_id` = $daliGatewayId AND `access` = 'ro' AND `alias` LIKE 'dali%a$address'");
        $daliAddressRegistersArray = array();
        while ($daliAddressRegister = $sql->fetch(PDO::FETCH_OBJ))
            $daliAddressRegistersArray[$daliAddressRegister->alias] = (int)$daliAddressRegister->id;

        // Проверям есть ли на шине устройство с адресом $address
        $daliDeviceType = Modbus::getRegisterValue ($daliAddressRegistersArray["dali_is_on_bus_a$address"]);

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
                $daliStatus = Modbus::getRegisterValue ($daliAddressRegistersArray["dali_device_status_a$address"]);
                // bit 1 - неисправность устройства. 0 = ОК; 1 = неисправность
                $failure = nbit ($daliStatus,1);
                // bit 2 - состояние устройства. 0 = off; 1 = on
                if (nbit ($daliStatus,2) == 0) $status = "off";
                else $status = "on";
                
                // Регистр 3004+A*5 - Текущий уровень яркости
                $daliBrightness = 100;

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

                Dali::setBrightnessByAddress ($address, $daliGatewayId, $daliBrightness);  
            }
        }
    }
    echo "Все устройства найдены и добавлены" . PHP_EOL;
}

exit (0);