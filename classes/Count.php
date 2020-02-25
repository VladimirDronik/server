<?php

use Graphs;

class Count extends Device
{


    /**
     * Узнаем на каком девайсе и на каком порту висит счетчик
     *
     * @param int $idCount - id счетчика
     */
    static function getDevicePort($idCountObject)
    {
        $sql = parent::$db->query("SELECT ports.`id_device` AS device,
                                       ports.num_port AS port,
                                       counts.id AS id,
                                       `impulse`   
                                       FROM counts
                                       INNER JOIN ports     
                                       ON ports.object = counts.id_object
                                       WHERE counts.id_object = $idCountObject");

        return $sql->fetch(PDO::FETCH_OBJ);
    }


    /**
     * Считываем показания счетчика и заносим в БД
     *
     * @param int $idCount - ИД счетчика, с которым будем работать
     **/
    static function getCount($idCountObject)
        {

            $count = self::getDevicePort($idCountObject);

            $idPort = $count->port;
            $idDevice = $count->device;
            $impulse = $count->impulse;


        //Читаем текущее количество импульсов
            $countImpulse = Megad::status($idPort,'get',$idDevice,1);

        $currentValue = $countImpulse*$impulse;

        //Заносим количество единиц счетчика в таблицу счетчиков
            parent::$db->query("UPDATE counts SET 
                                `today_value` = $currentValue
                                WHERE id_object = $idCountObject");

            Graphs::insertToCounts($count->id, $currentValue);


        }



    /**
     * Обнуляем текущее значение счетчика
     *
     * @param int $idCountObject - ИД счетчика, с которым будем работать
     */
    static function resetCount($idCountObject)
        {
            $sql = parent::$db->query("SELECT `counts`.`id` AS id, `counts`.`type`, `today_value` FROM counts 
                                       INNER JOIN `objects` ON `objects`.`id` = `counts`.`id_object` 
                                       WHERE `objects`.`id` = $idCountObject");

            $count = $sql->fetch(PDO::FETCH_OBJ);

            //Если счетчик воды, то значение преобразовываем в м3
            if($count->type == 'water')
                $today = $count->today_value / 1000;
            else
                $today = $count->today_value;

            parent::$db->query("UPDATE counts SET 
                                `total_value` = `total_value`+$today,
                                `today_value` = 0 WHERE id = $count->id");


            $count = self::getDevicePort($idCountObject);

            $idPort = $count->port;
            $idDevice = $count->device;

            //Обнуление значения счетчика порта
            Megad::resetCount($idDevice, $idPort);
        }

}