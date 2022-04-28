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
    }

    /**
     * Функция заполнения данными таблицы с элементами
     */
    private function fillElements()
    {

        //Обновление значения подачи для всех элементов с таким типом
        $cooliantSupply = '[{"status":"'.$this->boiler->feed_heat_temp.'°С"}]';
        parent::$db->exec("UPDATE elements SET `value` = '$cooliantSupply' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'csupply'");



        //Обновление значения обратки для всех элементов с таким типом
        $cooliantReturn = '[{"status":"'.$this->boiler->back_heat_temp.'°С"}]';
        parent::$db->exec("UPDATE elements SET `value` = '$cooliantReturn' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'creturn'");

        //Обновление состояния давления теплоносителя
        $pressue = '[{"status":"'.$this->boiler->pressue.'"}]';
        parent::$db->exec("UPDATE elements SET `value` = '$pressue' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'pressue'");


        //Обновление целевой температуры котла
        $target_heat_temp = '[{"status":"'.$this->boiler->target_heat_temp.'"}]';
        parent::$db->exec("UPDATE elements SET `value` = '$target_heat_temp' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'heat_temp'");

        //Обновление целевой температуры контура воды
        $target_water_temp = '[{"status":"'.$this->boiler->target_water_temp.'"}]';
        parent::$db->exec("UPDATE elements SET `value` = '$target_water_temp' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'water_temp'");


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
            // температуру котла в соответствии с таблицей
            $sql = parent::$db->query("SELECT boiler_auto.t_water,  boiler_auto.t_out, termostats.current FROM boiler_auto 
                                        INNER JOIN boiler ON boiler.id_object = boiler_auto.id_object
                                        INNER JOIN termostats ON termostats.id = boiler.id_outside_thermostat
                                        WHERE boiler.`id_object` = {$this->boiler->id_object}
                                        AND boiler_auto.t_out <= termostats.current 
                                        ORDER BY boiler_auto.t_out DESC LIMIT 1 ");

            $boiler_autoparams = $sql->fetch(PDO::FETCH_OBJ);
            $this->boiler->target_heat_temp = $boiler_autoparams->t_water;

        } else {
            // Если ручной режим, то у котла устанавливаем температуру, которая указаана в таблице
            $sql = parent::$db->query("SELECT set_value FROM boiler_manual WHERE id_object = {$this->boiler->id_object}");
            $boiler_manualparams = $sql->fetch(PDO::FETCH_OBJ);
            $this->boiler->target_heat_temp = $boiler_manualparams->set_value;
        }


        //ОТправляем данные из БД на котел
        if ($this->boiler->thermostat == 1) $cmd = 'on';
        else $cmd = 'off';

        file_get_contents("http://{$this->boiler->ip_address}/thermostat?cmd=$cmd");

        if($this->boiler->boiler == 0) $boiler = 'off';
        else $boiler = 'on';

        file_get_contents("http://{$this->boiler->ip_address}/thermostat?cmd=set&heat={$this->boiler->target_heat_temp}".
            "&water={$this->boiler->target_water_temp}&boiler=$boiler");


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


        parent::$db->exec("UPDATE boiler SET `feed_heat_temp` =  {$this->boiler->feed_heat_temp},
                                `back_heat_temp` = {$this->boiler->back_heat_temp},
                                `target_heat_temp` = {$this->boiler->target_heat_temp},
                                `target_water_temp` = {$this->boiler->target_water_temp},
                                `thermostat` = {$this->boiler->thermostat},
                                `boiler` =  {$this->boiler->boiler},
                                `water_temp` = {$this->boiler->water_temp},
                                `feed_water_temp` = {$this->boiler->feed_water_temp}
                                  WHERE `id_object` = {$this->boiler->id_object}");


        //Вызываем метод заполнения параметров для страницы, у которой имееются необходимые хэндлы
        $this->fillElements();
    }

    //Установить температуру отопления для котла
    public function setHeat(int $temperature) {

        if(!$this->boiler->lock) {
            $this->boiler->target_heat_temp = $temperature;
            parent::$db->exec("UPDATE boiler SET `target_heat_temp` = {$temperature}
                                  WHERE `id_object` = {$this->boiler->id_object}");

            file_get_contents("http://{$this->boiler->ip_address}/thermostat?cmd=set&heat=$temperature}");
        }

    }


    //Установить температуру отопления для воды
    public function setWater(int $temperature)
    {

        if (!$this->boiler->lock) {

            $this->boiler->target_water_temp = $temperature;
            parent::$db->exec("UPDATE boiler SET `target_water_temp` = {$temperature}
                                  WHERE `id_object` = {$this->boiler->id_object}");

            file_get_contents("http://{$this->boiler->ip_address}/thermostat?cmd=set&water=$temperature}");

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
        parent::$db->exec("UPDATE boiler SET `mode` = $mode
                                       WHERE `id_object` = $id_object");
    }


}