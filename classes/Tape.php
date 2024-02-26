<?php

class Tape extends Device
{
    private $idObject;
    private $port;
    private $address;
    private $type;
    private $status;
    private $h;
    private $s;
    private $v;
    private $w;
    
    private $tape = null;
    private $registersIds = [];
    private $object = null;

    function __construct($objectId)
    {
        //Определяем параметры ленты
        $sql = parent::$db->query(" SELECT `tapes`.`type` AS 'type',
                                           `tapes`.`h` AS 'hue',
                                           `tapes`.`s` AS 'saturation',
                                           `tapes`.`v` AS 'value',
                                           `tapes`.`w` AS 'brightness',
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
        
        // else self::$tape = null;
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

        // $keys = [];
        // $registersIds['state'] = Modbus::getRegisterIdByAlias($this->tape->controller,
        //             $channelAliasConnection[$this->tape->channel].'_state');
        // var_dump ($this->tape->type);

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
                    $channelAliasConnection[$this->tape->channel].'_cct_brightness');
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
        // var_dump ($registersIds);
        return $registersIds;
    }

    public function tapeOn()
    {
        Modbus::putTaskIntoQueue($this->registersIds['state'], 'write', 5, 1);
        $this->object->setStatus('on',true,false);
    }

    public function tapeOff()
    {
        Modbus::putTaskIntoQueue($this->registersIds['state'], 'write', 5, 0);
        $this->object->setStatus('off',true,false);
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
            Modbus::putTaskIntoQueue($this->registersIds['h_component'], 'write', 5, $hue);
            Modbus::putTaskIntoQueue($this->registersIds['s_component'], 'write', 5, $saturation);
            parent::$db->query("UPDATE `tapes` SET `h` = $hue, `s` = $saturation
                                WHERE `id_object` = ". $this->object->id);
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
            Modbus::putTaskIntoQueue($this->registersIds['temperature'], 'write', 5, $temperature);
            parent::$db->query("UPDATE `tapes` SET `cct` = $temperature
                                WHERE `id_object` = ". $this->object->id);
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
            Modbus::putTaskIntoQueue($this->registersIds['brightness'], 'write', 5, $brightness);
            $this->tapeOn();
            if ($this->tape->type == 'RGB') $column = 'v';
            else $column = 'w';
            parent::$db->query("UPDATE `tapes` SET `$column` = $brightness
                                WHERE `id_object` = ". $this->object->id);
        }
        else $this->tapeOff();
    }

    /**
     * Установка статуса для ленты вкл/выкл
     */
    function setStatus(int $color, int $shade, int $bright, int $wbright, string $status) {
               
    
          // если значения цвета и яркости не пришли от приложения, значит берем предыдущие значения, которые были в БД
          // иначе берем новые значения, которые пришли от приложения
        if ($color == 0 && $bright == 0 && $shade == 0) {
           $color = $this->h; 
           $bright = $this->v; 
           $shade = $this->s;
        } 
        
        //Если значение яркости белого не пришло, значит берем предыдущее значение из БД
        if ($wbright == 0) {
            $wbright = $this->w;
        }

        parent::$db->query("UPDATE tapes 
                            SET status = '$status', h = $color, s = $shade, v = $bright, w = $wbright 
                            WHERE id_object = $this->idObject");

        $this->setValue($color, $shade, $bright, $wbright, $status);

    }

    /**
     * Установка значения для ленты RGB
     */
    function setValue(int $color, int $shade, int $bright, int $wbright, string $status) {

        //обработка включения ленты с выбором цвета и яркости (если пользователь выбрал цвет в приложении)
        if ($status == "on") {
            if ($this->type == 'RGB' || $this->type == 'RGBW') {
                //Цвет и яркость для цветной ленты
                $method = Objects::getMethodByAlias($this->idObject, "ch123_color");
                Action::runAction($method->id, null, null, $color);

                $method = Objects::getMethodByAlias($this->idObject, "ch123_shade");
                Action::runAction($method->id, null, null, $shade);

                $method = Objects::getMethodByAlias($this->idObject, "ch123_bright");
                Action::runAction($method->id, null, null, $bright);

                $method = Objects::getMethodByAlias($this->idObject, "ch123_enable");
                if ($bright <= 1) {
                    Action::runAction($method->id, null, null, false);
                } else {
                    Action::runAction($method->id, null, null, true);
                }
                
            } 
            //обработка яркости для белой ленты
            if ($this->type == 'RGBW') {    
                
                $method = Objects::getMethodByAlias($this->idObject, "ch4_bright");
                Action::runAction($method->id, null, null, $wbright);

                $method = Objects::getMethodByAlias($this->idObject, "ch4_enable");
                if ($wbright <= 1) {
                    Action::runAction($method->id, null, null, false);
                } else {
                    Action::runAction($method->id, null, null, true);
                }
            } 
            elseif ($this->type == 'W') {
                //яркость для белой ленты, висящей отдельно на 4м порту
                $method = Objects::getMethodByAlias($this->idObject, "ch4_bright");
                Action::runAction($method->id, null, null, $wbright);

                $method = Objects::getMethodByAlias($this->idObject, "ch4_enable");
                if ($wbright <= 1) {
                    Action::runAction($method->id, null, null, false);
                } else {
                    Action::runAction($method->id, null, null, true);
                }
            } 

        

        }  else {
            //обработка выключения ленты
            if ($this->type == 'RGB' || $this->type == 'RGBW') {
                $method = Objects::getMethodByAlias($this->idObject, "ch123_enable");
                Action::runAction($method->id, null, null, false);
                
                $method = Objects::getMethodByAlias($this->idObject, "ch4_enable");
                Action::runAction($method->id, null, null, false);
            } elseif ($this->type == 'w') {
                $method = Objects::getMethodByAlias($this->idObject, "ch4_enable");
                Action::runAction($method->id, null, null, false);
            }  
    }
    
    }
}