<?php

require_once '../include.php';

function nbit($number, $n) 
{
    return ($number >> $n) & 1;
}

$daliGatewayId = $argv[1];

$getRegisterQuery = System::$db->prepare("  SELECT `id`
                                            FROM `modbus_registers`
                                            WHERE `slaver_id` = $daliGatewayId
                                            AND `alias` = ?");

// Получаем регистр контроля изменений шины DALI
$getRegisterQuery->execute(["dali_bus_changes"]);
$changesAmountRegister = $getRegisterQuery->fetch(PDO::FETCH_OBJ)->id;

// Получаем массив адресов устройств на шине
$daliAddressesArray = [];
$sql = System::$db->query(" SELECT `address`
                            FROM `dali_devices`
                            WHERE `dali_gateway` = $daliGatewayId 
                            ORDER BY `address`");
while ($address = $sql->fetch(PDO::FETCH_OBJ)) $daliAddressesArray[] = $address->address;

// В зависимости от количества устройств на шине,
// получаем массив регистров для поиска флагов изменений
$devicesChangesRegisterArray = [];
$getRegisterQuery->execute(["dali_15_0_changes"]);
$devicesChangesRegisterArray[] = $getRegisterQuery->fetch(PDO::FETCH_OBJ)->id;
if (end($daliAddressesArray) >= 16) 
{
    $getRegisterQuery->execute(["dali_31_16_changes"]);
    $devicesChangesRegisterArray[] = $getRegisterQuery->fetch(PDO::FETCH_OBJ)->id;
}
if (end($daliAddressesArray) >= 32) 
{
    $getRegisterQuery->execute(["dali_47_32_changes"]);
    $devicesChangesRegisterArray[] = $getRegisterQuery->fetch(PDO::FETCH_OBJ)->id;
}
if (end($daliAddressesArray) >= 48) 
{
    $getRegisterQuery->execute(["dali_63_48_changes"]);
    $devicesChangesRegisterArray[] = $getRegisterQuery->fetch(PDO::FETCH_OBJ)->id;
}


// Обнуляем счетчик изменений шины DALI
Modbus::putTaskIntoQueue($changesAmountRegister, 'write', 0, 0);

// Запускаем непрерывный опрос регистра контроля изменений шины DALI
Modbus::pollingCtl($changesAmountRegister, true, 0);

while (true)
{
    usleep (500000);
    // Получаем текущее количество изменений на шине DALI
    $changesAmount = Modbus::getRegisterValueFromDB($changesAmountRegister);

    $changedAddressesArray = []; // Массив адресов с изменениями

    while ($changesAmount > 0)
    {
        echo "Текущее значение изменений: " . $changesAmount . PHP_EOL;

        foreach ($devicesChangesRegisterArray as $key => $registerId)
        {
            $flags = Modbus::getRegisterValue($registerId);
            foreach ($daliAddressesArray as $address)
            {
                echo "A" . $address+$key*16 . ": " . nbit($flags, $address+$key*16) . PHP_EOL;
                if (nbit($flags, $address-$key*16) == 1) 
                {
                    if (!in_array($address, $changedAddressesArray))
                        $changedAddressesArray[] = $address; // Добавляем адрес в массив
                }
            }
        }
        
        $currentChangesAmount = Modbus::getRegisterValueFromDB($changesAmountRegister);
        // var_dump($changesAmount, $currentChangesAmount);
        if ($changesAmount == $currentChangesAmount) 
        {
            Modbus::putTaskIntoQueue($changesAmountRegister, 'write', 0, 0);

            $changesAmount = 0;
        }
        else $changesAmount = $currentChangesAmount;
    }

    // Обрабатываем полученные адреса устройств
    // Получаем данные устройств
    if (!empty($changedAddressesArray))
    {
        foreach ($changedAddressesArray as $address)
        {
            // Статус
            $status = Dali::getStatusByAddress ($address, $daliGatewayId);
            // Яркость
            // Если 0, то не пишем в БД. Оставляем текущее значение, как последнее установленное.
            Dali::getBrightnessByAddress ($address, $daliGatewayId);
            // Определяем умеет ли устройство управлять цветовой температурой
            $sql = System::$db->query(" SELECT `is_cct`
                                        FROM `dali_devices`
                                        WHERE `dali_gateway` = $daliGatewayId
                                        AND `address` = $address");
            $isCctControl = $sql->fetch(PDO::FETCH_OBJ)->is_cct;
            // Цветовая температура
            if ($isCctControl) Dali::getColorTemperatureByAddress ($address, $daliGatewayId);
        }
    }
}