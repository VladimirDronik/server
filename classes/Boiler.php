<?php

/**
 * Класс для работы с котлом
 */
class Boiler extends System
{

    private $boiler;

    function __construct($idObject)
    {
        $sql = parent::$db->query("SELECT * FROM boiler WHERE `id_object` = $idObject");
        $this->boiler = $sql->fetch(PDO::FETCH_OBJ);

        //Определяем параметры контроллера бойлера
        $sql = parent::$db->query("SELECT devtypes.nam AS type_controller, devices.ip_address AS address, devices.port AS port FROM devtypes 
                                   INNER JOIN devices ON devtypes.id = devices.type WHERE devices.id = {$this->boiler->id_controller}");
        $boiler_controller = $sql->fetch(PDO::FETCH_OBJ);
        $this->boiler->idObject = $idObject;
        $this->boiler->type_controller = $boiler_controller->type_controller;
        $this->boiler->address_controller = $boiler_controller->address;
        $this->boiler->port = $boiler_controller->port;

    }

    /**
     * Функция заполнения данными таблицы с элементами
     */
    private function fillElements()
    {
        //TODO:: добавить контур ГВС

        //Обновление значения подачи для всех элементов с таким типом
        $cooliantSupply = '[{"status":"'.$this->boiler->feed_heat_temp.'°С"}]';
        parent::$db->exec("UPDATE elements SET `value` = '$cooliantSupply' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'csupply'");
        Labels::setValue($this->boiler->feed_heat_temp.'°С', "температура подачи", $this->boiler->idObject);


        //Обновление значения обратки для всех элементов с таким типом
        $cooliantReturn = '[{"status":"'.$this->boiler->back_heat_temp.'°С"}]';
        parent::$db->exec("UPDATE elements SET `value` = '$cooliantReturn' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'creturn'");
        Labels::setValue($this->boiler->back_heat_temp.'°С', "температура обратки", $this->boiler->idObject);


        //Обновление состояния давления теплоносителя
        $pressure = '[{"status":"'.$this->boiler->pressure.'"}]';
        parent::$db->exec("UPDATE elements SET `value` = '$pressure' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'pressure'");
        Labels::setValue($this->boiler->pressure.'b', "давление", $this->boiler->idObject);        


        //Обновление целевой температуры котла
        $target_heat_temp = '[{"status":"'.$this->boiler->target_heat_temp.'°С"}]';
        parent::$db->exec("UPDATE elements SET `value` = '$target_heat_temp' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'heat_temp'");

        //Обновление целевой температуры контура воды
        $water_temp = '[{"status":"'.$this->boiler->water_temp.'°С", "settings": "true"}]';
        parent::$db->exec("UPDATE elements SET `value` = '$water_temp' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'water_temp'");

        //Обновление уличной температуры
        $outdoor_temp = '[{"status":"'.$this->boiler->outdoor_temp.'°С"]';
        parent::$db->exec("UPDATE elements SET `value` = '$outdoor_temp' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'outdoor_temp'");

        //Обновление кода ошибки
        $error_code = '[{"status":"'.$this->boiler->error_code.'"]';
            parent::$db->exec("UPDATE elements SET `value` = '$error_code' 
                                       WHERE `id_object` = {$this->boiler->id_object} AND handle = 'error_code'");
        Labels::setValue($this->boiler->error_code, "код ошибки", $this->boiler->idObject); 

        //Описание расширенной ошибки, если есть
        $ext_error = '[{"status":"'.$this->boiler->ext_error.'"]';
            parent::$db->exec("UPDATE elements SET `value` = '$ext_error' 
                                       WHERE `id_object` = {$this->boiler->id_object} AND handle = 'ext_error'");
        //TODO:: сделать вставку в Label текста о расшифрованной ошибке

        //Обновление значения статуса котла для всех элементов с таким типом
        if($this->boiler->boiler == 1)
        $state = '[{"status":"on"}]';
        else
            $state = '[{"status":"off"}]';

        parent::$db->exec("UPDATE elements SET `value` = '$state' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'state'");




        //Обновление режима работы котла (авто или ручной)
        if($this->boiler->mode == 'auto') {
            $auto = '[{"status": "on", "settings": "true"}]';
            $manual = '[{"status": "off", "settings": "true"}]';
        } else {
            $auto = '[{"status": "off", "settings": "true"}]';
            $manual = '[{"status": "on", "settings": "true"}]';
        }

        parent::$db->exec("UPDATE elements SET `value` = '$auto'
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'automode'");

        parent::$db->exec("UPDATE elements SET `value` = '$manual'
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'manualmode'");




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




    }


    /**
     * Получение всех параметров котла
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

        
        if ($this->boiler->type_controller == "nevoton") {
            $this->sendDataToNevoton(); 
            $this->reqDataFromNevoton();
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

    //Установить температуру отопления для котла
    public function setHeat(int $temperature) {
        if (!$this->boiler->lock) {
        $this->boiler->target_heat_temp = $temperature;
        parent::$db->exec("UPDATE boiler SET `target_heat_temp` = {$temperature}
                              WHERE `id_object` = {$this->boiler->id_object}");

        if ($this->boiler->type_controller == "nevoton") {
            
            $modbus = $this->modbusSetup();

            //включение систем котла
            $result = $modbus->sendQuery(9, 1, "03E7", 1);
            //Включение ручного режима (отключение термостата)
            $modbus->sendQuery(9, 1, "03EC", 1);
             //Запись температуры котла
            $modbus->sendQuery($this->boiler->address, 6, "0x03FA", $this->boiler->target_heat_temp);
            $modbus->deviceClose();
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

        if ($this->boiler->type_controller == "nevoton") {
          
            $modbus = $this->modbusSetup();

            //включение систем котла
            $result = $modbus->sendQuery(9, 1, "03E7", 1);
            //Включение ручного режима (отключение термостата)
            $modbus->sendQuery(9, 1, "03EC", 1);
             //Запись температуры ГВС
             $modbus->sendQuery($this->boiler->address, 6, "0x0400", $this->boiler->target_water_temp);
             $modbus->deviceClose();
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

    /**
     * Установить режим работы котла auto или manual
     * В зависимости от этого режима котел будет работат следующим образом:
     * auto - будет оцениваться температура с внешнего датчика id_outside_thermostat и сравниваться
     * с значениями в таблице boiler_auto. В зависимости от этого будет выставляться температура
     * теплоносителя.
     * manual - будет выставляться температура теплоносителя в зависимости от значений, которые указаны в
     * boiler_manual
     */
    static public function setMode($id_object, $mode) {

        //меняем режим котла на auto
        parent::$db->exec("UPDATE boiler SET `mode` = '".$mode."'
                                       WHERE `id_object` = $id_object");
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


    // Отправка данных на котел Невотон
    private function sendDataToNevoton() {

        $modbus = $this->modbusSetup();

        //Включение ручного режима (отключение термостата)
        $modbus->sendQuery(9, 1, "03EC", 1);
        //Запись температуры котла
        $modbus->sendQuery($this->boiler->address, 6, "0x03FA", $this->boiler->target_heat_temp);
        //Запись температуры ГВС
        $modbus->sendQuery($this->boiler->address, 6, "0x0400", $this->boiler->target_water_temp);

        $modbus->deviceClose();
    }

    // Опрос котла Невотон
    private function reqDataFromNevoton() {

      $modbus = $this->modbusSetup();

        //модуляция горелки
        $this->boiler->flame = $modbus->sendQuery($this->boiler->address, 3, "03EF", 1);
        //давление
        $this->boiler->pressure = $modbus->sendQuery($this->boiler->address, 3, "03F0", 1);
        //скорость потока ГВС
        $this->boiler->GVS_flow_rate = $modbus->sendQuery($this->boiler->address, 3, "03F1", 1);
        //температура котла
        $this->boiler->feed_heat_temp = $modbus->sendQuery($this->boiler->address, 3, "03F2", 1);
        //температура ГВС
        $this->boiler->water_temp = $modbus->sendQuery($this->boiler->address, 3, "03F3", 1);
        //Внешняя температура
        $this->boiler->outdoor_temp = $modbus->sendQuery($this->boiler->address, 3, "03F4", 1);
        
        //считывание признака ошибки
        $err = $modbus->sendQuery($this->boiler->address, 1, "03F9", 1);
        if ($err != null) {
            //Если признак ошибки есть, то записываем код ошибки
            $this->boiler->error_code =  $modbus->sendQuery($this->boiler->address, 3, "03F6", 1);
            //Если у котла есть функция получения расширенной ошибки
            $ext_err_flag = $modbus->sendQuery($this->boiler->address, 1, "03FA", 1);
            if ( $ext_err_flag != null) {
                
                if ($modbus->sendQuery($this->boiler->address, 1, "03FB", 1) == 1)
                $this->boiler->ext_error = "Ошибка воздушного давления";

                if ($modbus->sendQuery($this->boiler->address, 1, "3FC", 1) == 1)
                $this->boiler->ext_error = "Ошибка по газу/пламени";

                if ($modbus->sendQuery($this->boiler->address, 1, "3FD", 1) == 1)
                $this->boiler->ext_error = "Блокировка внешнего управления";

                if ($modbus->sendQuery($this->boiler->address, 1, "3FE", 1) == 1)
                $this->boiler->ext_error = "Ошибка низкого давления воды";

                if ($modbus->sendQuery($this->boiler->address, 1, "3FF", 1) == 1)
                $this->boiler->ext_error = "Необходимо внешнее обслуживание";

                if ($modbus->sendQuery($this->boiler->address, 1, "400", 1) == 1)
                $this->boiler->ext_error = "Ошибка превышения максимальной температуры воды";

            }
        }
        $modbus->deviceClose();

    }

    //Установка параметров работы модбас
    private function modbusSetup() {
        $modbus = new PhpSerialModbus;

        if ($this->boiler->port == 0 ) $pt = '/dev/ttyUSB0';
        else $pt = '/dev/ttyUSB1'; 

        $modbus->deviceInit($pt, 9600, 'none', 8, 1, 'none');
        $modbus->deviceOpen();
        $modbus->debug = true;

        return $modbus;
    }

}