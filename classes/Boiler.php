<?php

/**
 * Класс для работы с котлом
 */
class Boiler extends System
{
    private $boiler = null;
    private $object = null;
    private $paramsList = [];
    public $debug = false;

    function __construct($idObject)
    {
        if (isset($idObject))
        {
            $sql = parent::$db->query(" SELECT `boilers`.*, `modbus_slavers`.`active`
                                        FROM `boilers`
                                        INNER JOIN `modbus_slavers`
                                        ON `modbus_slavers`.`id` = `boilers`.`gateway_id`
                                        WHERE `id_object` = $idObject");
            if($sql->rowCount() > 0)
            {
                $this->boiler = $sql->fetch(PDO::FETCH_OBJ);
                $this->object = new Objects();
                $this->object->select($idObject);
                $this->paramsList = $this->getParamsList();
            }
            else echo "[Error] Котел с ID объекта $idObject не найден" . PHP_EOL;
        }
        else echo "[Error] Не определен ID котла" . PHP_EOL;
    }

    private function getParamsList()
    {
        $sql = parent::$db->query(" SELECT *
                                    FROM `boilers_params_flags`
                                    WHERE `boiler_id` = {$this->boiler->id}");
        $paramsList = (array)$sql->fetch(PDO::FETCH_OBJ);
        unset($paramsList['id'], $paramsList['boiler_id']);
        $paramsList = array_keys($paramsList, 1);
        return $paramsList;
    }

    public function checkBoiler()
    {
        foreach ($this->paramsList as $paramName)
        {
            switch ($paramName)
            {
                case 'outdoor_temp':
                    $this->weatherCompensation();
                    break;
                
                case 'indoor_temp':
                    break;

                default:
                    $paramValue = $this->getParam($paramName);
                   
                    if (isset($paramValue))
                    {
                        $this->writeToDb($paramName, $paramValue);
                    }

                    if ($this->debug) echo "$paramName: {$paramValue}" . PHP_EOL;
                    break;
            }
        }
    }

    public function weatherCompensation()
    {
        $sql = parent::$db->query(" SELECT `termostats`.`current`
                                    FROM `termostats`
                                    INNER JOIN `boilers`
                                    ON `boilers`.`outdoor_sensor` = `termostats`.`id_object`
                                    WHERE `boilers`.`id` = " . $this->boiler->id);
        $outdoorTemp = $sql->fetch(PDO::FETCH_OBJ)->current;

        if (isset($outdoorTemp))
        {
            if ($this->boiler->heating_mode == 'wc')
            {
                
                $sql = parent::$db->query(" SELECT `t_water`
                                            FROM `boiler_auto`
                                            WHERE `id_object` = {$this->boiler->id_object}
                                            AND `t_out` <= $outdoorTemp
                                            OR `t_out` = (SELECT MIN(`boiler_auto`.`t_out`) FROM `boiler_auto`)
                                            ORDER BY `boiler_auto`.`t_out` DESC LIMIT 1");

                if($sql->rowCount() > 0)
                {
                    $newSetpoint = $sql->fetch(PDO::FETCH_OBJ)->t_water;
                    $this->setParam('ch_setpoint_temp', $newSetpoint);
                }
            }
        }
        
        $this->writeToDb('outdoor_temp', $outdoorTemp);

        if ($this->debug) echo "outdoor_temp: {$outdoorTemp}" . PHP_EOL;
    }

    /**
     * Функция получения значения параметра
     */
    private function getParam(string $paramName)
    {
        if ($this->boiler->gateway_type == 'modbus')
        {
            $registerId = Modbus::getRegisterIdByAlias($this->boiler->gateway_id, $paramName);
            if (isset($registerId))
            {
                $response = Modbus::sendModbus($registerId, 'read');
                if (isset($response)) return $response;
                else return null;
            }
            else return null;
        }
    }

    /**
     * Функция отправки значения параметра на устройство
     */
    public function setParam(string $paramName, mixed $value)
    {
        if ($this->boiler->gateway_type == 'modbus')
        {
            $registerId = Modbus::getRegisterIdByAlias($this->boiler->gateway_id, $paramName);
            if (isset($registerId))
            {
                $response = Modbus::sendModbus($registerId, 'write', $value);
                if (isset($response)) 
                {
                    $this->writeToDb($paramName, $value);
                    return true;
                }
                else return false;
            }
        }
    }

    private function writeToDb(string $paramName, mixed $value)
    {
        if ($paramName != 'indoor_temp' && $paramName != 'outdoor_temp')
        {
            if(!is_numeric($value) || $value < 0)
            {
            echo "Некорректное значение температуры: " . $value . PHP_EOL;
            $value = 0;
            }

            parent::$db->query("UPDATE `boilers_params`
                                SET `$paramName` = $value
                                WHERE `boiler_id` = {$this->boiler->id}");
        }
        parent::$db->query("UPDATE `elements`
                            SET `status` = $value
                            WHERE `id_object` = {$this->boiler->id_object}
                            AND `handle` = '$paramName'");
    }

    /**
     * Установить режим работы отопления котла wc или manual
     * В зависимости от этого режима котел будет работат следующим образом:
     * wc - ПЗА: будет оцениваться температура с внешнего датчика и сравниваться
     * с значениями в таблице boiler_auto. В зависимости от этого будет выставляться температура
     * теплоносителя.
     * manual - ручная установка температуры теплоносителя
     */
    public function setMode($mode)
    {
        parent::$db->query("UPDATE `boilers`
                            SET `heating_mode` = '$mode'
                            WHERE `id_object` = {$this->boiler->id_object}");
        $this->boiler->heating_mode = $mode;
        if ($mode == 'wc') $this->weatherCompensation();
    }

    public function getMode()
    {
        return $this->boiler->heating_mode;
    }

    public static function convertToF88($value)
    {
        if (0x8000 & $value) return ($value - 0x10000) / 0x100;
        else return $value / 0x100;
    }

    public static function convertFromF88($value)
    {
        if ($value >= 0) return $value * 0x100;
        else return 0x10000 + ($value * 0x100);
    }

}