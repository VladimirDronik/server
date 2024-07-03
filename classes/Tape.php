<?php

class Tape extends Device
{   
    private $tape = null;
    private $registersIds = [];
    private $object = null;

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
                                            `tapes`.`channel` AS 'channel',
                                            `tapes`.`controller_id` AS 'controller',
                                            `modbus_slavers`.`active`
                                        FROM `tapes`
                                        INNER JOIN `modbus_slavers`
                                        ON `modbus_slavers`.`id` = `tapes`.`controller_id`
                                        WHERE `tapes`.`id_object` = $objectId");

            if($sql->rowCount() > 0)
            {
                $this->tape = $sql->fetch(PDO::FETCH_OBJ);
                if ($this->tape->active != 1)
                {
                    $modbusGw = $this->tape->controller;
                    echo "[Error] Modbus устройство WB-LED (ID $modbusGw) недоступно" . PHP_EOL;
                    System::addLog("Error", "Modbus шлюз кондиционера (ID $modbusGw) недоступен", "port");
                    exit;
                }
                $this->registersIds = $this->getRegistersIdByTapeType();
                $this->object = new Objects();
                $this->object->select($objectId);
            }
            else 
            {
                echo "[Error] LED лента с ID $objectId не найдена" . PHP_EOL;
                exit;
            }
        }
        else
        {
            echo "[Error] Не определен ID LED ленты" . PHP_EOL;
            exit;
        }
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

    public function tapeOn()
    {
        $response = Modbus::modbusRtu($this->registersIds['state'], 'write', 5, 1);
        if ($response && !$response['error']) $this->object->setStatus('on',true,false);
    }

    public function tapeOff()
    {
        $response = Modbus::modbusRtu($this->registersIds['state'], 'write', 5, 0);
        if ($response && !$response['error']) $this->object->setStatus('off',true,false);
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
        if ($this->tape->type == 'RGB')
        {
            $response = Modbus::modbusRtu($this->registersIds['h_component'], 'write', 5, $hue);
            if ($response && !$response['error'])
                parent::$db->query("UPDATE `tapes`
                                    SET `h` = $hue
                                    WHERE `id_object` = {$this->object->id}");

            $response = Modbus::modbusRtu($this->registersIds['s_component'], 'write', 5, $saturation);
            if ($response && !$response['error'])
                parent::$db->query("UPDATE `tapes`
                                    SET `s` = $saturation
                                    WHERE `id_object` = {$this->object->id}");
        }
        else echo "Устройство не поддерживает настройку цвета" . PHP_EOL;
    }

    /**
     * @param int $temperature - значение цветовой температуры, 0 до 100 %
     * 0 - тёплый цвет, 100 - холодный цвет
     */
    public function tapeSetTemperature (int $temperature)
    {
        if ($this->tape->type == 'CCT')
        {
            $response = Modbus::modbusRtu($this->registersIds['temperature'], 'write', 5, $temperature);
            if ($response && !$response['error'])
                parent::$db->query("UPDATE `tapes`
                                    SET `cct` = $temperature
                                    WHERE `id_object` = {$this->object->id}");
        }
        else echo "Устройство не поддерживает настройку цветовой температуры" . PHP_EOL;
    }

    /**
     * @param int $brightness - значение яркости, от 0 до 100 %
     */
    public function tapeSetBrightness (int $brightness)
    {
        if ($brightness > 0)
        {
            Modbus::modbusRtu($this->registersIds['brightness'], 'write', 5, $brightness);
            if ($response && !$response['error'])
            {
                if ($this->tape->type == 'RGB') $column = 'v';
                else $column = 'w';
                parent::$db->query("UPDATE `tapes`
                                    SET `$column` = $brightness
                                    WHERE `id_object` = {$this->object->id}");
            }
        }
    }
}