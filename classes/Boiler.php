<?php

/**
 * Класс для работы с котлом
 */
class Boiler extends System
{

    private $boiler = null;
    private $prevParams = [];
    private $currentParams = [];
    public $debug = false;

    function __construct($idObject)
    {
        $sql = parent::$db->query(" SELECT *, `modbus_slavers`.`active`
                                    FROM `boilers`
                                    WHERE `id_object` = $idObject");
        if($sql->rowCount() > 0)
        {
            $this->boiler = $sql->fetch(PDO::FETCH_OBJ);
            $this->prevParams = $this->getPrevParams();
            $this->currentParams = $this->getCurrentParams();
        }
        else echo "Котел с ID объекта $idObject не найден" . PHP_EOL;
        var_dump ($this->boiler);
    }


    private function getPrevParams()
    {
        $sql = parent::$db->query(" SELECT *
                                    FROM `boilers_params_flags`
                                    WHERE `boiler_id` = {$this->boiler->id}");
        $flags = $sql->fetch(PDO::FETCH_OBJ);
        $flags = (array)$flags;
        unset($flags['id'], $flags['boiler_id']);
        $flags = array_keys($flags, 1);
        $columns = implode(",", $flags);
        
        $sql = parent::$db->query(" SELECT $columns
                                    FROM `boilers_params`
                                    WHERE `boiler_id` = {$this->boiler->id}");
        $prevParams = $sql->fetch(PDO::FETCH_OBJ);

        return (array)$prevParams;
    }

    private function getCurrentParams()
    {
        foreach (array_keys($this->prevParams) as $paramName)
        {
            switch ($paramName)
            {
                case 'outdoor_temp':
                case 'indoor_temp':
                    if ($paramName == 'outdoor_temp') $columnName = 'outdoor_sensor';
                    else $columnName = 'indoor_sensor';
                    $sql = parent::$db->query(" SELECT `termostats`.`current`
                                                FROM `termostats`
                                                INNER JOIN `boilers`
                                                ON `boilers`.`$columnName` = `termostats`.`id_object`
                                                WHERE `boilers`.`id` = " . $this->boiler->id);
                    $paramValue = $sql->fetch(PDO::FETCH_OBJ)->current;
                    break;
                    
                default:
                    if ($this->boiler->gateway_type == 'modbus') 
                    {
                        $paramValue = (int)$this->getValueFromDbByAlias($paramName);
                    }
                    break;
            }

            if (!isset($paramValue)) $paramValue = 'NULL';
            $currentParams[$paramName] = $paramValue;
        }

        return $currentParams;
    }

    private function getValueFromDbByAlias(string $alias)
    {
        $registerId = Modbus::getRegisterIdByAlias($this->boiler->gateway_id, $alias);
        return Modbus::modbusRtu($registerId, 'read')['response'];
    }

    // public function checkBoiler()
    // {
    //     foreach ($this->prevParams as $paramName => $paramValue)
    //     {
    //         $this->implementParamValue($paramName, $this->getCurrentParamValue($paramName));
    //     }
    // }

    
    
    
    
   
    /**
     * Функция обработки нового значения параметра (если требуется)
     */
    public function checkBoiler()
    {
        foreach ($this->currentParams as $paramName => $paramValue)
        {
            switch ($paramName)
            {
                case 'outdoor_temp':
                    if ($this->boiler->heating_mode == 'auto')
                    {
                        $sql = parent::$db->query(" SELECT `t_water`
                                                    FROM `boiler_auto`
                                                    WHERE `id_object` = {$this->boiler->id_object}
                                                    AND `t_out` <= '$paramValue'
                                                    OR `t_out` = (SELECT MIN(`boiler_auto`.`t_out`) FROM `boiler_auto`)
                                                    ORDER BY `boiler_auto`.`t_out` DESC LIMIT 1");
                        $newSetpoint = $sql->fetch(PDO::FETCH_OBJ)->t_water;
                        $this->currentParams['ch_setpoint_temp'] = $newSetpoint;
                        $this->putParam('ch_setpoint_temp');
                        if ($this->debug) echo "Исходя из значения уличной температуры температуры, " .
                                            "значение уставки: $newSetpoint" . PHP_EOL;
                    }
                    break;
            }
        }
        
        $this->fillElements();
    }

    /**
     * Функция отправки нового значения параметра на устройство
     */
    private function putParam(string $paramName)
    {
        if ($this->boiler->gateway_type == 'modbus') 
        {
            $registerId = Modbus::getRegisterIdByAlias($this->boiler->gateway_id, $paramName);
            if (isset($registerId)) Modbus::modbusRtu($registerId, 'write', null, $this->currentParams[$paramName]);
        }
    }

    /**
     * Функция заполнения данными таблицы с элементами
     */
    private function fillElements()
    {
        $setString = '';

        foreach ($this->currentParams as $column => $value)
        {
            $setString .= "$column=$value, ";

            parent::$db->exec(" UPDATE elements
                                SET `status` = '$value'
                                WHERE `id_object` = {$this->boiler->id_object}
                                AND handle = '$column'");

            // Labels::setValue($this->boiler->feed_heat_temp.'°С', "csupply", $this->boiler->idObject);
        }
        
        $setString = rtrim($setString, ', ');
        parent::$db->query("UPDATE `boilers_params`
                            SET $setString
                            WHERE `boiler_id` = {$this->boiler->id}");
    }

    /**
     * Функция установки значения параметра
     */
    public function setParam(string $paramName, mixed $value)
    {

        if ($this->boiler->gateway_type == 'modbus') 
        {
            $this->currentParams[$paramName] = $value;
            var_dump($this->currentParams);
            $this->putParam($paramName);
            $this->fillElements();
        }
    }


    /**
     * Установить режим работы отопления котла auto или manual
     * В зависимости от этого режима котел будет работат следующим образом:
     * auto - ПЗА: будет оцениваться температура с внешнего датчика id_outside_thermostat и сравниваться
     * с значениями в таблице boiler_auto. В зависимости от этого будет выставляться температура
     * теплоносителя.
     * manual - ручная установка температуры теплоносителя
     */
    public function setHeatingMode($mode)
    {
        parent::$db->exec("UPDATE `boilers` SET `heating_mode` = '$mode'  WHERE `id_object` = {$this->boiler->id_object}");
        $this->boiler->heating_mode = $mode;
        $this->checkBoiler();
    }



    //  /**
    //  * Функция получения нового значения параметра
    //  */
    // private function getCurrentParamValue(string $paramName)
    // {
    //     switch ($paramName)
    //     {
    //         case 'outdoor_temp':
    //         case 'indoor_temp':
    //             if ($paramName == 'outdoor_temp') $columnName = 'outdoor_sensor';
    //             else $columnName = 'indoor_sensor';
    //             $sql = parent::$db->query(" SELECT `termostats`.`current`
    //                                         FROM `termostats`
    //                                         INNER JOIN `boilers`
    //                                         ON `boilers`.`$columnName` = `termostats`.`id_object`
    //                                         WHERE `boilers`.`id` = " . $this->boiler->id);
    //             $paramValue = $sql->fetch(PDO::FETCH_OBJ)->current;
    //             break;
                
    //         default:
    //             if ($this->boiler->gateway_type == 'modbus') 
    //             {
    //                 $paramValue = (int)$this->getValueFromDbByAlias($paramName);
    //             }
    //             break;
    //     }

    //     // Если значение не получено, пишем в массив NULL
    //     if (!isset($paramValue)) $paramValue = 'NULL';
    //     // Заносим значение параметра в БД
    //     // parent::$db->query("UPDATE `boilers_params`
    //     //                     SET `$paramName` = $paramValue
    //     //                     WHERE `boiler_id` = " . $this->boiler->id);

    //     $currentParams[$paramName] = $paramValue;

    //     if ($this->debug) echo "$paramName: $paramValue" . PHP_EOL;

    //     // $this->fillElements($paramName, $paramValue);

    //     return $paramValue;
    // }



    /**
     * Функция заполнения данными таблицы с элементами
     */
    // private function fillElements()
    // {

    //     //Обновление значения подачи для всех элементов с таким типом
    //     $cooliantSupply = '[{"status":"'.$this->boiler->feed_heat_temp.'°С"}]';
    //     parent::$db->exec("UPDATE elements SET `value` = '$cooliantSupply' 
    //                                WHERE `id_object` = {$this->boiler->id_object} AND handle = 'csupply'");
    //     Labels::setValue($this->boiler->feed_heat_temp.'°С', "csupply", $this->boiler->idObject);


    //     //Обновление значения обратки для всех элементов с таким типом
    //     $cooliantReturn = '[{"status":"'.$this->boiler->back_heat_temp.'°С"}]';
    //     parent::$db->exec("UPDATE elements SET `value` = '$cooliantReturn' 
    //                                WHERE `id_object` = {$this->boiler->id_object} AND handle = 'creturn'");
    //     Labels::setValue($this->boiler->back_heat_temp.'°С', "creturn", $this->boiler->idObject);

    //     //TODO:: добавить температуру контура ГВС по аналогии с cooliantSupply
    //     //TODO:: сделать Labels::setValue для контура ГВС, название для опции gvssupply

    //     //Обновление состояния давления теплоносителя
    //     $pressure = '[{"status":"'.$this->boiler->pressure.'"}]';
    //     parent::$db->exec("UPDATE elements SET `value` = '$pressure' 
    //                                WHERE `id_object` = {$this->boiler->id_object} AND handle = 'pressure'");
    //     Labels::setValue($this->boiler->pressure.'b', "pressure", $this->boiler->idObject);        


    //     //Обновление целевой температуры котла
    //     $target_heat_temp = '[{"status":"'.$this->boiler->target_heat_temp.'°С"}]';
    //     parent::$db->exec("UPDATE elements SET `value` = '$target_heat_temp' 
    //                                WHERE `id_object` = {$this->boiler->id_object} AND handle = 'heat_temp'");

    //     //Обновление целевой температуры контура воды
    //     $water_temp = '[{"status":"'.$this->boiler->water_temp.'°С", "settings": "true"}]';
    //     parent::$db->exec("UPDATE elements SET `value` = '$water_temp' 
    //                                WHERE `id_object` = {$this->boiler->id_object} AND handle = 'water_temp'");

    //     //Обновление уличной температуры
    //     $outdoor_temp = '[{"status":"'.$this->boiler->outdoor_temp.'°С"]';
    //     parent::$db->exec("UPDATE elements SET `value` = '$outdoor_temp' 
    //                                WHERE `id_object` = {$this->boiler->id_object} AND handle = 'outdoor_temp'");

    //     //Обновление кода ошибки
    //     $error_code = '[{"status":"'.$this->boiler->error_code.'"]';
    //         parent::$db->exec("UPDATE elements SET `value` = '$error_code' 
    //                                    WHERE `id_object` = {$this->boiler->id_object} AND handle = 'error_code'");
    //     Labels::setValue($this->boiler->error_code, "код ошибки", $this->boiler->idObject); 

    //     //Описание расширенной ошибки, если есть
    //     $ext_error = '[{"status":"'.$this->boiler->ext_error.'"]';
    //         parent::$db->exec("UPDATE elements SET `value` = '$ext_error' 
    //                                    WHERE `id_object` = {$this->boiler->id_object} AND handle = 'ext_error'");
    //     //TODO:: сделать вставку в Label текста о расшифрованной ошибке (данные должны вставляться в поле message ячейки params в таблице view_item)
    //     // Это нужно для того, чтобы при длительном нажатии на кнопку с кодом ошибки еще можно было показать её расшифровку, если котел это выдает
    //     // Функцию вставки в params нужно сделать в классе Labels

    //     //Обновление значения статуса котла для всех элементов с таким типом
    //     if($this->boiler->boiler == 1)
    //     $state = '[{"status":"on"}]';
    //     else
    //         $state = '[{"status":"off"}]';

    //     parent::$db->exec("UPDATE elements SET `value` = '$state' 
    //                                WHERE `id_object` = {$this->boiler->id_object} AND handle = 'state'");




    //     //Обновление режима работы котла (авто или ручной)
    //     if($this->boiler->mode == 'auto') {
    //         $auto = '[{"status": "on", "settings": "true"}]';
    //         $manual = '[{"status": "off", "settings": "true"}]';
    //     } else {
    //         $auto = '[{"status": "off", "settings": "true"}]';
    //         $manual = '[{"status": "on", "settings": "true"}]';
    //     }

    //     parent::$db->exec("UPDATE elements SET `value` = '$auto'
    //                                WHERE `id_object` = {$this->boiler->id_object} AND handle = 'automode'");

    //     parent::$db->exec("UPDATE elements SET `value` = '$manual'
    //                                WHERE `id_object` = {$this->boiler->id_object} AND handle = 'manualmode'");




//
//        //Обновление состояния горелки для котла
//        if ($this->boiler->burner == 1)
//        $burner = '[{"status": "Включена", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';
//        else
//            $burner = '[{"status": "Выключена", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';
//
//        parent::$db->exec("UPDATE elements SET `value` = '$burner'
//                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'burner'");
//
//
//
//
//        //Обновление состояния горелки ГВС
//        if ($this->boiler->burner_GVS == 1)
//            $burnerGVS = '[{"status": "Включена", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';
//        else
//            $burnerGVS = '[{"status": "Выключена", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';
//
//        parent::$db->exec("UPDATE elements SET `value` = '$burnerGVS'
//                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'burnerGVS'");
//
//
//
//
//        //Обновление состояния модуляции горелки
//            $modulation = '[{"status": "'.$this->boiler->burner_modulation.'", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';
//
//        parent::$db->exec("UPDATE elements SET `value` = '$modulation'
//                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'modulation'");
//


//        //Обновление состояния насоса
//        if ($this->boiler->pump_status == 1)
//            $pump = '[{"status": "Включен", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';
//        else
//            $pump = '[{"status": "Выключен", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';
//
//        parent::$db->exec("UPDATE elements SET `value` = '$pump'
//                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'pump'");




    // }


    /**
     * Получение всех параметров котла и выставление температуры на термостате котла в зависимости от тех параметров, которые ранее записали в БД
     */
    public function check() {

        // Смотрим какой режим у котла, авто или ручной
        if ($this->boiler->mode == 'auto') {

            //Ищем страницу для котла по id объекта
            // Смотрим температуру на улице через привязанный датчик температуры и выставляем
            // температуру котла в соответствии с таблицей соответствия температур
            $sql = parent::$db->query("SELECT boiler_auto.t_water,  boiler_auto.t_out, termostats.current FROM boiler_auto 
                                   INNER JOIN boiler ON boiler.id_object = boiler_auto.id_object
                                   INNER JOIN termostats ON termostats.id_object = boiler.id_outside_thermostat
                                   WHERE boiler.`id_object` = {$this->boiler->id_object}
                                   AND (boiler_auto.t_out <= termostats.current
                                    OR boiler_auto.t_out = (SELECT MIN(boiler_auto.t_out) FROM boiler_auto)) 
                                   ORDER BY boiler_auto.t_out DESC LIMIT 1 ");

            $boiler_autoparams = $sql->fetch(PDO::FETCH_OBJ);
            $this->boiler->target_heat_temp = $boiler_autoparams->t_water;

        } else {
            // Если ручной режим, то у котла устанавливаем температуру, которая указана в таблице ручного режима
            $sql = parent::$db->query("SELECT set_value FROM boiler_manual WHERE id_object = {$this->boiler->id_object}");
            $boiler_manualparams = $sql->fetch(PDO::FETCH_OBJ);
            $this->boiler->target_heat_temp = $boiler_manualparams->set_value;
        }

        if ($this->boiler->gateway_type == "modbus") {
            $this->sendDataToModbus(); 
            $this->reqDataFromModbus();
        } else {
            $this->sendDataToTouchonEbus();
            $this->reqDataFromTouchonEbus();
        }



        parent::$db->exec("UPDATE boiler SET `feed_heat_temp` =  {$this->boiler->feed_heat_temp},
                                `back_heat_temp` = {$this->boiler->back_heat_temp},
                                `target_heat_temp` = {$this->boiler->target_heat_temp},
                                `target_water_temp` = {$this->boiler->target_water_temp},
                                `thermostat` = {$this->boiler->thermostat},
                                `boiler` =  {$this->boiler->boiler},
                                `water_temp` = {$this->boiler->water_temp},
                                `feed_water_temp` = {$this->boiler->feed_water_temp},
                                `outsoor_temp` = {$this->boiler->outdoor_temp},
                                `error_code` =  {$this->boiler->error_code},
                                `ext_error` =  {$this->boiler->ext_error},
                                `pressure` = {$this->boiler->pressure}
                                  WHERE `id_object` = {$this->boiler->id_object}");


        //Вызываем метод заполнения параметров для страницы, у которой имееются необходимые хэндлы
        $this->fillElements();
    }

    /**
     * Установить температуру отопления для котла
     */
    public function setHeat(int $temperature) {
        if (!$this->boiler->lock) {
        $this->boiler->target_heat_temp = $temperature;
        parent::$db->exec("UPDATE boiler SET `target_heat_temp` = {$temperature}
                              WHERE `id_object` = {$this->boiler->id_object}");

        if ($this->boiler->gateway_type == 'modbus') {
           $this->setHeatTempOnBoiler($temperature);
        }else {
            file_get_contents("http://{$this->boiler->ip_address}/thermostat?cmd=set&heat=$temperature}");
    }
    }

    }


    //Установить температуру отопления для воды
    public function setWater(int $temperature)
    {
        if (!$this->boiler->lock) {
        $this->boiler->target_water_temp = $temperature;
        parent::$db->exec("UPDATE boiler SET `target_water_temp` = {$temperature}
                              WHERE `id_object` = {$this->boiler->id_object}");

        if ($this->boiler->gateway_type == 'modbus') {   
        $this->setWaterTempOnBoiler($temperature);
        } else {
                file_get_contents("http://{$this->boiler->ip_address}/thermostat?cmd=set&water=$temperature}");    
        }
    }
       
    }


    //Установить режим котла
    public function setBoiler(string $boiler)
    {

        if (!$this->boiler->lock) {
            $this->boiler->boiler = $boiler;
            parent::$db->exec("UPDATE boiler SET `boiler` =  {$this->boiler->boiler}
                                  WHERE `id_object` = {$this->boiler->id_object}");

            file_get_contents("http://{$this->boiler->ip_address}/thermostat?cmd=set&boiler=$boiler");
        }
    }


    //Установка режима работы от термостата
    public function setThermostat(string $mode)
    {
        if (!$this->boiler->lock) {
            if ($mode == 'off') {
                $thermostat = 0;
                $cmd = 'off';
            } elseif ($mode == 'on') {
                $thermostat = 1;
                $cmd = 'on';
            } else exit;

            $this->boiler->thermostat = $thermostat;
            parent::$db->exec("UPDATE boiler SET `thermostat` =  {$thermostat}
                                  WHERE `id_object` = {$this->boiler->id_object}");

            file_get_contents("http://{$this->boiler->ip_address}/thermostat?cmd=$cmd");
        }
    }


    //Установить режим котла, при котором внешние изменения не будут влиять на параметры котла, например нельзя
    //будет установить программно температуру котла, пока lock=1
    public function lockChanges(bool $lock) {

        if($lock) $this->boiler->lock = true;
        else $this->boiler->lock = false;


        parent::$db->exec("UPDATE boiler SET `automode` =  {$this->boiler->lock}
                                  WHERE `id_object` = {$this->boiler->id_object}");

    }

    



    // Отправка данных на котел ebus
    private function sendDataToTouchonEbus() {

        //Отправляем данные из БД на котел
        if ($this->boiler->thermostat == 1) $cmd = 'on';
        else $cmd = 'off';

        file_get_contents("http://{$this->boiler->ip_address}/thermostat?cmd=$cmd");

        if($this->boiler->boiler == 0) $boiler = 'off';
        else $boiler = 'on';

        file_get_contents("http://{$this->boiler->ip_address}/thermostat?cmd=set&heat={$this->boiler->target_heat_temp}".
            "&water={$this->boiler->target_water_temp}&boiler=$boiler");

    }

    // опрос котла ebus
    private function reqDataFromTouchonEbus() {

         //Опрашиваем котёл, заносим параметры в переменные и в таблицу котла
         $stateBoilerResponse = file_get_contents("http://{$this->boiler->ip_address}/thermostat?cmd=get");

         $stateBoiler = json_decode($stateBoilerResponse);
 
         $this->boiler->feed_heat_temp = $stateBoiler->feed_heat_temp;
         $this->boiler->back_heat_temp = $stateBoiler->back_heat_temp;
         $this->boiler->target_heat_temp = $stateBoiler->target_heat_temp;
         $this->boiler->target_water_temp = $stateBoiler->target_water_temp;
         $this->boiler->thermostat = $stateBoiler->thermostat;
         $this->boiler->boiler = $stateBoiler->boiler;
         $this->boiler->water_temp = $stateBoiler->water_temp;
         $this->boiler->feed_water_temp = $stateBoiler->feed_water_temp;
         $this->boiler->pressure = round($stateBoiler->pressure/1000,1);
    }


    // Отправка данных на котел с модбасом
    private function sendDataToModbus() {
       
        // - включение ручного режима котла
        $this->setManualModeOnBoiler("enable");
        // - установка температуры котла
        $this->setHeatTempOnBoiler($this->boiler->target_heat_temp);
        // - установка температуры ГВС
        $this->setWaterTempOnBoiler($this->boiler->target_heat_temp);
    }

    /**
     *  Извлечение данных для котла из таблицы регистров
     * */ 
    private function reqDataFromModbus() {

        //модуляция горелки
        $method = Objects::getMethodByAlias($this->boiler->id_object, 'flame');
        $this->boiler->flame = Action::runAction($method->id);
        //давление
        $method = Objects::getMethodByAlias($this->boiler->id_object, 'pressure');
        $this->boiler->pressure = Action::runAction($method->id);
        //скорость потока ГВС
        $method = Objects::getMethodByAlias($this->boiler->id_object, 'flow_rate');
        $this->boiler->GVS_flow_rate = Action::runAction($method->id);
        //температура котла
        $method = Objects::getMethodByAlias($this->boiler->id_object, 'feed_heat_temp');
        $this->boiler->feed_heat_temp = Action::runAction($method->id);
        //температура ГВС
        $method = Objects::getMethodByAlias($this->boiler->id_object, 'water_temp');
        $this->boiler->water_temp = Action::runAction($method->id);
        //Внешняя температура
        $method = Objects::getMethodByAlias($this->boiler->id_object, 'outdoor_temp');
        $this->boiler->outdoor_temp = Action::runAction($method->id);
        //Температура в помещении
        $method = Objects::getMethodByAlias($this->boiler->id_object, 'indoor_temp');
        $this->boiler->indoor_temp = Action::runAction($method->id);
        
        //считывание признака наличия ошибки
        $method = Objects::getMethodByAlias($this->boiler->id_object, 'error_flag');
        $this->boiler->error_flag = Action::runAction($method->id);

        if ($this->boiler->error_flag != null) {
            //Если признак ошибки есть, то записываем код ошибки
            $method = Objects::getMethodByAlias($this->boiler->id_object, 'error_code');
            $this->boiler->error_code = Action::runAction($method->id);
            //Если у котла есть функция получения расширенной ошибки
            $method = Objects::getMethodByAlias($this->boiler->id_object, 'ext_err_flag');
            $this->boiler->ext_err_flag = Action::runAction($method->id);
            if ($this->boiler->ext_err_flag != null) {
                
                $method = Objects::getMethodByAlias($this->boiler->id_object, 'error_flow_press');
                $errorFlowPress = Action::runAction($method->id);
                if ($errorFlowPress == 1)
                $this->boiler->ext_error = "Ошибка воздушного давления";

                $method = Objects::getMethodByAlias($this->boiler->id_object, 'error_flame');
                $errorFlame = Action::runAction($method->id);
                if ($errorFlame == 1)
                $this->boiler->ext_error = "Ошибка по газу/пламени";

                $method = Objects::getMethodByAlias($this->boiler->id_object, 'error_lock_control');
                $errorLockControl = Action::runAction($method->id);
                if ($errorLockControl == 1)
                $this->boiler->ext_error = "Блокировка внешнего управления";

                $method = Objects::getMethodByAlias($this->boiler->id_object, 'error_low_water');
                $errorLowWater = Action::runAction($method->id);
                if ($errorLowWater == 1)
                $this->boiler->ext_error = "Низкое давления теплоносителя";

                $method = Objects::getMethodByAlias($this->boiler->id_object, 'error_need_service');
                $errorNeedService = Action::runAction($method->id);
                if ($errorNeedService == 1)
                $this->boiler->ext_error = "Необходимо внешнее обслуживание";

                $method = Objects::getMethodByAlias($this->boiler->id_object, 'error_max_temp');
                $errorMaxTemp = Action::runAction($method->id);
                if ($errorMaxTemp == 1)
                $this->boiler->ext_error = "Превышение максимальной температуры теплоносителя";
            }
        }
    }

    //Установка параметров работы модбас
    // private function modbusSetup() {
    //     $modbus = new PhpSerialModbus;

    //     if ($this->boiler->port == 0 ) $pt = '/dev/ttyUSB0';
    //     else $pt = '/dev/ttyUSB1'; 

    //     $modbus->deviceInit($pt, 9600, 'none', 8, 1, 'none');
    //     $modbus->deviceOpen();
    //     $modbus->debug = true;

    //     return $modbus;
    // }


    /**
     * Установка режима котла для работы от внешнего термостата (контроллера) mode=enable или от внутренней логики котла mode=disable 
     */
    public function setManualModeOnBoiler($mode) {
        //Найти метод из таблицы методов, который соответствует объекту котла
        $method = Objects::getMethodByAlias($this->boiler->id_object, "manual_mode");
        Action::runAction($method->id, null, null, $mode);
    }

    /**
     * Установка температуры котла на устройстве
     */
    public function setHeatTempOnBoiler($temp) {
        $method = Objects::getMethodByAlias($this->boiler->id_object, "set_heat_temp");
        Action::runAction($method->id, null, null, $temp);
    }

      /**
     * Установка температуры ГВС на устройстве
     */
    public function setWaterTempOnBoiler($temp) {
        $method = Objects::getMethodByAlias($this->boiler->id_object, "set_water_temp");
        Action::runAction($method->id, null, null, $temp);
    }

}