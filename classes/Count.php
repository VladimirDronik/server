<?php

use Graphs;

class Count extends Megad
{

    /**
     * Считываем показания счетчика и заносим в БД
     *
     * @param int $idCount - ИД счетчика, с которым будем работать
     **/
    static function getCount($idCount)
        {
        //Узнаем на каком девайсе и на каком порту висит счетчик
            $sql = parent::$db->query("SELECT `id_device`, `id_port`, `impulse` 
                                       FROM counts
                                       WHERE id = $idCount");

            $count = $sql->fetch(PDO::FETCH_OBJ);
            $idPort = $count->id_port;
            $idDevice = $count->id_device;
            $impulse = $count->impulse;


        //Читаем текущее количество импульсов
        $countImpulse = 500;//parent::status($idPort,'get',$idDevice,1);

        $currentValue = $countImpulse*$impulse;

        //Заносим количество импульсов в таблицу счетчиков
            parent::$db->query("UPDATE counts SET 
                                `today_value` = $currentValue
                                WHERE id = $idCount");

            Graphs::insertToCounts($idCount, $currentValue);


        }



    /**
     * Обнуляем текущее значение счетчика
     *
     * @param int $idCount - ИД счетчика, с которым будем работать
     */
    static function resetCount($idCount)
        {
            parent::$db->query("UPDATE counts SET 
                                `total_value` = `total_value`+`today_value`,
                                `today_value` = 0 WHERE id = $idCount");
        }

}