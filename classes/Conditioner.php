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
                                               `conditioners`.`mode`,
                                               `conditioners`.`fan`,
                                               `conditioners`.`vdir`,
                                               `conditioners`.`hdir`,
                                               `conditioners`.`modbus_slaver_id`,
                                               `modbus_slavers`.`active`,
                                               `conditioner_types`.`temperature` AS 'temps',
                                               `conditioner_types`.`mode` AS 'modes',
                                               `conditioner_types`.`fan` AS 'fans',
                                               `conditioner_types`.`vdir` AS 'vdirs',
                                               `conditioner_types`.`hdir` AS 'hdirs'
                                        FROM `conditioners`
                                        INNER JOIN `objects`
                                        ON `conditioners`.`id_object` = `objects`.`id`
                                        INNER JOIN `modbus_slavers`
                                        ON `modbus_slavers`.`id` = `conditioners`.`modbus_slaver_id`
                                        INNER JOIN `conditioner_types`
                                        ON `conditioners`.`type` = `conditioner_types`.`id`
                                        WHERE `conditioners`.`id_object` = $idObject");

            if($sql->rowCount() > 0) 
            {
                $this->ac = $sql->fetch(PDO::FETCH_OBJ);
                // if ($this->ac->active != 1)
                // {
                    // $modbusGw = $this->ac->modbus_slaver_id;
                    // echo "[Error] Modbus шлюз кондиционера (ID $modbusGw) недоступен" . PHP_EOL;
                    // System::addLog("Error", "Modbus шлюз кондиционера (ID $modbusGw) недоступен", "port");
                    // exit;
                // }
            }
            else 
            {
                echo "[Error] Кондиционер (ID $idObject) не найден" . PHP_EOL;
                // System::addLog("Error", "Попытка обратиться к кондиционеру, которого не существует (ID $idObject)", "port");
                // exit;
            }
        }
        else
        {
            echo "[Error] Не определен ID кондиционера" . PHP_EOL;
            // exit;
        }
    }

    public function setAcPower(string $state)
    {
        $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_power');

        if ($state == 'on') $cmd = 1;
        if ($state == 'off') $cmd = 0;
            
        if (isset($registerId))
        {
            $response = Modbus::sendModbus($registerId, 'write', $cmd);
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
        if(isset($this->ac->temps)) {
            $acTemperatureRange = json_decode($this->ac->temps, true);

            if ($temperature >= $acTemperatureRange['min'] && $temperature <= $acTemperatureRange['max'])
            {
                $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_temp');
                if (isset($registerId)) 
                {
                    $response = Modbus::sendModbus($registerId, 'write', $temperature);
                    if (isset($response))
                    {
                        parent::$db->query("UPDATE `conditioners`
                                            SET `temp` = $temperature
                                            WHERE id_object = {$this->ac->id_object}");
                        $aliceCapabilities = [
                            "type" => "devices.capabilities.range",
                            "state" => [
                                "instance" => "temperature",
                                "value" => $temperature
                            ]
                        ];
                        Device::aliceCallbackState($this->ac->id_object, $aliceCapabilities, null);
                        return true;
                    }
                    else return false;
                }
            }
        }
    }
    
    public function setAcMode(string $mode)
    {
        if(isset($this->ac->modes)) {
            $acModes = json_decode($this->ac->modes, true);
            if (array_key_exists($mode, $acModes))
            {
                $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_mode');
                if (isset($registerId)) 
                {
                    $response = Modbus::sendModbus($registerId, 'write', $acModes[$mode]);
                    if (isset($response))
                    {
                        parent::$db->query("UPDATE `conditioners`
                                            SET `mode` = '$mode'
                                            WHERE id_object = {$this->ac->id_object}");
                        $aliceCapabilities = [
                            "type" => "devices.capabilities.mode",
                            "state" => [
                                "instance" => "thermostat",
                                "value" => Device::ALICE_AC_MODES_MAPPING[$mode]
                            ]
                        ];
                        Device::aliceCallbackState($this->ac->id_object, $aliceCapabilities, null);
                        return true;
                    }
                    else return false;
                }
            }
        }
    }

    public function setAcFanSpeed(string $speed)
    {
        if(isset($this->ac->fans)) {
            $acFanSpeeds = json_decode($this->ac->fans, true);
            if (array_key_exists($speed, $acFanSpeeds))
            {
                $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_fan');
                if (isset($registerId)) 
                {
                    $response = Modbus::sendModbus($registerId, 'write', $acFanSpeeds[$speed]);
                    if (isset($response))
                    {
                        parent::$db->query("UPDATE `conditioners`
                                            SET `fan` = '$speed'
                                            WHERE id_object = {$this->ac->id_object}");
                        $aliceCapabilities = [
                            "type" => "devices.capabilities.mode",
                            "state" => [
                                "instance" => "fan_speed",
                                "value" => Device::ALICE_AC_MODES_MAPPING[$speed]
                            ]
                        ];
                        Device::aliceCallbackState($this->ac->id_object, $aliceCapabilities, null);
                        return true;
                    }
                    else return false;
                }
            }
        }
    }

    public function setAcVDir(string $vDir)
    {
        if(isset($this->ac->vdirs))
        {
            $acVDirs = json_decode($this->ac->vdirs, true);

            if (array_key_exists($vDir, $acVDirs))
            {
                $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_vdir');
                if (isset($registerId))
                {
                    $response = Modbus::sendModbus($registerId, 'write', $acVDirs[$vDir]);
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
        if(isset($this->ac->hdirs))
        {
            $acHDirs = json_decode($this->ac->hdirs, true);

            if (array_key_exists($hDir, $acHDirs))
            {
                $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, 'ac_hdir');
                if (isset($registerId))
                {
                    $response = Modbus::sendModbus($registerId, 'write', $acHDirs[$hDir]);
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

    public function getAcPower() {
        return $this->ac->state;
    }

    public function getAcTemperature() {
        return $this->ac->temp;
    }

    public function getAcMode() {
        return $this->ac->mode;
    }

    public function getAcFanSpeed() {
        return $this->ac->fan;
    }

    public function getAcVDir() {
        return $this->ac->vdir;
    }

    public function getAcHDir() {
        return $this->ac->hdir;
    }

    public function getMinMaxTemps() {
        return json_decode($this->ac->temps, true);
    }

    public function getFans() {
        return json_decode($this->ac->fans, true);
    }

    public function getModes() {
        return json_decode($this->ac->modes, true);
    }

    public function updateAcParams() {
        $sql = parent::$db->query(" SELECT `id`, `alias`
                                    FROM `modbus_registers`
                                    WHERE `slaver_id` = {$this->ac->modbus_slaver_id}");
        while ($acParam = $sql->fetch(PDO::FETCH_OBJ)) {
            $registerId = Modbus::getRegisterIdByAlias ($this->ac->modbus_slaver_id, $acParam->alias);
            if (isset($registerId)) {
                $response = Modbus::sendModbus($registerId, 'read');
                if (isset($response)) {
                    $column = substr($acParam->alias, 3);
                    if ($column == 'power') {
                        if (false == $response) $state = 'off';
                        else $state = 'on';
                        $object = new Objects();
                        $object->select($this->ac->id_object);
                        $object->setStatus($state, true, false);
                    }
                    else {
                        if ($column != 'temp') {
                            if (isset($this->ac->{$column.'s'}))
                                $value = array_search($response, json_decode($this->ac->{$column.'s'}, true));
                        }
                        else $value = $response;
                        if ($value == false) $value = NULL;
                        parent::$db->query("UPDATE `conditioners`
                                            SET `$column` = '$value'
                                            WHERE id_object = {$this->ac->id_object}");
                        if ($column == 'mode' || $column == 'fan' || $column == 'temp') {
                            if ($column == 'mode') {
                                $aliceCapabilities = [
                                    "type" => "devices.capabilities.mode",
                                    "state" => [
                                        "instance" => "thermostat",
                                        "value" => Device::ALICE_AC_MODES_MAPPING[$value]
                                    ]
                                ];
                            }
                            if ($column == 'fan') {
                                $aliceCapabilities = [
                                    "type" => "devices.capabilities.mode",
                                    "state" => [
                                        "instance" => "fan_speed",
                                        "value" => Device::ALICE_AC_FAN_MODES_MAPPING[$value]
                                    ]
                                ];
                            }
                            if ($column == 'temp') {
                                $aliceCapabilities = [
                                    "type" => "devices.capabilities.range",
                                    "state" => [
                                        "instance" => "temperature",
                                        "value" => $value
                                    ]
                                ];
                            }
                            Device::aliceCallbackState($this->ac->id_object, $aliceCapabilities, null);
                        } 
                    }
                }
            }
        }
    }
}

