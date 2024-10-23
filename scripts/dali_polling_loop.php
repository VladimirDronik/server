<?php

require_once '../include.php';

$daliGatewayId = $argv[1];

$getRegisterQuery = System::$db->prepare("  SELECT `id`
                                            FROM `modbus_registers`
                                            WHERE `slaver_id` = $daliGatewayId
                                            AND `alias` = ?");

// Получаем регистр контроля изменений шины DALI
$getRegisterQuery->execute(["dali_bus_changes"]);
$changesAmountRegister = $getRegisterQuery->fetch(PDO::FETCH_OBJ)->id;

// Получаем массив регистров для поиска флагов изменений
$getRegisterQuery->execute(["dali_groups_changes"]);
$groupChangesRegister = $getRegisterQuery->fetch(PDO::FETCH_OBJ)->id;

$devicesChangesRegisters = [];
$getRegisterQuery->execute(["dali_15_0_changes"]);
$devicesChangesRegisters[] = $getRegisterQuery->fetch(PDO::FETCH_OBJ)->id;
$getRegisterQuery->execute(["dali_31_16_changes"]);
$devicesChangesRegisters[] = $getRegisterQuery->fetch(PDO::FETCH_OBJ)->id;
$getRegisterQuery->execute(["dali_47_32_changes"]);
$devicesChangesRegisters[] = $getRegisterQuery->fetch(PDO::FETCH_OBJ)->id;
$getRegisterQuery->execute(["dali_63_48_changes"]);
$devicesChangesRegisters[] = $getRegisterQuery->fetch(PDO::FETCH_OBJ)->id;

while (true)
{
    // Получаем текущее количество изменений на шине DALI
    $changesAmount = Modbus::sendModbus($changesAmountRegister, 'read', null, 150);
    // if (isset($changesRequest)) $changesAmount = (int)$changesRequest;
    // else $changesAmount = null;
sleep(1);
    if (isset($changesAmount) && $changesAmount > 0)
    {
        echo " [ Изменений : $changesAmount ] " . PHP_EOL;

        $groupFlags = Modbus::sendModbus($groupChangesRegister, 'read');
        var_dump( $groupFlags);
        if (isset($groupFlags) && $groupFlags != 0)
        {
            // echo decbin($groupFlags) . PHP_EOL;
            for ($bit = 0; $bit<16; $bit++)
            {
                if ($isDeviceChanges = Dali::nbit($groupFlags, $bit))
                {
                    echo " [ Группа G$bit ] ->";
                }
            }
        }

            foreach ($devicesChangesRegisters as $key => $registerId)
            {
                $flags = Modbus::sendModbus($registerId, 'read');
                var_dump($flags);
                if (isset($flags) && $flags != 0)
                {
                    
                    for ($bit = 0; $bit<16; $bit++)
                    {
                        if ($isDeviceChanges = Dali::nbit($flags, $bit))
                        {
                            $address = $bit+$key*16;
                            echo " [ Адрес A$address ] ->";
    
                            $sql = System::$db->query(" SELECT `dali_devices`.`id_object`
                                                        FROM `dali_devices`
                                                        WHERE `dali_gateway` = $daliGatewayId
                                                        AND `address` = $address");
                            if($sql->rowCount() > 0) 
                            {
                                $dali = new Dali($sql->fetch(PDO::FETCH_OBJ)->id_object);
                                if (isset($dali))
                                {
                                    $status = $dali->getDeviceStatus();
                                    var_dump($status);
                                    $brightness = $dali->getBrightnessFromDevice();
                                    $sql = System::$db->query(" SELECT `is_cct`
                                                                FROM `dali_devices`
                                                                WHERE `id_object` = {$dali->daliDevice->id_object}");
                                    if ($sql->fetch(PDO::FETCH_OBJ)->is_cct)
                                        $cct = $dali->getColorTemperatureFromDevice();
                                    echo " [ OK ] ->";
                                }
                                else echo " [ FAIL ] ->";
                            }
                            else echo " [ DEVICE ID NOT FOUND ] ->";
                        }
                    }
                }
            }

        $changesRequest = Modbus::sendModbus($changesAmountRegister, 'read', null, 150);
        if (isset($changesRequest)) $changesAmountAck = (int)$changesRequest;
        else $changesAmountAck = null;

        if (isset($changesAmountAck))
        {
            $isCounterReset = Modbus::sendModbus($changesAmountRegister, 'write', 0);
            if (isset($isCounterReset)) echo " [ Counter reset ]" . PHP_EOL;
        }
    }
}