<?php

/**
 * Класс работы с термостатами
 */


class Hygrostats extends Objects
{

    private $script;
    private $id_hygrostat;

    private $min_threshold;
    private $max_threshold;
    private $min_alarm;
    private $max_alarm;
    private $hygrostat;
    private $idObject;
    private $typeObject;
    private $placetype;
    private $usensor;
    private $hitepro_dev;
    private $name;


    /**
     * Конструктор определяет рабочие параметры у выбранного гигростата
     *
     * @param int $idObjectHygrost
     */
    function __construct($idObjectHygrost=null)
    {

        if($idObjectHygrost!=null) {
            $this->script = new Scripts();

            //Получаем все данные гигростата
            $scriptsql = parent::$db->query("SELECT  hygrostats.id AS id, current, optimal, gisteresis, type, object, method_on, 
                                            method_off, `min_threshold`, `max_threshold`, `min_alarm`, `max_alarm`, `objects`.`type` as `type_object`,
                                            `placetype`, `usensor_id`, hygrostats.`name`, `subdev_id`
                                            FROM termostats 
                                            INNER JOIN objects ON  id_object=objects.id
                                            WHERE id_object=$idObjectHygrost");



            $this->hygrostat = $hygrostat = $scriptsql->fetch(PDO::FETCH_OBJ);

            $this->idObject = $idObjectHygrost;
            $this->id_termostat = $hygrostat->id;
            $this->min_threshold = $hygrostat->min_threshold;
            $this->max_threshold = $hygrostat->max_threshold;
            $this->min_alarm = $hygrostat->min_alarm;
            $this->max_alarm = $hygrostat->max_alarm;
            $this->typeObject = $hygrostat->type_object;
            $this->placetype = $hygrostat->placetype;
            $this->usensor = $hygrostat->usensor_id;
            $this->name = $hygrostat->name;
            $this->hitepro_dev = $hygrostat->subdev_id;

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
                $property = $this->hygrostat->current;
                break;

            case 'optimal' :
                $property = $this->hygrostat->optimal;
                break;

            case 'gisteresis' :
                $property = $this->hygrostat->gisteresis;
                break;

            case 'type' :
                if($this->hygrostat->type == 1)
                $property = 'осушение';
                else
                    $property = 'увлажнение';
                break;

            case 'min_threshold' :
                $property = $this->hygrostat->min_threshold;
                break;

            case 'max_threshold' :
                $property = $this->hygrostat->max_threshold;
                break;

            case 'min_alarm' :
                $property = $this->hygrostat->min_alarm;
                break;

            case 'max_alarm' :
                $property = $this->hygrostat->max_alarm;
                break;

            case 'room' :
                //TODO: Здесь сделать запрос названия комнаты по его id
                break;

            case 'battery' :
                //TODO: Здесь сделать запрос заряда батареи для беспроводного гигростата
                break;

        }

        return $property;

    }


    /**
     * Установка значения свойства для гигростата
     * @param $property
     * @param $value
     */
    public function setProperty($property, $value)
    {

        //Для нагрева или охлаждения модифицируем значение
        if($property == 'type') {

            if($value == 'осушение')
                $value = 1;
            elseif($value == 'увлажнение') $value = 0;
        }

        parent::$db->query("UPDATE hygrostats SET $property = '$value'
                                         WHERE id=$this->id_hygrostat");

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

        if($this->hygrostat->current) {
        //Если гигростат с функцией увлажнения
            if ($this->hygrostat->type == 0)
            {

                if ($this->hygrostat->current >=($this->hygrostat->optimal))
                {
                    if ($object->status == 'ON') $sendMessage = true;

                    $object->setStatus('OFF',true,false);

                    // Вызываем метод off
                    if($this->hygrostat->method_off)
                    Action::runAction($this->hygrostat->method_off, 'hygrostat', $this->idObject, null, $sendMessage);
                    return 0;

                }

                if ($this->hygrostat->current < ($this->hygrostat->optimal-$this->hygrostat->gisteresis))
                {
                    if ($object->status == 'OFF') $sendMessage = true;

                    $object->setStatus('ON',true,false);

                    // Вызываем метод on
                    if($this->hygrostat->method_on)
                    Action::runAction($this->hygrostat->method_on, 'hygrostat', $this->idObject, null, $sendMessage);
                    return 1;
                }


            } else //Если гигростат с функцией осушения
            {
                if ($this->hygrostat->current <=($this->hygrostat->optimal-$this->hygrostat->gisteresis))
                {
                    if ($object->status == 'ON') $sendMessage = true;

                    $object->setStatus('OFF',true,false);

                    // Вызываем метод off
                    if($this->hygrostat->method_off)
                    Action::runAction($this->hygrostat->method_off, 'hygrostat', $this->idObject, null, $sendMessage);
                    return 0;
                }


                if ($this->hygrostat->current > $this->hygrostat->optimal)
                {
                    if ($object->status == 'OFF') $sendMessage = true;

                    $object->setStatus('ON',true,false);

                    // Вызываем метод on
                    if($this->hygrostat->method_on)
                    Action::runAction($this->hygrostat->method_on, 'hygrostat', $this->idObject, null, $sendMessage);
                    return 1;
                }

            }
        }
    }


    /**
     * Получение значение гигростата
     *
     * @return void
     */
    function get_humidity()
    {
        $alarm_cnt = 0;
        $alarm_cnt_avail = 0;

        Events::exicute($this->idObject, 'onCheck');


        if ($this->placetype == 'usensor') { //Термостат входит в состав унивесального датчика

            $result = Usensors::checkI2C($this->usensor);
            $humidity_value = $result['hum'];

            $this->checkValue($humidity_value);

        } else { //если датчик в составе сервера хитпро

            $humidity_value =  HitePro::getHiteProCommand(null, null, null, $this->hitepro_dev);
            $this->checkValue($humidity_value);

        }



            //проверка на слишком резкое изменеие значения
            $sql = parent::$db->query("SELECT MAX(id), `value` FROM graph_hygrostats
                                      WHERE id_termostat = $this->id_hygrostat
                                      AND (NOW() - `datetime`) < 300 ");

            $hygrostat= $sql->fetch(PDO::FETCH_OBJ);

            //Если разница меньше 10 % или если данные были слишком давно, то заносим в графики значения
            if ((abs($hygrostat->value - $humidity_value) < 10) || (!$hygrostat->value)) {

                //Заносим значение гигростата в БД в таблицу гигростатов и в таблицу графиков

                parent::$db->query("UPDATE hygrostats SET `current` = $humidity_value
                                         WHERE id=$this->id_hygrostat");

                Graphs::insertToTermostats($this->id_hygrostat, $humidity_value);

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
     * Проверка снятого с гигростата значения на пороговое и формирование аварии
     *
     * @param float $hygrostat_value - снятое с гигростата значение
     */
    private function checkValue($hygrostat_value) {

        //Проверка на пороговое значение (доступность)
        if(($hygrostat_value < $this->min_threshold) || ($hygrostat_value > $this->max_threshold)) {

            System::addLog('error', 'Гигростат "' . $this->name . '" недоступен', 'sensor');
            Messages::send(1, 'Гигростат '.$this->name.' недоступен');
        }elseif (($hygrostat_value < $this->min_alarm) || ($hygrostat_value > $this->max_alarm)) {
            //Проверка на аварийные значения

            System::addLog('error', 'Аварийное значение гигростата "' . $this->name . '" T='.$hygrostat_value, 'sensor');
            Messages::send(1, 'Аварийное значение гигростата '.$this->name.', T='.$hygrostat_value);

            Events::exicute($this->idObject, 'onThreshold');

        }
    }


    /**
     * Заносим в таблицу гигростатов данные об установленной пользователем температуре
     *
     * @param int $idObject - id термостата
     * @param float $value - Значение выбраной темпертуры
     */
    function set_humiduty($idObject, $value){

        //Заносим значение термостата в БД
        parent::$db->query("UPDATE hygrostats SET `optimal` = $value
                                         WHERE id_object='$idObject'");

    }


//    /**
//     * Установка режима отопления для термостата и изменение связанного графического элемента
//     *
//     * @param string $mode - режим, коорый хотим установить
//     * @param int $idObject - id объекта, к которому привязан термостат
//     * @return void
//     */
//    static function set_temperature_mode($mode, $idObject){
//
//        //Берем температуру у выбранного режима
//        $modesql = parent::$db->query("SELECT `temperatures`.$mode AS temperature FROM `temperatures`
//                                       INNER JOIN `termostats` ON `temperatures`.`id_room` = `termostats`.`room`
//                                       INNER JOIN `objects` ON `termostats`.`id_object` = `objects`.`id`
//                                       LEFT JOIN `view_items` ON `view_items`.`id_object` = `termostats`.`id_object`
//                                       WHERE `termostats`.`id_object` = $idObject");
//
//        $result = $modesql->fetch(PDO::FETCH_OBJ);
//
//
//        //Заносим значение в БД для выбранного термостата
//        self::set_temperature($idObject, $result->temperature);
//
//        if($result->view) {
//            $view = new Views();
//            $view->updateItem($result->view, $result->temperature);
//        }
//    }



    /**
     * Удаление старых значений температуры в таблице графиков
     *
     * @return void
     */
    static function deleteGraphOldValues(){

        Graphs::deleteOldValues('graph_hygrostats');
    }

}
