<?php

/**
 * Класс работы с термостатами
 */
class Thermostats extends Objects
{

    private $script;

    function __construct()
    {
        $this->script =  new Scripts();
    }


    /** Проверяем параметры нужного термостата, на входе id термостата, который проверяем */

    function check(int $id)
    {

        $scriptsql = parent::$db->query("SELECT current, optimal, gisteresis, thermostat, object, method_on, method_off FROM termostats
                                         WHERE id=$id");

        $termostat = $scriptsql->fetch(PDO::FETCH_OBJ);

        //Если термостат с фйнкцией нагрева
        if ($termostat->thermostat == 1)
        {

            if ($termostat->current >=($termostat->optimal+$termostat->gisteresis))
            {
                // Вызываем метод off
                $this->script->runscript($termostat->object,$termostat->method_off);
                return 0;

            }

            if ($termostat->current < $termostat->optimal)
            {
                // Вызываем метод on
                $this->script->runscript($termostat->object,$termostat->method_on);
                return 1;
            }


        } else //Если термостат с функцией охлаждения
        {
            if ($termostat->current <=($termostat->optimal-$termostat->gisteresis))
            {
                // Вызываем метод off
                $this->script->runscript($termostat->object,$termostat->method_off);
                return 0;
            }


            if ($termostat->current > $termostat->optimal)
            {
                // Вызываем метод on
                $this->script->runscript($termostat->object,$termostat->method_on);
                return 1;
            }

        }
    }


    /** Получение температуры термостата */
    function get_temperature(int $id_termostat)
    {

        //Ищем к какому порту и устройствву принадлежит термостат, а также его id термометра
        $termostatsql = parent::$db->query("SELECT id_device, port, id_termometr FROM termostats
                                         WHERE id=$id_termostat");

        $termostat = $termostatsql->fetch(PDO::FETCH_OBJ);

        //вызываем status(int $port, int $id_device=null)
        $termometrs = Megad::status($termostat->port,'list', $termostat->id_device);

        /**Перебираем вернувшийсяя массив - находим в нем нужный термостат, берем значение его температуры
        e2b5d7020000:23.62;1fa3d7020000:23.62*/
        $termometrsarray = explode(';',$termometrs);

        foreach ($termometrsarray as $termometr) {
            $termarray = explode(':',$termometr);
            if ($termarray[0]==$termostat->id_termometr)
               $id_termometr = $termarray[0];
               $termometr_value = $termarray[1];
        }

        //Заносим значение термостата в БД в таблицу термостатов и в таблицу графиков
        parent::$db->query("UPDATE termostats SET `current` = $termometr_value
                                         WHERE id_termometr='$id_termometr'");

        parent::$db->query("INSERT INTO graph (`id`, `id_termostat`, `datetime`, `value`)
                                      VALUES (null, '$id_termostat',CONCAT(CURRENT_DATE,' ',CURRENT_TIME),'$termometr_value')");


    }




    /** Заносим в таблицу термостатов данные об установленной пользователем температуре */
    function set_temperature(int $id_object, $value){

        //Заносим значение термостата в БД
        parent::$db->query("UPDATE termostats SET `optimal` = $value
                                         WHERE id_object='$id_object'");

    }

}