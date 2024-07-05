<?php

/**
 * Класс работы с устройствами на шине DALI.
 * В качестве DALI контроллера выступает Modbus-DALI шлюз компании EcoDim.
 */
class Dali extends Device
{
    private static $address;
    private static $daliGatewayId;
    private static $daliState;

    private $daliDevice = null;

    function __construct($idObject)
    {
        if (isset($idObject))
        {
            $sql = parent::$db->query(" SELECT `dali_devices`.`id_object`,
                                               `dali_devices`.`address`,
                                               `dali_devices`.`dali_gateway`,
                                               `dali_devices`.`brightness`,
                                               `objects`.`status`,
                                               `modbus_slavers`.`active`
                                        FROM `dali_devices`
                                        INNER JOIN `objects`
                                        ON `dali_devices`.`id_object` = `objects`.`id`
                                        INNER JOIN `modbus_slavers`
                                        ON `modbus_slavers`.`id` = `conditioners`.`modbus_slaver_id`
                                        WHERE `id_object` = $idObject");

            if($sql->rowCount() > 0) 
            {
                $this->daliDevice = $sql->fetch(PDO::FETCH_OBJ);
                if ($this->daliDevice->active != 1)
                {
                    echo "[Error] Modbus шлюз шины DALI (ID {$this->daliDevice->dali_gateway}) недоступен" . PHP_EOL;
                    System::addLog("Error", "Modbus шлюз шины DALI (ID {$this->daliDevice->dali_gateway}) недоступен", "port");
                    exit(1);
                }
                else
                {
                    $object = new Objects();
                    $object->select($idObject);
                }
            }
            else 
            {
                echo "[Error] DALI устройство (ID $idObject) не найдено" . PHP_EOL;
                exit(1);
            }
        }
        else
        {
            echo "[Error] Не определен ID устройства DALI" . PHP_EOL;
            exit(1);
        }
    }

    private static function nbit($number, $n) 
    {
        return ($number >> $n) & 1;
    }

    private static function percentToArcpower($percent) 
    {
        $val = 253*(log10($percent)+1)/3+1;
        return round($val);
    }

    private static function arcpowerTopercent($arcpower) 
    {
        $val = pow(10, (3*($arcpower-1)/253)-1);
        return round($val);
    }

    public function getBrightness()
    {
        // return $this->getBrightnessByAddress ();
        $sql = parent::$db->query(" SELECT `id`
                                    FROM `modbus_registers` 
                                    WHERE `slaver_id` = {$this->daliDevice->dali_gateway}
                                    AND `alias` LIKE 'dali_get_brightness_a{$this->daliDevice->address}'");
        $registerId = $sql->fetch(PDO::FETCH_OBJ)->id;
            
        $response = Modbus::modbusRtu($registerId, 'read');
        if ($response && !$response['error'])
        {
            $brightness = (int)$response['response'];
            $brightness = self::arcpowerTopercent($brightness);

            if ($brightness > 0)
            {
                parent::$db->query("UPDATE `dali_devices`
                                    SET `brightness` = $brightness
                                    WHERE `dali_gateway` = {$this->daliDevice->dali_gateway}
                                    AND `address` = {$this->daliDevice->address}");
            }
            else
            {
                $sql = parent::$db->query(" SELECT `brightness` FROM `dali_devices`
                                            WHERE `dali_gateway` = {$this->daliDevice->dali_gateway}
                                            AND `address` = {$this->daliDevice->address}");
                $brightness = $sql->fetch(PDO::FETCH_OBJ)->brightness;
            }

            return $brightness;
        }
        else return null;
    }


    public function getColorTemperature()
    {
        $sql = parent::$db->query(" SELECT `id`
                                    FROM `modbus_registers` 
                                    WHERE `slaver_id` = {$this->daliDevice->dali_gateway}
                                    AND `alias` LIKE 'dali_get_temperature_a{$this->daliDevice->address}'");
        $registerId = $sql->fetch(PDO::FETCH_OBJ)->id;

        $response = Modbus::modbusRtu($registerId, 'read');

        if ($response && !$response['error'])
        {
            $colorTemperature = (int)$response['response'];

            parent::$db->query("UPDATE `dali_devices`
                                SET `cct` = $colorTemperature
                                WHERE `dali_gateway` = {$this->daliDevice->dali_gateway}
                                AND `address` = {$this->daliDevice->address}");

            return $colorTemperature;
        }
        else return null;
    }

    public function getDeviceStatus()
    {
        $sql = parent::$db->query(" SELECT `id`
                                    FROM `modbus_registers` 
                                    WHERE `slaver_id` = {$this->daliDevice->dali_gateway}
                                    AND `alias` LIKE 'dali_device_status_a{$this->daliDevice->address}'");
        $registerId = $sql->fetch(PDO::FETCH_OBJ)->id;

        $response = Modbus::modbusRtu($registerId, 'read');

        if ($response && !$response['error'])
        {
            $status = (int)$response['response'];
            $statusArray = [];
            // bit 1 - неисправность устройства. 0 = ОК; 1 = неисправность
            $isFailure = self::nbit ($status,1);
            $statusArray["failure"] = $isFailure;
            // bit 2 - состояние устройства. 0 = off; 1 = on
            (self::nbit ($status,2) == 0) ? $state = "off" : $state = "on";
            $statusArray["state"] = $state;
            parent::$db->query("UPDATE `dali_devices`
                                SET `failure` = $isFailure
                                WHERE `dali_gateway` = {$this->daliDevice->dali_gateway}
                                AND `address` = {$this->daliDevice->address}");
            parent::$db->query("UPDATE `objects`
                                INNER JOIN `dali_devices`
                                ON `objects`.`id` = `dali_devices`.`id_object`
                                SET `objects`.`status` = '$state'
                                WHERE `dali_devices`.`address` = {$this->daliDevice->address}
                                AND `dali_devices`.`dali_gateway` = {$this->daliDevice->dali_gateway}");
            return $statusArray;
        }
        else return null;
    }

    public function setBrightness(int $brightness)
    {
        $sql = parent::$db->query(" SELECT `id`
                                    FROM `modbus_registers` 
                                    WHERE `slaver_id` = {$this->daliDevice->dali_gateway}
                                    AND `alias` LIKE 'dali_set_brightness_a{$this->daliDevice->address}'");
        $registerId = $sql->fetch(PDO::FETCH_OBJ)->id;
 
        $arcpower = self::percentToArcpower($brightness);
        

        if ($object->status == 'on')
        {
            $response = Modbus::modbusRtu($registerId, 'write', null, $brightness);
            if (!isset($response) || $response['error']) return false;
        }
        
        if ($brightness > 0) parent::$db->query("   UPDATE `dali_devices`
                                                    SET `brightness` =  $brightness
                                                    WHERE `dali_gateway` = {$this->daliDevice->dali_gateway}
                                                    AND `address` = {$this->daliDevice->address}");

        return true;
    }

    public function setColorTemperature(int $cct)
    {
        $sql = parent::$db->query("SELECT `id` FROM `modbus_registers` 
                                    WHERE `slaver_id` = {$this->daliDevice->dali_gateway}
                                    AND `alias` LIKE 'dali_set_temperature_a{$this->daliDevice->address}'");
        $registerId = $sql->fetch(PDO::FETCH_OBJ)->id;
        
        $response = Modbus::modbusRtu($registerId, 'write', null, $cct);
        if ($response && !$response['error'])
        {
            parent::$db->query("UPDATE `dali_devices`
                                SET `cct` =  $cct
                                WHERE `dali_gateway` = {$this->daliDevice->dali_gateway}
                                AND `address` = {$this->daliDevice->address}");
            return true;
        }
        else return false;
    }

    public function sendCmd(int $daliCmd)
    {
        $sql = parent::$db->query("SELECT `id` FROM `modbus_registers` 
                                    WHERE `slaver_id` = {$this->daliDevice->dali_gateway}
                                    AND `alias` LIKE 'dali_send_cmd_a{$this->daliDevice->address}'");
        $registerId = $sql->fetch(PDO::FETCH_OBJ)->id;
            
        $response = Modbus::modbusRtu($registerId, 'write', null, $daliCmd);
        if ($response && !$response['error']) return true;
        else return false;
    }

    public function daliOff (int $idObject)
    {
        $offCmd = $this->sendCmd(0);
        if ($offCmd)
        {
            $object->setStatus('off',true,false);
            return true;
        }
        else return false;
    }

    public function daliOn()
    {
        $sql = parent::$db->query(" SELECT `brightness` FROM `dali_devices` 
                                    WHERE `id_object` = {$this->daliDevice->id_object}");
        $brightness = $sql->fetch(PDO::FETCH_OBJ)->brightness;
        $object->setStatus('on',true,false);
        $onCmd = $this->setBrightness($brightness);
        if ($onCmd) return true;
        else
        {
            $object->setStatus('off',true,false);
            return false;
        }
    }

    public function daliSw()
    {
        $sql = parent::$db->query(" SELECT `status` FROM `objects` 
                                    WHERE `id` = {$this->daliDevice->id_object}");
        $status = $sql->fetch(PDO::FETCH_OBJ)->status;

        if ($status == "on") return $this->daliOff();
        else return $this->daliOn();
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