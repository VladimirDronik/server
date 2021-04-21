<?php

/**
 * Класс для работы с котлом
 */
class Boiler extends System
{

    private $boiler;

    /*
    private $id;
    private $name;
    private $type;
    private $cooliantSupply;
    private $cooliantReturn;
    private $state;
    private $mode;
    private $burner;
    private $returnGVS;
    private $bernerModulation;
    private $pumpStatus;
    private $pressue;
    */

    function __construct($idObject)
    {
        $sql = parent::$db->query("SELECT * FROM boiler WHERE `id_object` = $idObject");
        $this->boiler = $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Функция заполнения данными таблицу с элементами
     */
    public function fillElements()
    {


        //Обновление значения подачи для всех элементов с таким типом
        $cooliantSupply = '[{"status":"'.$this->boiler->cooliant_supply.'&#176;С"}]';
        parent::$db->exec("UPDATE elements SET `value` = '$cooliantSupply' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'csupply'");



        //Обновление значения обратки для всех элементов с таким типом
        $cooliantReturn = '[{"status":"'.$this->boiler->cooliant_return.'&#176;С"}]';
        parent::$db->exec("UPDATE elements SET `value` = '$cooliantReturn' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'creturn'");



        //Обновление значения статуса котла для всех элементов с таким типом
        if($this->boiler->state == 1)
        $state = '[{"status":"on"}]';
        else
            $state = '[{"status":"off"}]';

        parent::$db->exec("UPDATE elements SET `value` = '$state' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'state'");




        //Обновление режима работы котла (вато или ручной)
        if($this->boiler->mode == 'auto') {
            $auto = '[{"status": "on"]';
            $manual = '[{"status": "off", "settings": "true"}]';
        } else {
            $auto = '[{"status": "off"]';
            $manual = '[{"status": "on", "settings": "true"}]';
        }

        parent::$db->exec("UPDATE elements SET `value` = '$auto' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'automode'");

        parent::$db->exec("UPDATE elements SET `value` = '$manual' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'manualmode'");





        //Обновление состояния горелки для котла
        if ($this->boiler->burner == 1)
        $burner = '[{"status": "Включена", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';
        else
            $burner = '[{"status": "Выключена", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';

        parent::$db->exec("UPDATE elements SET `value` = '$burner' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'burner'");





        //Обновление состояния горелки ГВС
        if ($this->boiler->burner_GVS == 1)
            $burnerGVS = '[{"status": "Включена", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';
        else
            $burnerGVS = '[{"status": "Выключена", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';

        parent::$db->exec("UPDATE elements SET `value` = '$burnerGVS' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'burnerGVS'");




        //Обновление состояния модуляции горелки
            $modulation = '[{"status": "'.$this->boiler->burner_modulation.'", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';

        parent::$db->exec("UPDATE elements SET `value` = '$modulation' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'modulation'");




        //Обновление состояния насоса
        if ($this->boiler->pump_status == 1)
            $pump = '[{"status": "Включен", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';
        else
            $pump = '[{"status": "Выключен", "wh-color": "#00ffbb", "bl_color": "#00ffbb"}]';

        parent::$db->exec("UPDATE elements SET `value` = '$pump' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'pump'");




        //Обновление состояния давления теплоносителя
        $pressue = '[{"status":"'.$this->boiler->pressue.'"}]';
        parent::$db->exec("UPDATE elements SET `value` = '$pressue' 
                                   WHERE `id_object` = {$this->boiler->id_object} AND handle = 'pressue'");
    }

}