<?php

require_once '../include.php';

$daliGatewayId = $argv[1];

function nbit($number, $n)
{
    return ($number >> $n) & 1;
}

// Получаем регистр управления сборкой шины DALI
$sql = System::$db->query(" SELECT `id`
                            FROM `modbus_registers`
                            WHERE `slaver_id` = $daliGatewayId
                            AND `alias` = 'dali_bus_assembling'");
$assemblingRegister = $sql->fetch(PDO::FETCH_OBJ)->id;

// Записываем команду запуска сборки шины
$response = Modbus::modbusRtu($assemblingRegister, 'write', null, 0x01);
if (!isset($response) || $response['error']) exit(1);

// Ждем окончания процесса сборки шины
echo "Выполняется поиск устройств на шине";
while ($registerValue = (int)Modbus::modbusRtu($assemblingRegister, 'read')['response'] == 0x11) echo ".";
if ((int)$registerValue == 0x06)
{
    echo "[FAIL]" . PHP_EOL;
    exit (1);
}
if ((int)$registerValue == 0) echo "[OK]" . PHP_EOL;
   
// Определяем количество устройств на шине
$sql = System::$db->query("SELECT `id`
                                FROM `modbus_registers`
                            WHERE `slaver_id` = $daliGatewayId
                                AND `alias` = 'dali_devices_quantity'");
$daliDevicesAmountRegister = $sql->fetch(PDO::FETCH_OBJ)->id;
$daliDevicesAmount = Modbus::modbusRtu($daliDevicesAmountRegister, 'read');
echo "Найдено устройств:   {$daliDevicesAmount['response']}" . PHP_EOL;
    
if ($daliDevicesAmount['response'] > 0)
{
    // Удаляем связанные с DALI устройствами объекты. Устройства и методы должны удаляться каскадно.
    
    $sql = System::$db->query("SELECT `id_object` FROM `dali_devices` WHERE `dali_gateway` = $daliGatewayId");
    $daliObjectsIdsArray = [];
    while ($daliObjectsId = $sql->fetch(PDO::FETCH_OBJ))
        $daliObjectsIdsArray[] = (int)$daliObjectsId->id_object;
    foreach ($daliObjectsIdsArray as $objectId)
        System::$db->query("DELETE FROM `objects` WHERE `id` = $objectId");
    $sql = System::$db->query("DELETE FROM `dali_devices` WHERE `dali_gateway` = $daliGatewayId");

    // Теперь проверим на каких адресах расположены устройства.
    // Поиск можно остановить, когда все будут определены адреса для всех найденных устройств.
    // Для этого добавляем 1 к $daliAcknoledgedAddresses, когда устройство на адресе обнаружено.
    $daliAcknoledgedAddresses = 0;

    for ($address = 0; $address < 64; $address ++)
    {
        // Получаем массив всех регистров устройства $daliGatewayId для адреса $address с атрибутом ro
        $sql = System::$db->query("SELECT `id`, `alias` FROM `modbus_registers` 
                                    WHERE `slaver_id` = $daliGatewayId AND `access` = 'ro' AND `alias` LIKE 'dali%a$address'");
        $daliAddressRegistersArray = [];
        while ($daliAddressRegister = $sql->fetch(PDO::FETCH_OBJ))
            $daliAddressRegistersArray[$daliAddressRegister->alias] = (int)$daliAddressRegister->id;

        // Проверяем есть ли на шине устройство с адресом $address
        $response = Modbus::modbusRtu($daliAddressRegistersArray["dali_is_on_bus_a$address"], 'read');

        if (isset($response['response']) && !$response['error']) $daliDeviceType = $response['response'];
        else $daliDeviceType = null;

        if (isset($daliDeviceType))
        {
            $daliAcknoledgedAddresses++;
            echo "Устройство А$address:" . PHP_EOL;
            // Получаем данные устройства
            // Регистр 3003+A*5 - Статус устройства
            $daliStatus = Modbus::modbusRtu($daliAddressRegistersArray["dali_device_status_a$address"], 'read')['response'];
            // bit 1 - неисправность устройства. 0 = ОК; 1 = неисправность
            $failure = nbit($daliStatus,1);
            // bit 2 - состояние устройства. 0 = off; 1 = on
            if (nbit($daliStatus,2) == 0) $status = "off";
            else $status = "on";
            
            // Регистр 3004+A*5 - Текущий уровень яркости
            $daliBrightness = Modbus::modbusRtu($daliAddressRegistersArray["dali_get_brightness_a$address"], 'read')['response'];
            $daliBrightness = Dali::arcpowerTopercent($daliBrightness);

            // Регистр 3322+A*5 - Варианты управления цветом
            $daliCctVariants = Modbus::modbusRtu($daliAddressRegistersArray["dali_cct_variants_a$address"], 'read')['response'];
            // bit 1 - управление цветовой температурой. 0 = не поддерживается; 1 = поддерживается
            $cctControl = nbit($daliCctVariants,1);

            echo "      Тип устройства: $daliDeviceType" . PHP_EOL;
            echo "      Устройство неисправно: $failure" . PHP_EOL;
            echo "      Статус устройства: $status" . PHP_EOL;
            echo "      Установленная яркость: $daliBrightness" . PHP_EOL;
            
            // Если устройство поддерживает управление цветовой температурой, то можем считать значение 
            if ($cctControl)
            {
                $daliCctValue = Modbus::modbusRtu($daliAddressRegistersArray["dali_get_temperature_a$address"], 'read')['response'];
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

            // $dali = new Dali();
            // $dali->setBrightness($daliBrightness);
            // Dali::sendCmdByAddress ($address, $daliGatewayId, 0);
        }
        
        // Если для всех найденых устройсв определены адреса выходим из цикла. Нет смысла продолжать опрос.
        if ($daliAcknoledgedAddresses == $daliDevicesAmount['response']) break;
    }
    echo "Все устройства найдены и добавлены" . PHP_EOL;
}

exit (0);