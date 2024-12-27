<?php
/**
 * Класс работы с устройствами на шине DALI.
 * В качестве DALI контроллера выступает Modbus-DALI шлюз компании EcoDim.
 */
class Dali extends System
{
    const FADE_TIME = [
        '0' => 0x00,
        '0.707' => 0x01,
        '1.000' => 0x02,
        '1.414' => 0x03,
        '2.000' => 0x04,
        '2.828' => 0x05,
        '4.000' => 0x06,
        '5.657' => 0x07,
        '8.000' => 0x08,
        '11.31' => 0x09,
        '16.00' => 0x10,
        '22.62' => 0x11,
        '32.00'  => 0x12,
        '45.25' => 0x13,
        '64.00' => 0x14,
        '90.51' => 0x15
    ];
    // private static $address;
    // private static $daliGatewayId;
    // private static $daliState;

    public $daliDevice = null;
    private $object = null;

    function __construct($idObject)
    {
        if (isset($idObject))
        {
            $sql = parent::$db->query(" SELECT `dali_devices`.`id_object`,
                                               `dali_devices`.`address`,
                                               `dali_devices`.`is_group`,
                                               `dali_devices`.`dali_gateway`,
                                               `dali_devices`.`brightness`,
                                               `dali_devices`.`cct`,
                                               `objects`.`status`,
                                               `modbus_slavers`.`active`
                                        FROM `dali_devices`
                                        INNER JOIN `objects`
                                        ON `dali_devices`.`id_object` = `objects`.`id`
                                        INNER JOIN `modbus_slavers`
                                        ON `modbus_slavers`.`id` = `dali_devices`.`dali_gateway`
                                        WHERE `id_object` = $idObject");

            if($sql->rowCount() > 0) 
            {
                $this->daliDevice = $sql->fetch(PDO::FETCH_OBJ);
                $this->object = new Objects();
                $this->object->select($idObject);
            }
            else echo "[Error] DALI устройство (ID $idObject) не найдено" . PHP_EOL;
        }
        else echo "[Error] Не определен ID устройства DALI" . PHP_EOL;
    }

    public static function nbit($number, $n)
    {
        return ($number >> $n) & 1;
    }

    public static function percentToArcpower($percent) 
    {
        $val = 253*(log10($percent)+1)/3+1;
        return round($val);
    }

    public static function arcpowerTopercent($arcpower) 
    {
        $val = pow(10, (3*($arcpower-1)/253)-1);
        return round($val);
    }

    public function getBrightness() {
        return $this->daliDevice->brightness;
    }

    public function getBrightnessFromDevice()
    {
        $alias = "dali_get_brightness_a{$this->daliDevice->address}";

        $response = Modbus::sendModbus(
            Modbus::getRegisterIdByAlias($this->daliDevice->dali_gateway, $alias),
            'read'
        );

        if (isset($response))
        {
            $brightness = self::arcpowerTopercent((int)$response);

            if ($brightness > 0) {
                parent::$db->query("UPDATE `dali_devices`
                                    SET `brightness` = $brightness
                                    WHERE `id_object` = {$this->daliDevice->id_object}");
                $this->object->setStatus('on', true, false);
            }
            else {
                $sql = parent::$db->query(" SELECT `brightness` FROM `dali_devices`
                                            WHERE `id_object` = {$this->daliDevice->id_object}");
                $brightness = $sql->fetch(PDO::FETCH_OBJ)->brightness;
                $this->object->setStatus('off', true, false);
            }
            return $brightness;
        }
        else return null;
    }


    public function getColorTemperature() {
        return $this->daliDevice->cct;
    }

    public function getColorTemperatureFromDevice()
    {
        $alias = "dali_get_temperature_a{$this->daliDevice->address}";

        $response = Modbus::sendModbus(
            Modbus::getRegisterIdByAlias($this->daliDevice->dali_gateway, $alias),
            'read'
        );

        if (isset($response))
        {
            $colorTemperature = (int)$response;
            parent::$db->query("UPDATE `dali_devices`
                                SET `cct` = $colorTemperature
                                WHERE `id_object` = {$this->daliDevice->id_object}");
            return $colorTemperature;
        }
        else return null;
    }

    public function getDeviceStatus()
    {
        $alias = "dali_device_status_a{$this->daliDevice->address}";
        $response = Modbus::sendModbus(
            Modbus::getRegisterIdByAlias($this->daliDevice->dali_gateway, $alias),
            'read'
        );

        if (isset($response))
        {
            $status = (int)$response;
            $statusArray = [];
            // bit 1 - неисправность устройства. 0 = ОК; 1 = неисправность
            $isFailure = self::nbit ($status,1);
            $statusArray["failure"] = $isFailure;
            // bit 2 - состояние устройства. 0 = off; 1 = on
            (self::nbit ($status,2) == 0) ? $state = "off" : $state = "on";
            $statusArray["state"] = $state;

            $this->object->setStatus($state, true, false);
            parent::$db->query("UPDATE `dali_devices`
                                SET `failure` = $isFailure
                                WHERE `id_object` = {$this->daliDevice->id_object}");
            
            return $statusArray;
        }
        else return null;
    }

    public function getGroupStatus()
    {
        $alias = "dali_get_failure_g{$this->daliDevice->address}";
        
        $response = Modbus::sendModbus(
            Modbus::getRegisterIdByAlias($this->daliDevice->dali_gateway, $alias),
            'read'
        );

        if (isset($response))
        {
            if ($response > 0) $statusArray["failure"] = 1;
            else $statusArray["failure"] = 0;
        }

        $alias = "dali_get_state_g{$this->daliDevice->address}";
        
        $response = Modbus::sendModbus(
            Modbus::getRegisterIdByAlias($this->daliDevice->dali_gateway, $alias),
            'read'
        );

        if (isset($response))
        {
            if ($response > 0) $state = 'on';
            else $state = 'off';
            $statusArray["state"] = $state;
            $this->object->setStatus($state, true, false);
        }
    }

    public function setBrightness(int $brightness)
    {

        if ($brightness == 0) $value = 0;
        else $value = self::percentToArcpower($brightness);

        if ($this->daliDevice->is_group) $alias = "dali_set_brightness_g{$this->daliDevice->address}";
        else $alias = "dali_set_brightness_a{$this->daliDevice->address}";

        $response = Modbus::sendModbus(
            Modbus::getRegisterIdByAlias($this->daliDevice->dali_gateway, $alias),
            'write',
            $value
        );
        
        if (isset($response)) {
            if ($brightness > 0) {
                parent::$db->query("UPDATE `dali_devices`
                                    SET `brightness` =  $brightness
                                    WHERE `id_object` = {$this->daliDevice->id_object}");
                $this->object->setStatus('on',true,false);
                if ($this->daliDevice->brightness != $brightness) {
                    $aliceCapabilities = [
                        "type" => "devices.capabilities.range",
                        "state" => [
                            "instance" => "brightness",
                            "value" => $brightness
                        ]
                    ];
                    $payload = [
                        "object_id" => $this->daliDevice->id_object,
                        "capabilities" => $aliceCapabilities,
                        "properties" => null
                    ];
                    $mqtt = new Mqtt();
                    $mqtt->publish('alice/callback', $payload, false);
                }
                $this->daliDevice->brightness = $brightness;
            }
            else $this->object->setStatus('off',true,false);

            return true;
        }
        else return false;
    }

    public function setColorTemperature(int $cct)
    {
        if ($cct < 1000) $cct = 1000;
        if ($cct > 10000) $cct = 10000;

        if ($this->daliDevice->is_group) $alias = "dali_set_temperature_g{$this->daliDevice->address}";
        else $alias = "dali_set_temperature_a{$this->daliDevice->address}";

        $response = Modbus::sendModbus(
            Modbus::getRegisterIdByAlias($this->daliDevice->dali_gateway, $alias),
            'write',
            $cct
        );

        if (isset($response))
        {
            parent::$db->query("UPDATE `dali_devices`
                                SET `cct` =  $cct
                                WHERE `id_object` = {$this->daliDevice->id_object}");
            
            if ($this->daliDevice->cct != $cct) {
                $aliceCapabilities = [
                    "type" => "devices.capabilities.color_setting",
                    "state" => [
                        "instance" => "temperature_k",
                        "value" => $cct
                    ]
                ];
                $payload = [
                    "object_id" => $this->daliDevice->id_object,
                    "capabilities" => $aliceCapabilities,
                    "properties" => null
                ];
    
                $mqtt = new Mqtt();
                $mqtt->publish('alice/callback', $payload, false);
            }
            return true;
        }
        else return false;
    }

    public function sendCmd(int $daliCmd)
    {
        if ($this->daliDevice->is_group) $alias = "dali_send_cmd_g{$this->daliDevice->address}";
        else $alias = "dali_send_cmd_a{$this->daliDevice->address}";

        $response = Modbus::sendModbus(
            Modbus::getRegisterIdByAlias($this->daliDevice->dali_gateway, $alias),
            'write',
            $daliCmd
        );

        if (isset($response)) return true;
        else return false;
    }

    public function daliOff()
    {
        $offCmd = $this->setBrightness(0);
        if ($offCmd) return true;
        else return false;
    }

    public function daliOn()
    {
        $onCmd = $this->setBrightness($this->daliDevice->brightness);
        if ($onCmd) return true;
        else return false;
    }

    public function daliSw()
    {
        if ($this->object->status == "on") return $this->daliOff();
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

    private function getDirectRegisters() : array
    {
        $sql = System::$db->query(" SELECT `id`, `alias` FROM `modbus_registers` 
            WHERE `slaver_id` = {$this->daliDevice->dali_gateway}
            AND `alias` = 'dali_direct_cmd'
            OR `alias` = 'dali_direct_response'
            OR `alias` = 'dali_direct_status'");
        while ($row = $sql->fetch(PDO::FETCH_OBJ)) $directRegisters[$row->alias] = $row->id;
        return $directRegisters;
    }

    private function getCmdFirstByte() {
        return ($this->daliDevice->address << 1) + 1;
    }

    private function sendDirectCmd (int $cmd, $isResponse = false, $isConfCmd = false)
    {
        $directRegisters = $this->getDirectRegisters();
        if ($isConfCmd) {
            Modbus::sendModbus($directRegisters['dali_direct_cmd'], 'write', $cmd);
        }
        $result = Modbus::sendModbus($directRegisters['dali_direct_cmd'], 'write', $cmd);

        if ($isResponse) {
            $start = microtime(true);
            do {
                usleep(250000);
                $response = Modbus::sendModbus($directRegisters['dali_direct_status'], 'read');
            } 
            while ($response != 3 && (microtime(true) - $start) < 2);
            if ($response == 3)
                return Modbus::sendModbus($directRegisters['dali_direct_response'], 'read');
        }
        
        return $result;
    }

    public function addToGroup(int $group)
    {
        $firstByte = ($this->daliDevice->address << 1) + 1;
        $addCmd = 0x60 + $group;
        $this->writeToDtr(0x00);
        $this->sendDirectCmd($firstByte << 8 | $addCmd, false, true);
        $groups7_0 = $this->sendDirectCmd($firstByte << 8 | 0xC0, true, false);
        $groups15_8 = $this->sendDirectCmd($firstByte << 8 | 0xC1, true, false);

        $groupFlags = ($groups15_8 << 8) | $groups7_0;

        if ($this->nbit($groupFlags, $group)) return true;
        else return false;
    }
    
    public function delFromGroup(int $group)
    {
        $firstByte = ($this->daliDevice->address << 1) + 1;
        $addCmd = 0x70 + $group;
        $this->writeToDtr(0x00);
        $this->sendDirectCmd($firstByte << 8 | $addCmd, false, true);
        $groups7_0 = $this->sendDirectCmd ($firstByte << 8 | 0xC0, true, false);
        $groups15_8 = $this->sendDirectCmd ($firstByte << 8 | 0xC1, true, false);

        $groupFlags = ($groups15_8 << 8) | $groups7_0;

        if ($this->nbit($groupFlags, $group)) return false;
        else return true;
    }

    public function writeToDtr($value) {
        $this->sendDirectCmd(0xA3 << 8 | $value, false, true);
    }

    public function setFadeTime(string $fadeTime) {
        $this->writeToDtr(self::FADE_TIME[$fadeTime]);
        $firstByte = ($this->daliDevice->address << 1) + 1;
        $this->sendDirectCmd($firstByte << 8 | 0x2E, false, true);
    }

    public function getMinArcLevel() {
        $firstByte = ($this->daliDevice->address << 1) + 1;
        return $this->sendDirectCmd($firstByte << 8 | 0x9A, true, false);
    }

    public function getDeviceType() {
        $firstByte = ($this->daliDevice->address << 1) + 1;
        return $this->sendDirectCmd($firstByte << 8 | 0x99, true, false);
    }

    public function onWithFade($targetBrightness) {
        $this->setFadeTime('4.000');
        $this->setBrightness($targetBrightness);
    }

    public function stop() {
        if ($this->daliDevice->is_group) $alias = "dali_set_brightness_g{$this->daliDevice->address}";
        else $alias = "dali_set_brightness_a{$this->daliDevice->address}";

        $response = Modbus::sendModbus(
            Modbus::getRegisterIdByAlias($this->daliDevice->dali_gateway, $alias),
            'write',
            255
        );
        $this->getBrightnessFromDevice();
        $this->setFadeTime('0.707');
    }

    public function up() {
        $firstByte = ($this->daliDevice->address << 1) + 1;
        return $this->sendDirectCmd($firstByte << 8 | 0x01, false, false);
    }

    public function down() {
        $firstByte = ($this->daliDevice->address << 1) + 1;
        return $this->sendDirectCmd($firstByte << 8 | 0x04, false, false);
    }


    public function changeBrightness($targetValue) {

        $mqtt = new Mqtt();
        $mqtt->subscribe('dali/cmd');

        $response = Modbus::sendModbus(
            Modbus::getRegisterIdByAlias($this->daliDevice->dali_gateway, $alias),
            'write',
            255
        );
        $this->getBrightnessFromDevice();
        $this->setFadeTime('0.707');
    }
}