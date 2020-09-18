<?php

/**
 * Класс работы с светостатами
 */


class Lightstats extends Objects
{

    private $script;
    private $id_lightstat;

    private $min_threshold;
    private $max_threshold;
    private $min_alarm;
    private $max_alarm;
    private $lightstat;
    private $idObject;
    private $typeObject;
    private $placetype;
    private $usensor;
    private $name;


    /**
     * Конструктор определяет рабочие параметры у выбранного светостата
     *
     * @param int $id_lightstat
     */
    function __construct($idObjectLightstat=null)
    {

        if($idObjectLightstat!=null) {
            $this->script = new Scripts();


            //Получаем все данные светостата
            $scriptsql = parent::$db->query("SELECT  lightstats.id AS id, current, optimal, gisteresis, mode, object, method_on, 
                                            method_off, `min_threshold`, `max_threshold`, `min_alarm`, `max_alarm`, `objects`.`type` as `type_object`,
                                            `placetype`, `usensor_id`, lightstats.`name`
                                            FROM lightstats 
                                            INNER JOIN objects ON  id_object=objects.id
                                            WHERE id_object=$idObjectLightstat");



            $this->lightstat = $lightstat = $scriptsql->fetch(PDO::FETCH_OBJ);

            $this->idObject = $idObjectLightstat;
            $this->id_lightstat = $lightstat->id;
            $this->min_threshold = $lightstat->min_threshold;
            $this->max_threshold = $lightstat->max_threshold;
            $this->min_alarm = $lightstat->min_alarm;
            $this->max_alarm = $lightstat->max_alarm;
            $this->typeObject = $lightstat->type_object;
            $this->placetype = $lightstat->placetype;
            $this->usensor = $lightstat->usensor_id;
            $this->name = $lightstat->name;

        }

    }


    /**
     * Проверяем параметры светостата с которым рабоатем
     *
     * @return int
     *
     */

    function check()
    {

        //Если светостат с реакцией на посветление
        if ($this->lightstat->mode == 1)
        {

            if ($this->lightstat->current >=($this->lightstat->optimal))
            {
                // Вызываем метод off
                if($this->lightstat->method_off)
                Action::runAction($this->lightstat->method_off, 'lightstat', $this->idObject);
                return 0;

            }

            if ($this->lightstat->current < ($this->lightstat->optimal-$this->lightstat->gisteresis))
            {
                // Вызываем метод on
                if($this->lightstat->method_on)
                Action::runAction($this->lightstat->method_on, 'lightstat', $this->idObject);
                return 1;
            }


        } else //Если светостат с реакцией на потемнение
        {
            if ($this->lightstat->current <=($this->lightstat->optimal-$this->lightstat->gisteresis))
            {
                // Вызываем метод off
                if($this->lightstat->method_off)
                Action::runAction($this->lightstat->method_off, 'lightstat', $this->idObject);
                return 0;
            }


            if ($this->lightstat->current > $this->lightstat->optimal)
            {
                // Вызываем метод on
                if($this->lightstat->method_on)
                Action::runAction($this->lightstat->method_on, 'lightstat', $this->idObject);
                return 1;
            }

        }
    }


    /**
     * Получение значение светостата
     *
     * @return int
     */
    function getLux()
    {
        $alarm_cnt = 0;


        if($this->placetype == 'port') {



            //Ищем к какому порту и устройству принадлежит светостат
            $lightstatsql = parent::$db->query("SELECT lightstats.id_object AS id_object,
                                                  ports_SDA.num_port AS SDA, ports_SCL.num_port AS SCL, lightstats.port_SCL, lightstats.`name`,
                                                  devices.ip_address AS ip
                                              FROM lightstats     
                                              INNER JOIN ports AS ports_SDA
                                              ON ports_SDA.id = lightstats.port_SDA
                                              INNER JOIN ports AS ports_SCL
                                              ON ports_SCL.id = lightstats.port_SCL
                                              INNER JOIN devices
                                              ON  ports_SDA.id_device = devices.id
                                              WHERE lightstats.id=$this->id_lightstat");

            $lightstat = $lightstatsql->fetch(PDO::FETCH_OBJ);

            do {

                do {

                        define("SCL", $lightstat->SCL);
                        define("SDA", $lightstat->SDA);
                        define("MD", "http://{$lightstat->ip}/sec/?");

                        // Вариант реализации I2C: 1 - полностью программный; 2 - частично аппаратный (прошивка 3.43beta1 и выше)
                        define("V", "2");

                      $lux = get_lux1750();

                    $alarm_cnt++;

                    //Если превышено число аварийных значений светостата
                    if ($alarm_cnt >= 10) {
                        //Здесь сделать вызов обработчика аварии и выходим из цикла
                        System::addLog('error', 'Светостат "' . $this->name . '" не доступен', 'sensor');


                        $error = true;
                        break 2;
                    }

                    //Проверка на аварийные занчения
                } while (($lux < $this->min_alarm) || ($lux > $this->max_alarm));

                //Проверка на пороговые значения
            } while (($lux < $this->min_threshold) || ($lux > $this->max_threshold));

        } else { //Светостат входит в состав унивесального датчика

            $result = Usensors::checkI2C($this->usensor);
            $lux = $result['lux'];

        }


        //TODO: проверка на слишком резкое изменеие значения

        if (!$error) {
            //Заносим значение светостата в БД в таблицу светостатов и в таблицу графиков
            parent::$db->query("UPDATE lightstats SET `current` = $lux
                                         WHERE id=$this->id_lightstat");

            Graphs::insertToLightstats($this->id_lightstat, $lux);
        }

        //Отдаем значение визуальному компоненту
        $sql = parent::$db->query("SELECT id FROM `view_items` WHERE `id_object`= $this->idObject");
        $viewItem = $sql->fetch(PDO::FETCH_OBJ);

        if($viewItem->id) {
            $view = new Views();
            $view->updateItem($viewItem->id);
        }

        return $lux;
    }

    /**
     * Получение значения светостата из таблицы
     */
    public static function getValueFromDB($idLightstat)
    {
        $lightstatsql = parent::$db->query("SELECT `current` FROM lightstats WHERE id_object = $idLightstat");
        if($lightstat = $lightstatsql->fetch(PDO::FETCH_OBJ));
        return $lightstat->current;

    }


}
