<?php

/**
 * Class Conditioner позволяет работать с кондиционерами, подключенными через modbus шлюзы 
 **/

class Conditioner extends Device
{
    private $ac = null; // id объекта кондиционера 

    function __construct($idObject)
    {
        if (isset($idObject))
        {
            $sql = parent::$db->query(" SELECT `objects`.`status` as 'state',
                                               `conditioners`.`id_object`,
                                               `conditioners`.`temp`,
                                               `conditioners`.`type`,
                                               `conditioners`.`mode`,
                                               `conditioners`.`fan`,
                                               `conditioners`.`vdir`,
                                               `conditioners`.`hdir`,
                                               `conditioners`.`modbus_slaver_id`,
                                               `modbus_slavers`.`active`      
                                        FROM `conditioners`
                                        INNER JOIN `objects`
                                        ON `conditioners`.`id_object` = `objects`.`id`
                                        INNER JOIN `modbus_slavers`
                                        ON `modbus_slavers`.`id` = `conditioners`.`modbus_slaver_id`
                                        WHERE `conditioners`.`id_object` = $idObject");

            if($sql->rowCount() > 0) 
            {
                $this->ac = $sql->fetch(PDO::FETCH_OBJ);
                if ($this->ac->active != 1)
                {
                    $modbusGw = $this->ac->modbus_slaver_id;
                    echo "[Error] Modbus шлюз кондиционера (ID $modbusGw) недоступен" . PHP_EOL;
                    System::addLog("Error", "Modbus шлюз кондиционера (ID $modbusGw) недоступен", "port");
                    exit;
                }
            }
            else 
            {
                echo "[Error] Кондиционер (ID $idObject) не найден" . PHP_EOL;
                // System::addLog("Error", "Попытка обратиться к кондиционеру, которого не существует (ID $idObject)", "port");
                exit;
            }
        }
        else
        {
            echo "[Error] Не определен ID кондиционера" . PHP_EOL;
            exit;
        }
    }

    public function setAcPower(string $state)
    {
        $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_power');

        if ($state == 'on') $cmd = 1;
        if ($state == 'off') $cmd = 0;
            
        if (isset($registerId))
        {
            $response = Modbus::modbusRtu($registerId, 'write', $cmd);
            if (isset($response))
            {
                $object = new Objects();
                $object->select($this->ac->id_object);
                $object->setStatus($state, true, false);
                return true;
            }
            else return false;
        }   
    }

    public function setAcTemperature(int $temperature)
    {
        $sql = parent::$db->query(" SELECT `conditioner_types`.`temperature`
                                    FROM `conditioner_types`
                                    INNER JOIN `conditioners`
                                    ON `conditioner_types`.`id` = `conditioners`.`type`
                                    WHERE `conditioners`.`modbus_slaver_id` = {$this->ac->modbus_slaver_id}
                                    AND `conditioners`.`id_object` = {$this->ac->id_object}");
        $acTemperatureRange = json_decode($sql->fetch(PDO::FETCH_OBJ)->temperature, true);

        if ($temperature >= $acTemperatureRange['min'] && $temperature <= $acTemperatureRange['max'])
        {
            $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_temp');
            if (isset($registerId)) 
            {
                $response = Modbus::modbusRtu($registerId, 'write', $temperature);
                if (isset($response))
                {
                    parent::$db->query("UPDATE `conditioners`
                                        SET `temp` = $temperature
                                        WHERE id_object = {$this->ac->id_object}");
                    return true;
                }
                else return false;
            }
        }
    }
    
    public function setAcMode(string $mode)
    {
        $sql = parent::$db->query(" SELECT `conditioner_types`.`mode`
                                    FROM `conditioner_types`
                                    INNER JOIN `conditioners`
                                    ON `conditioner_types`.`id` = `conditioners`.`type`
                                    WHERE `conditioners`.`modbus_slaver_id` = {$this->ac->modbus_slaver_id}
                                    AND `conditioners`.`id_object` = {$this->ac->id_object}");
        $acModes = json_decode($sql->fetch(PDO::FETCH_OBJ)->mode, true);

        if (array_key_exists($mode, $acModes))
        {
            $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_mode');
            if (isset($registerId)) 
            {
                $response = Modbus::modbusRtu($registerId, 'write', $acModes[$mode]);
                if (isset($response))
                {
                    parent::$db->query("UPDATE `conditioners`
                                        SET `mode` = '$mode'
                                        WHERE id_object = {$this->ac->id_object}");
                    return true;
                }
                else return false;
            }
        }
    }

    public function setAcFanSpeed(string $speed)
    {
        $sql = parent::$db->query(" SELECT `conditioner_types`.`fan`
                                    FROM `conditioner_types`
                                    INNER JOIN `conditioners`
                                    ON `conditioner_types`.`id` = `conditioners`.`type`
                                    WHERE `conditioners`.`modbus_slaver_id` = {$this->ac->modbus_slaver_id}
                                    AND `conditioners`.`id_object` = {$this->ac->id_object}");
        $acFanSpeeds = json_decode($sql->fetch(PDO::FETCH_OBJ)->fan, true);

        if (array_key_exists($speed, $acFanSpeeds))
        {
            $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_fan');
            if (isset($registerId)) 
            {
                $response = Modbus::modbusRtu($registerId, 'write', $acFanSpeeds[$speed]);
                if (isset($response))
                {
                    parent::$db->query("UPDATE `conditioners`
                                        SET `fan` = '$speed'
                                        WHERE id_object = {$this->ac->id_object}");
                    return true;
                }
                else return false;
            }
        }
    }

    public function setAcVDir(string $vDir)
    {
        $sql = parent::$db->query(" SELECT `conditioner_types`.`vdir`
                                    FROM `conditioner_types`
                                    INNER JOIN `conditioners`
                                    ON `conditioner_types`.`id` = `conditioners`.`type`
                                    WHERE `conditioners`.`modbus_slaver_id` = {$this->ac->modbus_slaver_id}
                                    AND `conditioners`.`id_object` = {$this->ac->id_object}");
        $queryResult = $sql->fetch(PDO::FETCH_OBJ);

        if(isset($queryResult->vdir))
        {
            $acVDirs = json_decode($queryResult->vdir, true);

            if (array_key_exists($vDir, $acVDirs))
            {
                $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_vdir');
                if (isset($registerId))
                {
                    $response = Modbus::modbusRtu($registerId, 'write', $acVDirs[$vDir]);
                    if (isset($response))
                    {
                        parent::$db->query("UPDATE `conditioners`
                                            SET `vdir` = '$vDir'
                                            WHERE id_object = {$this->ac->id_object}");
                        return true;
                    }
                    else return false;
                }
            }
        }
    }

    public function setAcHDir(string $hDir)
    {
        $sql = parent::$db->query(" SELECT `conditioner_types`.`hdir`
                                    FROM `conditioner_types`
                                    INNER JOIN `conditioners`
                                    ON `conditioner_types`.`id` = `conditioners`.`type`
                                    WHERE `conditioners`.`modbus_slaver_id` = {$this->ac->modbus_slaver_id}
                                    AND `conditioners`.`id_object` = {$this->ac->id_object}");
        $queryResult = $sql->fetch(PDO::FETCH_OBJ);

        if(isset($queryResult->hdir))
        {
            $acHDirs = json_decode($queryResult->hdir, true);

            if (array_key_exists($hDir, $acHDirs))
            {
                $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_hdir');
                if (isset($registerId))
                {
                    $response = Modbus::modbusRtu($registerId, 'write', $acHDirs[$hDir]);
                    if (isset($response))
                    {
                        parent::$db->query("UPDATE `conditioners`
                                            SET `hdir` = '$hDir'
                                            WHERE id_object = {$this->ac->id_object}");
                        return true;
                    }
                    else return false;
                }
            }
        }
    }
}

