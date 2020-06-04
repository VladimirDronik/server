<?php

/**
 * Класс работы сдатчиками движения
 */
class Motionsensor extends Objects
{

    public $name;
    public $method_normal;
    public $method_eco;
    public $method_night;
    public $method_morning;
    public $method_evening;
    public $method_guard;
    public $lightstat;
    public $equality;
    public $lightvalue;
    public $method_light;

    function __construct($idObject)
    {
        //Получаем все данные датчика движения
        $sql = parent::$db->query("SELECT * FROM `motionsensors` WHERE `id_object` = $idObject");

        $motionsensor = $sql->fetch(PDO::FETCH_OBJ);

        $this->name = $motionsensor->name;
        $this->method_normal = $motionsensor->method_normal;
        $this->method_eco = $motionsensor->method_eco;
        $this->method_night = $motionsensor->method_night;
        $this->method_morning = $motionsensor->method_morning;
        $this->method_evening = $motionsensor->method_evening;
        $this->method_guard = $motionsensor->method_guard;
        $this->lightstat = $motionsensor->lightstat;
        $this->equality = $motionsensor->equality;
        $this->lightvalue = $motionsensor->lightvalue;
        $this->method_light = $motionsensor->method_light;

    }

}