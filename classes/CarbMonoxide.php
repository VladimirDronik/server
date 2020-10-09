<?php

/**
 * Класс работы с датчиками угарного газа
 */
class CarbMonoxide extends Megad
{

    private $sensor;
    private $idObject;

    function __construct($idObject=null)
    {
        if($idObject!=null) {

            //получаем все данные датчика
            $sql = parent::$db->query("SELECT carbmonoxide.id AS id, ports.id_device, ports.num_port,
                                        carbmonoxide.low_value, carbmonoxide.low_method, carbmonoxide.high_value,
                                        carbmonoxide.high_method, carbmonoxide.calibration
                                        FROM carbmonoxide INNER JOIN ports 
                                        ON ports.object = carbmonoxide.id_object
                                        WHERE id_object = $idObject");

            $this->sensor = $sensor = $sql->fetch(PDO::FETCH_OBJ);
            $this->idObject = $idObject;

        }
    }

    /**
     * Получение значения датчика CO2
     */
    public function check()
    {
        for ( $i = 0; $i < 30; $i++ )
        {
            $vals[] = parent::status($this->sensor->num_port, 'get', $this->sensor->id_device);
            usleep(10000);
        }

        $avg = array_sum($vals) / count($vals);

        if ( $avg > 517 )
            $val = $avg - 517;
        else
            $val = 0;

        $raw_val = ($val / 1024 * 3.4); // Разница в Вольтах между нормальным и текущим напряжением на выходе датчика
        $ppm = round($raw_val / 100E3 * (1E9 / 1) / $this->sensor->calibration, 2);


        //Заносим данные в таблицу устройства
        $sql = parent::$db->query("UPDATE `carbmonoxide` SET `cur_value` = '$ppm' 
                                   WHERE `carbmonoxide`.`id` = $this->sensor->id;");


        //Проверяем на высокое пороговое значение
        if($ppm >= $this->sensor->high_value) {

            if($this->sensor->high_method)
                Action::runAction($this->sensor->high_method, 'carbmonoxide', $this->idObject, null, false);

            //Отправляем сообщение
            Messages::send($this->idObject, true, '2');

        }elseif ($ppm >= $this->sensor->low_value) { //Проверяем на низкое пороговое значение

            if($this->sensor->low_method)
                Action::runAction($this->sensor->low_method, 'carbmonoxide', $this->idObject, null, false);

            //Отправляем сообщение
            Messages::send($this->idObject, true, '1');
        }

        return $ppm;
    }
}