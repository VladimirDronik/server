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
    $changesRequest = Modbus::modbusRtu($changesAmountRegister, 'read', 150);
    if (isset($changesRequest) && !$changesRequest['error']) $changesAmount = (int)$changesRequest['response'];
    else $changesAmount = null;

    if (isset($changesAmount) && $changesAmount > 0)
    {
        echo " [ Изменений : $changesAmount ] ";
            foreach ($devicesChangesRegisters as $key => $registerId)
            {
                $flagsRequest = Modbus::modbusRtu($registerId, 'read');
                if (isset($flagsRequest) && !$flagsRequest['error'] && $flagsRequest['response'] != 0)
                    $flags = $flagsRequest['response'];
                else $flags = null;
                if (isset($flags))
                {
                    for ($bit = 0; $bit<16; $bit++)
                    {
                        if ($isDeviceChanges = Dali::nbit($flags, $bit))
                        {
                            $address = $bit+$key*16;
                            echo " [ Адрес A$address ] :";
    
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
                                    $brightness = $dali->getBrightness();
                                    $sql = System::$db->query(" SELECT `is_cct`
                                                                FROM `dali_devices`
                                                                WHERE `dali_gateway` = $daliGatewayId
                                                                AND `address` = $address");
                                    if ($sql->fetch(PDO::FETCH_OBJ)->is_cct) $cct = $dali->getColorTemperature();
                                    echo " [ OK ]";
                                }
                                else echo " [ FAIL ] ";
                            }
                            else echo " [ DEVICE ID NOT FOUND ] ";
                        }
                    }
                }
            }

        $changesRequest = Modbus::modbusRtu($changesAmountRegister, 'read', 150);
        if (isset($changesRequest) && !$changesRequest['error']) $changesAmountAck = (int)$changesRequest['response'];
        else $changesAmountAck = null;

        if (isset($changesAmountAck))
        {
            $isCounterReset = Modbus::modbusRtu($changesAmountRegister, 'write', null, 0);
            if (isset($isCounterReset) && !$isCounterReset['error']) echo " [ Counter reset ] " . PHP_EOL;
        }
    }
}