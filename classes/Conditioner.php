<?php

/**
 * Class Conditioner позволяет работать с кондиционерами, подключенными через modbus шлюзы 
 **/

class Conditioner extends Device
{
    private $ac = null; // id объекта кондиционера 

    function __construct ($idObject)
    {
        if ($idObject)
        {
            $sql = parent::$db->query(" SELECT `objects`.`status` as 'state',
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

            if ($this->ac = $sql->fetch(PDO::FETCH_OBJ)) $this->ac->id_object = $idObject;
        }

        if ($this->ac->active != 1)
        {
            $modbusGw = $this->ac->modbus_slaver_id;
            echo "[Error] Modbus шлюз кондиционера недоступен" . PHP_EOL;
            System::addLog("Error", "Modbus шлюз кондиционера (ID $modbusGw) недоступен", "port");
            return false;
        }
    }

    public function setAcPower (string $state)
    {
        $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_power');

        if ($state == 'on') $cmd = 1;
        if ($state == 'off') $cmd = 0;
            
        if (isset($registerId)) Modbus::putTaskIntoQueue($registerId, 'write', 5, $cmd);

        $object = new Objects();
        $object->select($this->ac->id_object);
        $object->setStatus($state, true, false);   
    }

    public function setAcTemperature (int $temperature)
    {
        $sql = parent::$db->query(" SELECT `conditioner_types`.`temperature`
                                    FROM `conditioner_types`
                                    INNER JOIN `conditioners`
                                    ON `conditioner_types`.`id` = `conditioners`.`type`
                                    WHERE `conditioners`.`modbus_slaver_id` = " . $this->ac->modbus_slaver_id . "
                                    AND `conditioners`.`id_object` = " . $this->ac->id_object);
        $acTemperatureRange = json_decode($sql->fetch(PDO::FETCH_OBJ)->temperature, true);

        if ($temperature >= $acTemperatureRange['min'] && $temperature <= $acTemperatureRange['max'])
        {
            $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_temp');
            if (isset($registerId)) Modbus::putTaskIntoQueue($registerId, 'write', 5, $temperature);
                
            parent::$db->query("UPDATE `conditioners`
                                SET `temp` = $temperature
                                WHERE id_object =" . $this->ac->id_object);
        }
    }
    
    public function setAcMode (string $mode)
    {
        $sql = parent::$db->query(" SELECT `conditioner_types`.`mode`
                                    FROM `conditioner_types`
                                    INNER JOIN `conditioners`
                                    ON `conditioner_types`.`id` = `conditioners`.`type`
                                    WHERE `conditioners`.`modbus_slaver_id` = " . $this->ac->modbus_slaver_id . "
                                    AND `conditioners`.`id_object` = " . $this->ac->id_object);
        $acModes = json_decode($sql->fetch(PDO::FETCH_OBJ)->mode, true);

        if (array_key_exists($mode, $acModes))
        {
            $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_mode');
            if (isset($registerId)) Modbus::putTaskIntoQueue($registerId, 'write', 5, $acModes[$mode]);

            parent::$db->query("UPDATE `conditioners`
                                SET `mode` = '$mode'
                                WHERE id_object =" . $this->ac->id_object);
        }
    }

    public function setAcFanSpeed (string $speed)
    {
        $sql = parent::$db->query(" SELECT `conditioner_types`.`fan`
                                    FROM `conditioner_types`
                                    INNER JOIN `conditioners`
                                    ON `conditioner_types`.`id` = `conditioners`.`type`
                                    WHERE `conditioners`.`modbus_slaver_id` = " . $this->ac->modbus_slaver_id . "
                                    AND `conditioners`.`id_object` = " . $this->ac->id_object);
        $acFanSpeeds = json_decode($sql->fetch(PDO::FETCH_OBJ)->fan, true);

        if (array_key_exists($speed, $acFanSpeeds))
        {
            $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_fan');
            if (isset($registerId)) Modbus::putTaskIntoQueue($registerId, 'write', 5, $acFanSpeeds[$speed]);

            parent::$db->query("UPDATE `conditioners`
                                SET `fan` = '$speed'
                                WHERE id_object =" . $this->ac->id_object);
        }
    }

    public function setAcVDir (string $vDir)
    {
        $sql = parent::$db->query(" SELECT `conditioner_types`.`vdir`
                                    FROM `conditioner_types`
                                    INNER JOIN `conditioners`
                                    ON `conditioner_types`.`id` = `conditioners`.`type`
                                    WHERE `conditioners`.`modbus_slaver_id` = " . $this->ac->modbus_slaver_id . "
                                    AND `conditioners`.`id_object` = " . $this->ac->id_object);
        $queryResult = $sql->fetch(PDO::FETCH_OBJ);

        if(isset($queryResult->vdir))
        {
            $acVDirs = json_decode($queryResult->vdir, true);

            if (array_key_exists($vDir, $acVDirs))
            {
                $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_vdir');
                if (isset($registerId)) Modbus::putTaskIntoQueue($registerId, 'write', 5, $acVDirs[$vDir]);

                parent::$db->query("UPDATE `conditioners`
                                    SET `vdir` = '$vDir'
                                    WHERE id_object =" . $this->ac->id_object);
            }
        }
    }

    public function setAcHDir (string $hDir)
    {
        $sql = parent::$db->query(" SELECT `conditioner_types`.`hdir`
                                    FROM `conditioner_types`
                                    INNER JOIN `conditioners`
                                    ON `conditioner_types`.`id` = `conditioners`.`type`
                                    WHERE `conditioners`.`modbus_slaver_id` = " . $this->ac->modbus_slaver_id . "
                                    AND `conditioners`.`id_object` = " . $this->ac->id_object);
        $queryResult = $sql->fetch(PDO::FETCH_OBJ);

        if(isset($queryResult->hdir))
        {
            $acHDirs = json_decode($queryResult->hdir, true);

            if (array_key_exists($hDir, $acHDirs))
            {
                $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_hdir');
                if (isset($registerId)) Modbus::putTaskIntoQueue($registerId, 'write', 5, $acHDirs[$hDir]);

                parent::$db->query("UPDATE `conditioners`
                                    SET `hdir` = '$hDir'
                                    WHERE id_object =" . $this->ac->id_object);
            }
        }
    }

    
    //
    public function setValue($temperature, $state, $oper, $fan)
    {

        $object = new Objects();
        $object->select(self::$idObject);


        if ($state == 'ON' ) {
            $object->setStatus('ON', true, false);
            $query = 'SELECT `code`, conditioners.wb_mir AS modbus_addr, devices.ip_address AS ip_addr FROM `conditioner_codes` INNER JOIN `conditioner_models` ON conditioner_codes.kind = conditioner_models.kind INNER JOIN `conditioners` ON conditioners.model = conditioner_models.id  
                INNER JOIN devices ON devices.id = conditioners.device_id
                WHERE conditioner_codes.temperature = '.$temperature.' AND conditioner_codes.operationMode = "'.$oper.'" AND conditioner_codes.fanMode = "'.$fan.'" AND conditioners.id_object = '.self::$idObject;
         } else {
            $object->setStatus('OFF', true, false);
            $query = 'SELECT `code`, conditioners.wb_mir AS modbus_addr, devices.ip_address AS ip_addr FROM `conditioner_codes` INNER JOIN `conditioner_models` ON conditioner_codes.kind = conditioner_models.kind INNER JOIN `conditioners` ON conditioners.model = conditioner_models.id  
                INNER JOIN devices ON devices.id = conditioners.device_id
                WHERE conditioner_codes.status = "'.$state.'" AND conditioners.id_object = '.self::$idObject;

         }

        //Ищем в таблице нужный код для команды
        $sql = parent::$db->query($query);


        $conditioner = $sql->fetch(PDO::FETCH_OBJ);

        //Заносим текущее состояние кондиционера в таблицу
            parent::$db->query("UPDATE conditioners SET 
                                `temp` = $temperature, `state` = \"$state\", `operation` = \"$oper\", `fan` = \"$fan\"   
                                WHERE id_object =".self::$idObject);


        //Отправляем команду скрипту кондиционера
        exec('rs_control ir_raw -d wb-mir --ip ' .$conditioner->ip_addr. ' -u ' .$conditioner->modbus_addr. ' --ir_json \'{"signal": [' .$conditioner->code. ']}\'');
    }
}

