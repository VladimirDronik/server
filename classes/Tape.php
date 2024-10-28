<?php

class Tape extends System
{   
    public $tape;
    private $registersIds = [];
    public $object;

    function __construct($objectId)
    {
        if (isset($objectId))
        {
            //Определяем параметры ленты
            $sql = parent::$db->query(" SELECT `tapes`.`type` AS 'type',
                                            `tapes`.`h` AS 'hue',
                                            `tapes`.`s` AS 'saturation',
                                            `tapes`.`v` AS 'value',
                                            `tapes`.`w` AS 'brightness',
                                            `tapes`.`cct`,
                                            `tapes`.`channel` AS 'channel',
                                            `tapes`.`controller_id` AS 'controller'
                                        FROM `tapes`
                                        WHERE `tapes`.`id_object` = $objectId");

            if($sql->rowCount() > 0)
            {
                $this->tape = $sql->fetch(PDO::FETCH_OBJ);
                $this->registersIds = $this->getRegistersIdByTapeType();
                $this->object = new Objects();
                $this->object->select($objectId);
            }
            else echo "[Error] LED лента с ID $objectId не найдена" . PHP_EOL;
        }
        else echo "[Error] Не определен ID LED ленты" . PHP_EOL;
    }

    private function getRegistersIdByTapeType()
    {
        $channelAliasConnection = [
            1 => 'ch1',
            2 => 'ch2',
            3 => 'ch3',
            4 => 'ch4',
            12 => 'ch1_ch2',
            34 => 'ch3_ch4',
            123 => 'ch1_ch2_ch3',
            1234 => 'ch1_ch2_ch3_ch4'
        ];

        switch ($this->tape->type)
        {
            case 'RGB':
                $registersIds['state'] = Modbus::getRegisterIdByAlias($this->tape->controller, 'ch1_ch2_ch3_rgb_state');
                $registersIds['h_component'] = Modbus::getRegisterIdByAlias($this->tape->controller, 'h_component');
                $registersIds['s_component'] = Modbus::getRegisterIdByAlias($this->tape->controller, 's_component');
                $registersIds['brightness'] = Modbus::getRegisterIdByAlias($this->tape->controller, 'v_component');
                break;
            
            case 'CCT':
                $registersIds['state'] = Modbus::getRegisterIdByAlias($this->tape->controller,
                    $channelAliasConnection[$this->tape->channel].'_cct_state');
                $registersIds['temperature'] = Modbus::getRegisterIdByAlias($this->tape->controller,
                    $channelAliasConnection[$this->tape->channel].'_cct_temperature');
                $registersIds['brightness'] = Modbus::getRegisterIdByAlias($this->tape->controller,
                    $channelAliasConnection[$this->tape->channel].'_cct_brightness');
                break;
                
            case 'W':
                if ($this->tape->channel < 5) $str = '_independent';
                else $str = '_parallel';
                $registersIds['state'] = Modbus::getRegisterIdByAlias($this->tape->controller,
                    $channelAliasConnection[$this->tape->channel].$str.'_state');
                $registersIds['brightness'] = Modbus::getRegisterIdByAlias($this->tape->controller,
                    $channelAliasConnection[$this->tape->channel].$str.'_brightness');
                break;
        }
        return $registersIds;
    }

    public function getType() {
        return $this->tape->type;
    }

    public function tapeOn()
    {
        $response = Modbus::sendModbus($this->registersIds['state'], 'write', 1);
        if (isset($response)) $this->object->setStatus('on',true,false);
    }

    public function tapeOff()
    {
        $response = Modbus::sendModbus($this->registersIds['state'], 'write', 0);
        if (isset($response)) $this->object->setStatus('off',true,false);
    }

    public function tapeSw()
    {
        if ($this->object->status == 'on') $this->tapeOff();
        else $this->tapeOn();
    }
    
    /**
     * Цвет задается в палитре HSV. Необходимо передать H и S компоненты.
     * V компонент используется для управления яркостью RGB ленты
     * @param int $hue - H компонент
     * @param int $saturation - S компонент
     */
    public function tapeSetColor (int $hue, int $saturation)
    {
        if (isset($hue) && isset($saturation))
        {
            if ($this->tape->type == 'RGB')
            {
                $response = Modbus::sendModbus($this->registersIds['h_component'], 'write', $hue);
                if (isset($response))
                    parent::$db->query("UPDATE `tapes`
                                        SET `h` = $hue
                                        WHERE `id_object` = {$this->object->id}");

                $response = Modbus::sendModbus($this->registersIds['s_component'], 'write', $saturation);
                if (isset($response))
                    parent::$db->query("UPDATE `tapes`
                                        SET `s` = $saturation
                                        WHERE `id_object` = {$this->object->id}");
                
                $aliceCapabilities = [
                    "type" => "devices.capabilities.color_setting",
                    "state" => [
                        "instance" => "hsv",
                        "value" => [
                            "h" => $hue,
                            "s" => $saturation,
                            "v" => 100
                        ]
                    ]
                ];
                Device::aliceCallbackState($this->object->id, $aliceCapabilities, null);
            }
            else echo "[Error] Устройство не поддерживает настройку цвета" . PHP_EOL;
        }
        else echo "[Error] Не указаны значения оттенка и/или насыщенности" . PHP_EOL;
    }

    public function getColor() {
        $sql = parent::$db->query(" SELECT `h`, `s`, `v`
                                    FROM `tapes`
                                    WHERE `id_object` = {$this->object->id}");
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * @param int $temperature - значение цветовой температуры, 0 до 100 %
     * 0 - тёплый цвет, 100 - холодный цвет
     */
    public function tapeSetTemperature(int $temperature)
    {
        if (isset($temperature))
        {
            if ($this->tape->type == 'CCT')
            {
                $response = Modbus::sendModbus($this->registersIds['temperature'], 'write', $temperature);
                if (isset($response))
                    parent::$db->query("UPDATE `tapes`
                                        SET `cct` = $temperature
                                        WHERE `id_object` = {$this->object->id}");
            }
            else echo "[Error] Устройство не поддерживает настройку цветовой температуры" . PHP_EOL;
        }
        else echo "[Error] Не указано значение цветовой температуры" . PHP_EOL;
    }

    /**
     * @param int $brightness - значение яркости, от 0 до 100 %
     */
    public function tapeSetBrightness (int $brightness)
    {
        if (isset($brightness))
        {
            if ($brightness > 0)
            {
                $response = Modbus::sendModbus($this->registersIds['brightness'], 'write', $brightness);
                if (isset($response))
                {
                    if ($this->tape->type == 'RGB') $column = 'v';
                    else $column = 'w';
                    parent::$db->query("UPDATE `tapes`
                                        SET `$column` = $brightness
                                        WHERE `id_object` = {$this->object->id}");

                    $aliceCapabilities = [
                        "type" => "devices.capabilities.range",
                        "state" => [
                            "instance" => "brightness",
                            "value" => $brightness
                        ]
                    ];
                    Device::aliceCallbackState($this->object->id, $aliceCapabilities, null);
                }
            }
        }
        else echo "[Error] Не указано значение яркости" . PHP_EOL;
    }

    public function getBrightness() {
        if ($this->tape->type == 'RGB') return $this->tape->value;
        else return $this->tape->brightness;
    }

    public function getCct() {
        return $this->tape->cct;
    }

}