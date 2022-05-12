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
    private $hitepro_dev;
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
                                            `placetype`, `usensor_id`, termostats.`name`, `subdev_id`
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
            $this->hitepro_dev = $termostat->subdev_id;


        }

    }

    /**
     * Проверка условий выполнения действия при возникновении события
     * @param $comparison
     */
    public function getProperty($event)
    {


        switch ($event->property) {

            case 'current' :
                $property = $this->termostat->current;
                break;

            case 'optimal' :
                $property = $this->termostat->optimal;
                break;

            case 'gisteresis' :
                $property = $this->termostat->gisteresis;
                break;

            case 'type' :
            case 'thermostat':
                if($this->termostat->termostat == 1)
                $property = 'нагрев';
                else
                    $property = 'охлаждение';
                break;

            case 'min_threshold' :
                $property = $this->termostat->min_threshold;
                break;

            case 'max_threshold' :
                $property = $this->termostat->max_threshold;
                break;

            case 'min_alarm' :
                $property = $this->termostat->min_alarm;
                break;

            case 'max_alarm' :
                $property = $this->termostat->max_alarm;
                break;

            case 'room' :
                //TODO: Здесь сделать запрос названия комнаты по его id
                break;

            case 'battery' :
                //TODO: Здесь сделать запрос заряда батареи для беспроводного термостата
                break;

        }

        return $property;

    }


    /**
     * Установка значения свойства для термостата
     * @param $property
     * @param $value
     */
    public function setProperty($property, $value)
    {

        //Для нагрева или охлаждения модифицируем значение
        if($property == 'type') {
            $property = 'thermostat';

            if($value == 'нагрев')
                $value = 1;
            elseif($value == 'охлаждение') $value = 0;
        }

        parent::$db->query("UPDATE termostats SET $property = '$value'
                                         WHERE id=$this->id_termostat");

    }



    /**
     * Проверяем параметры термостата с которым рабоатем
     *
     * @return int
     *
     */

    function check()
    {

        $sendMessage = false;

        $object = new Objects();
        $object->select($this->idObject);

        Events::exicute($this->idObject, 'onStatus');

        if($this->termostat->current) {
        //Если термостат с фйнкцией нагрева
            if ($this->termostat->thermostat == 1)
            {

                if ($this->termostat->current >=($this->termostat->optimal))
                {
                    if ($object->status == 'ON') $sendMessage = true;

                    $object->setStatus('OFF',true,false);

                    // Вызываем метод off
                    if($this->termostat->method_off)
                    Action::runAction($this->termostat->method_off, 'termostat', $this->idObject, null, $sendMessage);
                    return 0;

                }

                if ($this->termostat->current < ($this->termostat->optimal-$this->termostat->gisteresis))
                {
                    if ($object->status == 'OFF') $sendMessage = true;

                    $object->setStatus('ON',true,false);

                    // Вызываем метод on
                    if($this->termostat->method_on)
                    Action::runAction($this->termostat->method_on, 'termostat', $this->idObject, null, $sendMessage);
                    return 1;
                }


            } else //Если термостат с функцией охлаждения
            {
                if ($this->termostat->current <=($this->termostat->optimal-$this->termostat->gisteresis))
                {
                    if ($object->status == 'ON') $sendMessage = true;

                    $object->setStatus('OFF',true,false);

                    // Вызываем метод off
                    if($this->termostat->method_off)
                    Action::runAction($this->termostat->method_off, 'termostat', $this->idObject, null, $sendMessage);
                    return 0;
                }


                if ($this->termostat->current > $this->termostat->optimal)
                {
                    if ($object->status == 'OFF') $sendMessage = true;

                    $object->setStatus('ON',true,false);

                    // Вызываем метод on
                    if($this->termostat->method_on)
                    Action::runAction($this->termostat->method_on, 'termostat', $this->idObject, null, $sendMessage);
                    return 1;
                }

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

        Events::exicute($this->idObject, 'onCheck');


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
                    if ($alarm_cnt_avail >= 5) {
                        //Здесь сделать вызов обработчика аварии и выходим из цикла
                        System::addLog('error', 'Термостат "' . $this->name . '" недоступен', 'sensor');

                        Messages::send(1, 'Термостат '.$this->name.' недоступен');
                        Events::exicute($this->idObject, 'onError');

                        $error = true;
                        break 2;
                    }


                    //Проверка на пороговые значения
                } while (($termometr_value < $this->min_threshold) || ($termometr_value > $this->max_threshold));

                $alarm_cnt++;

                //Если превышено число аварийных значений термометра
                if ($alarm_cnt >= 3) {

                    //Здесь сделать вызов обработчика аварии и выходим из цикла
                    System::addLog('error', 'Аварийное значение термостата "' . $this->name . '" T='.$termometr_value, 'sensor');

                    Messages::send(1, 'Аварийное значение термостата '.$this->name.', T='.$termometr_value);
                    Events::exicute($this->idObject, 'onThreshold');

                   // $error = true;
                    break 1;
                }

                    //Проверка на аварийные занчения
            } while (($termometr_value < $this->min_alarm) || ($termometr_value > $this->max_alarm));


        } elseif ($this->placetype == 'usensor') { //Термостат входит в состав унивесального датчика

            $result = Usensors::checkI2C($this->usensor);
            $termometr_value = $result['temp'];

            $this->checkValue($termometr_value);

        } else { //если датчик в составе сервера хитпро

            $termometr_value =  HitePro::getHiteProCommand(null, null, null, $this->hitepro_dev);
            $this->checkValue($termometr_value);

        }



        if (!$error) {

            //проверка на слишком резкое изменение значения
            $sql = parent::$db->query("SELECT MAX(id), `value` FROM graph_termostats 
                                      WHERE id_termostat = $this->id_termostat
                                      AND (NOW() - `datetime`) < 300 ");

            $termostat = $sql->fetch(PDO::FETCH_OBJ);

            //Если разница меньше 10 град или если данные были слишком давно, то заносим в графики значения
            if ((abs($termostat->value - $termometr_value) < 10) || (!$termostat->value)) {

                //Заносим значение термостата в БД в таблицу термостатов и в таблицу графиков

                parent::$db->query("UPDATE termostats SET `current` = $termometr_value
                                         WHERE id=$this->id_termostat");

                //Заносим температуру в таблицу элементов
                $temperature = '[{"status":"'.$termometr_value.'°С"}]';
                parent::$db->exec("UPDATE elements SET `value` = '$temperature' 
                                   WHERE `id_object` = {$this->idObject} AND handle = 'temperature'");

                Graphs::insertToTermostats($this->id_termostat, $termometr_value);

            }

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
     * Проверка снятого с термостата значения на пороговое и формирование аварии
     *
     * @param float $termometr_value - снятое с термостата значение
     */
    private function checkValue($termometr_value) {

        //Проверка на пороговое значение (доступность)
        if(($termometr_value < $this->min_threshold) || ($termometr_value > $this->max_threshold)) {

            System::addLog('error', 'Термостат "' . $this->name . '" недоступен', 'sensor');
            Messages::send(1, 'Термостат '.$this->name.' недоступен');
        }elseif (($termometr_value < $this->min_alarm) || ($termometr_value > $this->max_alarm)) {
            //Проверка на аварийные значения

            System::addLog('error', 'Аварийное значение термостата "' . $this->name . '" T='.$termometr_value, 'sensor');
            Messages::send(1, 'Аварийное значение термостата '.$this->name.', T='.$termometr_value);

            Events::exicute($this->idObject, 'onThreshold');

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
