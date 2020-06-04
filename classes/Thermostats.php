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
    private $idObject;
    private $typeObject;
    private $placetype;
    private $usensor;
    private $name;


    /**
     * Конструктор определяет рабочие параметры у выбранного термостата
     *
     * @param int $id_termost
     */
    function __construct($idObjectTermost=null)
    {

        if($idObjectTermost!=null) {
            $this->script = new Scripts();



            //Получаем все данные термостата
            $scriptsql = parent::$db->query("SELECT  termostats.id AS id, current, optimal, gisteresis, thermostat, object, method_on, 
                                            method_off, `min_threshold`, `max_threshold`, `min_alarm`, `max_alarm`, `objects`.`type` as `type_object`,
                                            `placetype`, `usensor_id`, termostats.`name`
                                            FROM termostats 
                                            INNER JOIN objects ON  id_object=objects.id
                                            WHERE id_object=$idObjectTermost");



            $this->termostat = $termostat = $scriptsql->fetch(PDO::FETCH_OBJ);

            $this->idObject = $idObjectTermost;
            $this->id_termostat = $termostat->id;
            $this->min_threshold = $termostat->min_threshold;
            $this->max_threshold = $termostat->max_threshold;
            $this->min_alarm = $termostat->min_alarm;
            $this->max_alarm = $termostat->max_alarm;
            $this->typeObject = $termostat->type_object;
            $this->placetype = $termostat->placetype;
            $this->usensor = $termostat->usensor_id;
            $this->name = $termostat->name;

        }

    }


    /**
     * Проверяем параметры термостата с которым рабоатем
     *
     * @return int
     *
     */

    function check()
    {

        //Если термостат с фйнкцией нагрева
        if ($this->termostat->thermostat == 1)
        {

            if ($this->termostat->current >=($this->termostat->optimal))
            {
                // Вызываем метод off
                if($this->termostat->method_off)
                Action::runAction($this->termostat->method_off, 'termostat');
                return 0;

            }

            if ($this->termostat->current < ($this->termostat->optimal-$this->termostat->gisteresis))
            {
                // Вызываем метод on
                if($this->termostat->method_on)
                Action::runAction($this->termostat->method_on, 'termostat');
                return 1;
            }


        } else //Если термостат с функцией охлаждения
        {
            if ($this->termostat->current <=($this->termostat->optimal-$this->termostat->gisteresis))
            {
                // Вызываем метод off
                if($this->termostat->method_off)
                Action::runAction($this->termostat->method_off, 'termostat');
                return 0;
            }


            if ($this->termostat->current > $this->termostat->optimal)
            {
                // Вызываем метод on
                if($this->termostat->method_on)
                Action::runAction($this->termostat->method_on, 'termostat');
                return 1;
            }

        }
    }


    /**
     * Получение температуры термостата
     *
     * @return void
     */
    function get_temperature()
    {
        $alarm_cnt = 0;
        $alarm_cnt_avail = 0;


        if(($this->placetype == 'port') || ($this->placetype == '1wire')) {

            //Ищем к какому порту и устройству принадлежит термостат, а также его id термометра
            $termostatsql = parent::$db->query("SELECT termostats.id_object AS id_object,
                                                   ports.id_device AS device, 
                                                   ports.num_port AS port, 
                                                   id_termometr,
                                                   `name`
                                              FROM termostats
                                              INNER JOIN ports     
                                              ON ports.object = termostats.id_object      
                                              WHERE termostats.id=$this->id_termostat");

            $termostat = $termostatsql->fetch(PDO::FETCH_OBJ);

            do {

                do {

                    //Если id термометра задан, то тогда это массив с термометрами
                    if ($this->placetype == '1wire') {

                        //вызываем status(int $port, int $device=null)
                        $termometrs = Megad::status($termostat->port, 'list', $termostat->device);


                        /*Перебираем вернувшийсяя массив - находим в нем нужный термостат, берем значение его температуры
                          e2b5d7020000:23.62;1fa3d7020000:23.62*/
                        $termometrsarray = explode(';', $termometrs);


                        foreach ($termometrsarray as $termometr) {
                            $termarray = explode(':', $termometr);

                            if ($termarray[0] == $termostat->id_termometr)
                                $id_termometr = $termarray[0];

                            $termometr_value = $termarray[1];
                        }

                    } else //термометр висит прямо на порту
                    {
                        $termometrs = Megad::status($termostat->port, 'get', $termostat->device);
                        $termometrsarray = explode(':', $termometrs);
                        $id_termometr = $termostat->id_termometr;
                        $termometr_value = $termometrsarray[1];
                    }



                    $alarm_cnt_avail++;

                    //Если превышено число пороговых значений термометра, значит он не работает
                    if ($alarm_cnt_avail >= 10) {
                        //Здесь сделать вызов обработчика аварии и выходим из цикла
                        System::addLog('error', 'Термостат "' . $this->name . '" недоступен', 'sensor');

                        $error = true;
                        break 2;
                    }


                    //Проверка на пороговые значения
                } while (($termometr_value < $this->min_threshold) || ($termometr_value > $this->max_threshold));

                $alarm_cnt++;

                //Если превышено число аварийных значений термометра
                if ($alarm_cnt >= 10) {
                    //Здесь сделать вызов обработчика аварии и выходим из цикла
                    System::addLog('error', 'Аварийное значение термостата "' . $this->name . '" T='.$termometr_value, 'sensor');

                    Messages::send(1, 'Аварийное значение термостата '.$this->name.', T='.$termometr_value);

                    $error = true;
                    break 1;
                }

                    //Проверка на аварийные занчения
            } while (($termometr_value < $this->min_alarm) || ($termometr_value > $this->max_alarm));


        } else { //Термостат входит в состав унивесального датчика

            $result = Usensors::checkI2C($this->usensor);
            $termometr_value = $result['temp'];

        }


        //TODO: проверка на слишком резкое изменеие значения

        if (!$error) {
            //Заносим значение термостата в БД в таблицу термостатов и в таблицу графиков
            parent::$db->query("UPDATE termostats SET `current` = $termometr_value
                                         WHERE id=$this->id_termostat");

            Graphs::insertToTermostats($this->id_termostat, $termometr_value);
        }

        //Отдаем значение визуальному компоненту
        $sql = parent::$db->query("SELECT id FROM `view_items` WHERE `id_object`= $this->idObject");
        $viewItem = $sql->fetch(PDO::FETCH_OBJ);

        if($viewItem->id) {
            $view = new Views();
            $view->updateItem($viewItem->id);
        }
    }




    /**
     * Заносим в таблицу термостатов данные об установленной пользователем температуре
     *
     * @param int $idObject - id термостата
     * @param float $value - Значение выбраной темпертуры
     */
    function set_temperature($idObject, $value){

        //Заносим значение термостата в БД
        parent::$db->query("UPDATE termostats SET `optimal` = $value
                                         WHERE id_object='$idObject'");

    }


    /**
     * Установка режима отопления для термостата и изменение связанного графического элемента
     *
     * @param string $mode - режим, коорый хотим установить
     * @param int $idObject - id объекта, к которому привязан термостат
     * @return void
     */
    static function set_temperature_mode($mode, $idObject){

        //Берем температуру у выбранного режима
        $modesql = parent::$db->query("SELECT `temperatures`.$mode AS temperature FROM `temperatures` 
                                       INNER JOIN `termostats` ON `temperatures`.`id_room` = `termostats`.`room` 
                                       INNER JOIN `objects` ON `termostats`.`id_object` = `objects`.`id`
                                       LEFT JOIN `view_items` ON `view_items`.`id_object` = `termostats`.`id_object`
                                       WHERE `termostats`.`id_object` = $idObject");

        $result = $modesql->fetch(PDO::FETCH_OBJ);


        //Заносим значение в БД для выбранного термостата
        self::set_temperature($idObject, $result->temperature);

        if($result->view) {
            $view = new Views();
            $view->updateItem($result->view, $result->temperature);
        }
    }



    /**
     * Удаление старых значений температуры в таблице графиков
     *
     * @return void
     */
    static function deleteGraphOldValues(){

        Graphs::deleteOldValues('graph_termostats');
    }

}
