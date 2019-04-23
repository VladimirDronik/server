<?php

/**
 * Класс работы с термостатами
 */
class Thermostats extends Objects
{

    private $script;
    private $id_termostat;

    private $min_threshold;
    private $max_threshold;
    private $min_alarm;
    private $max_alarm;
    private $termostat;


    function __construct($id_termost=null)
    {

        if($id_termost!=null) {
            $this->script = new Scripts();

            $this->id_termostat = $id_termost;

            //Получаем все данные термостата
            $scriptsql = parent::$db->query("SELECT current, optimal, gisteresis, thermostat, object, method_on, method_off, `min_threshold`, `max_threshold`, `min_alarm`, `max_alarm` FROM termostats
                                         WHERE id=$this->id_termostat");

            $this->termostat = $termostat = $scriptsql->fetch(PDO::FETCH_OBJ);

            $this->min_threshold = $termostat->min_threshold;
            $this->max_threshold = $termostat->max_threshold;
            $this->min_alarm = $termostat->min_alarm;
            $this->max_alarm = $termostat->max_alarm;
        }

    }


    /** Проверяем параметры нужного термостата */

    function check()
    {

        //Если термостат с фйнкцией нагрева
        if ($this->termostat->thermostat == 1)
        {

            if ($this->termostat->current >=($this->termostat->optimal))
            {
                // Вызываем метод off
                $this->script->runscript($this->termostat->object,$this->termostat->method_off);
                return 0;

            }

            if ($this->termostat->current < ($this->termostat->optimal-$this->termostat->gisteresis))
            {
                // Вызываем метод on
                $this->script->runscript($this->termostat->object,$this->termostat->method_on);
                return 1;
            }


        } else //Если термостат с функцией охлаждения
        {
            if ($this->termostat->current <=($this->termostat->optimal-$this->termostat->gisteresis))
            {
                // Вызываем метод off
                $this->script->runscript($this->termostat->object,$this->termostat->method_off);
                return 0;
            }


            if ($this->termostat->current > $this->termostat->optimal)
            {
                // Вызываем метод on
                $this->script->runscript($this->termostat->object,$this->termostat->method_on);
                return 1;
            }

        }
    }


    /** Получение температуры термостата */
    function get_temperature()
    {
        $alarm_cnt = 0;

        //Ищем к какому порту и устройствву принадлежит термостат, а также его id термометра
        $termostatsql = parent::$db->query("SELECT id_device, port, id_termometr FROM termostats
                                         WHERE id=$this->id_termostat");

        $termostat = $termostatsql->fetch(PDO::FETCH_OBJ);

        do {

            do {
                //вызываем status(int $port, int $id_device=null)
                $termometrs = Megad::status($termostat->port, 'list', $termostat->id_device);

                /**Перебираем вернувшийсяя массив - находим в нем нужный термостат, берем значение его температуры
                 * e2b5d7020000:23.62;1fa3d7020000:23.62*/
                $termometrsarray = explode(';', $termometrs);

                foreach ($termometrsarray as $termometr) {
                    $termarray = explode(':', $termometr);
                    if ($termarray[0] == $termostat->id_termometr)
                        $id_termometr = $termarray[0];
                    $termometr_value = $termarray[1];
                }

                $alarm_cnt++;

                //Если превышено число аварийных значений термометра
                if ($alarm_cnt>=10)
                {
                    //Здесь сделать вызов обработчика аварии и выходим из цикла
                    break 2;
                }

                //Проверка на аварийные занчения
            } while (($termometr_value < $this->min_alarm) || ($termometr_value > $this->max_alarm));

            //Проверка на пороговые значения
        } while (($termometr_value < $this->min_threshold) || ($termometr_value > $this->max_threshold));


        //Заносим значение термостата в БД в таблицу термостатов и в таблицу графиков
        parent::$db->query("UPDATE termostats SET `current` = $termometr_value
                                         WHERE id_termometr='$id_termometr'");

        parent::$db->query("INSERT INTO graph (`id`, `id_termostat`, `datetime`, `value`)
                                      VALUES (null, '$this->id_termostat',CONCAT(CURRENT_DATE,' ',CURRENT_TIME),'$termometr_value')");


    }




    /** Заносим в таблицу термостатов данные об установленной пользователем температуре */
    function set_temperature($id_object, $value){

        //Заносим значение термостата в БД
        parent::$db->query("UPDATE termostats SET `optimal` = $value
                                         WHERE id_object='$id_object'");

    }


    /* Установка режима отопления для термостата и изменение связанного графического элемента
       Указываем mode=режим, коорый хотим установить,
       $id_object = ид объекта, коотрый используем */
    static function set_temperature_mode($mode, $id_object){

        //Берем температуру у выбранного режима
        $modesql = parent::$db->query("SELECT `temperatures`.$mode AS temperature, `objects`.`view` AS view  FROM `temperatures` 
                                       INNER JOIN `termostats` ON `temperatures`.`id_room` = `termostats`.`room` 
                                       INNER JOIN `objects` ON `termostats`.`id_object` = `objects`.`id`
                                       WHERE `termostats`.`id_object` = $id_object");

        $result = $modesql->fetch(PDO::FETCH_OBJ);


        //Заносим значение в БД для выбранного термостата
        self::set_temperature($id_object, $result->temperature);

        $view = new Views();
        $view->update_item($result->view, $result->temperature);
    }

    /**
     * Удаление старых значений температуры в таблице графиков
     *
     * @return null
     */

    static function delete_old_values(){

        $date = parent::read_setting('graphdate');
        parent::$db->query("DELETE FROM `graph` WHERE `graph`.`datetime` = $date");
    }

}
