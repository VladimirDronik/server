<?php

/**
 * Класс работы с устройствами на шине DALI.
 * В качестве DALI контроллера выступает Modbus-DALI шлюз компании EcoDim.
 */
class Dali extends Device
{
    private static $address;
    private static $daliGatewayId;

    public static function daliDeviceInit (int $idObject)
    {
        $sql = parent::$db->query("SELECT `address`, `dali_gateway`, `brightness`
                                     FROM `dali_devices`
                                    WHERE `id_object` = $idObject");

        $daliDevice = $sql->fetch(PDO::FETCH_OBJ);
        self::$address = $daliDevice->address;
        self::$daliGatewayId = $daliDevice->dali_gateway;
    }

    private static function nbit ($number, $n) 
    {
        return ($number >> $n) & 1;
    }

    private static function percentToArcpower ($percent) 
    {
        return (int)round(253*(log10($percent)+1)/3+1);
    }

    private static function arcpowerTopercent ($arcpower) 
    {
        return (int)round(pow(10, (3*($arcpower-1)/253)-1));
    }

    public static function getBrightness (int $idObject)
    {
        self::daliDeviceInit ($idObject);
        return self::getBrightnessByAddress (self::$address, self::$daliGatewayId);
    }

    public static function getBrightnessByAddress (int $address, int $daliGatewayId)
    {
        $sql = parent::$db->query(" SELECT `id` FROM `modbus_registers` 
                                    WHERE `slaver_id` = $daliGatewayId
                                    AND `alias` LIKE 'dali_get_brightness_a$address'");
        $registerId = $sql->fetch(PDO::FETCH_OBJ)->id;
            
        $brightness = (int)Modbus::getRegisterValue ($registerId, 50);
        $brightness = self::arcpowerTopercent($brightness);
        if ($brightness > 0)
        {
            parent::$db->query("UPDATE `dali_devices`
                                SET `brightness` = $brightness
                                WHERE `dali_gateway` = $daliGatewayId
                                AND `address` = $address");
        }
        else
        {
            $sql = parent::$db->query(" SELECT `brightness` FROM `dali_devices`
                                        WHERE `dali_gateway` = $daliGatewayId
                                        AND `address` = $address");
            $brightness = $sql->fetch(PDO::FETCH_OBJ)->brightness;
        }

        return $brightness;
    }

    public static function getColorTemperature (int $idObject)
    {
        self::daliDeviceInit ($idObject);
        return self::getColorTemperatureByAddress (self::$address, self::$daliGatewayId);
    }

    public static function getColorTemperatureByAddress (int $address, int $daliGatewayId)
    {
        $sql = parent::$db->query("SELECT `id` FROM `modbus_registers` 
                                    WHERE `slaver_id` = $daliGatewayId
                                    AND `alias` LIKE 'dali_get_temperature_a$address'");
        $registerId = $sql->fetch(PDO::FETCH_OBJ)->id;
            
        $colorTemperature = (int)Modbus::getRegisterValue ($registerId, 50);

        $sql = "UPDATE `dali_devices`
                SET `cct` = ?
                WHERE `dali_gateway` = $daliGatewayId
                AND `address` = $address";
        $stmt = parent::$db->prepare($sql);
        $stmt->execute([$colorTemperature]);

        return $colorTemperature;
    }

    public static function getDeviceStatus (int $idObject)
    {
        self::daliDeviceInit ($idObject);
        return self::getStatusByAddress (self::$address, self::$daliGatewayId);
    }

    public static function getStatusByAddress (int $address, int $daliGatewayId)
    {
        $sql = parent::$db->query("SELECT `id` FROM `modbus_registers` 
                                    WHERE `slaver_id` = $daliGatewayId
                                    AND `alias` LIKE 'dali_device_status_a$address'");
        $registerId = $sql->fetch(PDO::FETCH_OBJ)->id;
            
        $status = (int)Modbus::getRegisterValue ($registerId, 50);
        $statusArray = [];
        // bit 1 - неисправность устройства. 0 = ОК; 1 = неисправность
        $isFailure = self::nbit ($status,1);
        $statusArray["failure"] = $isFailure;
        // bit 2 - состояние устройства. 0 = off; 1 = on
        (self::nbit ($status,2) == 0) ? $state = "off" : $state = "on";
        $statusArray["state"] = $state;
        parent::$db->query("UPDATE `dali_devices`
                            SET `failure` = $isFailure
                            WHERE `dali_gateway` = $daliGatewayId
                            AND `address` = $address");
        // $stmt = parent::$db->prepare($sql);
        parent::$db->query("UPDATE `objects`
                            INNER JOIN `dali_devices` ON `objects`.`id` = `dali_devices`.`id_object`
                            SET `objects`.`status` = '$state'
                            WHERE `dali_devices`.`address` = $address
                            AND `dali_devices`.`dali_gateway` = $daliGatewayId");
        return $statusArray;
    }

    public static function setBrightness (int $idObject, int $brightness)
    {
        self::daliDeviceInit ($idObject);
        self::setBrightnessByAddress (self::$address, self::$daliGatewayId, $brightness);
    }

    public static function setBrightnessByAddress (int $address, int $daliGatewayId, int $brightness)
    {
        $sql = parent::$db->query("SELECT `id` FROM `modbus_registers` 
                                    WHERE `slaver_id` = $daliGatewayId
                                    AND `alias` LIKE 'dali_set_brightness_a$address'");
        $registerId = $sql->fetch(PDO::FETCH_OBJ)->id;
        if ($brightness > 100) $brightness = 100;
        $arcpower = self::percentToArcpower($brightness);
        Modbus::putTaskIntoQueue($registerId, 'write', 5, $arcpower);
        if ($brightness > 0)
            parent::$db->query("UPDATE `dali_devices`
                                SET `brightness` =  $brightness
                                WHERE `dali_gateway` = $daliGatewayId
                                AND `address` = $address");
    }

    public static function setColorTemperature (int $idObject, int $cct)
    {
        self::daliDeviceInit ($idObject);
        self::setColorTemperatureByAddress (self::$address, self::$daliGatewayId, $cct);
    }

    public static function setColorTemperatureByAddress (int $address, int $daliGatewayId, int $cct)
    {
        $sql = parent::$db->query("SELECT `id` FROM `modbus_registers` 
                                    WHERE `slaver_id` = $daliGatewayId
                                    AND `alias` LIKE 'dali_set_temperature_a$address'");
        $registerId = $sql->fetch(PDO::FETCH_OBJ)->id;
        Modbus::putTaskIntoQueue($registerId, 'write', 5, $cct);
        parent::$db->query("UPDATE `dali_devices`
                            SET `cct` =  $cct
                            WHERE `dali_gateway` = $daliGatewayId
                            AND `address` = $address");
    }

    public static function sendCmd (int $idObject, int $daliCmd)
    {
        self::daliDeviceInit ($idObject);
        self::sendCmdByAddress (self::$address, self::$daliGatewayId, $daliCmd);
    }

    public static function sendCmdByAddress (int $address, int $daliGatewayId, int $daliCmd)
    {
        $sql = parent::$db->query("SELECT `id` FROM `modbus_registers` 
                                    WHERE `slaver_id` = $daliGatewayId
                                    AND `alias` LIKE 'dali_send_cmd_a$address'");
        $registerId = $sql->fetch(PDO::FETCH_OBJ)->id;
            
        Modbus::putTaskIntoQueue($registerId, 'write', 5, $daliCmd);
    }

    public static function daliOff (int $idObject)
    {
        self::sendCmd ($idObject, 0);
        parent::$db->query("UPDATE `objects`
                            SET `status` = 'off'
                            WHERE `id` = $idObject");
    }

    public static function daliOn (int $idObject)
    {
        $sql = parent::$db->query(" SELECT `brightness`
                                    FROM `dali_devices` 
                                    WHERE `id_object` = $idObject");
        $brightness = $sql->fetch(PDO::FETCH_OBJ)->brightness;

        self::setBrightness ($idObject, $brightness);

        parent::$db->query("UPDATE `objects`
                            SET `status` = 'on'
                            WHERE `id` = $idObject");
    }

    public static function daliSw (int $idObject)
    {
        $sql = parent::$db->query(" SELECT `status`
                                    FROM `objects` 
                                    WHERE `id` = $idObject");
        $status = $sql->fetch(PDO::FETCH_OBJ)->status;

        if ($status == "on") self::daliOff ($idObject);
        else self::daliOn ($idObject);
    }

    /**
     * Получение списка DALI шин из БД
     */
    public static function getDaliBuses()
    {
        $sql = parent::$db->query(" SELECT `modbus_slavers`.`id` AS 'dali_gateway'
                                    FROM `modbus_slavers`
                                    INNER JOIN `modbus_slavers_types` ON `modbus_slavers_types`.`id` = `modbus_slavers`.`type`
                                    WHERE `modbus_slavers_types`.`type` = 'ecodim-dali-gw2'");
        
        $daliBusesArray = [];
        while ($daliControllerId = $sql->fetch(PDO::FETCH_OBJ))
            $daliBusesArray[] = (int)$daliControllerId->dali_gateway;
        
        return $daliBusesArray;  
    }
}