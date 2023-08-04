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
        if($idObjectTermost!=null)
        {
            $this->script = new Scripts();

            //Получаем все данные термостата
            $scriptsql = parent::$db->query("SELECT termostats.id AS id,
                                                    current,
                                                    optimal,
                                                    gisteresis,
                                                    thermostat,
                                                    object,
                                                    method_on,
                                                    method_off,
                                                    `min_threshold`,
                                                    `max_threshold`,
                                                    `min_alarm`,
                                                    `max_alarm`,
                                                    `objects`.`type` as `type_object`,
                                                    `placetype`,
                                                    `usensor_id`,
                                                    termostats.`name`,
                                                    `subdev_id`
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
            $this->optimal = $termostat->optimal;
        }
    }

    /**
     * Проверка условий выполнения действия при возникновении события
     * @param $comparison
     */
    public function getProperty($event)
    {
        switch ($event->property)
        {

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
        if($property == 'type') 
        {
            $property = 'thermostat';
            if($value == 'нагрев') $value = 1;
            elseif($value == 'охлаждение') $value = 0;
        }
        parent::$db->query("UPDATE termostats SET $property = '$value' WHERE id=$this->id_termostat");
    }

    /**
     * Проверяем параметры термостата с которым рабоатем
     *
     * @return int
     */
    function check()
    {
        $sendMessage = false;
        $object = new Objects();
        $object->select($this->idObject);

        // Events::exicute($this->idObject, 'onStatus');

        if($this->termostat->current)
        {
        //Если термостат с фйнкцией нагрева
            if ($this->termostat->thermostat == 1)
            {
                if (floatval($this->termostat->current) >= $this->termostat->optimal+$this->termostat->gisteresis/2)
                {
                    if (mb_strtoupper($object->status) == 'ON') $sendMessage = true;
                    $object->setStatus('OFF',true,false);
                    Messages::sendByObject($this->idObject, $sendMessage);
                    $sendMessage = false;

                    // Вызываем метод off
                    if($this->termostat->method_off)
                    {
                        $object = new Objects();
                        $object->select($this->termostat->object);
                        if (mb_strtoupper($object->status) == 'ON') $sendMessage = true;
                        Action::runAction($this->termostat->method_off, 'termostat', $this->idObject, null, false);
                        Messages::sendByObject($this->termostat->object, $sendMessage);
                    }
                    return 0;
                }

                if (floatval($this->termostat->current) <= $this->termostat->optimal-$this->termostat->gisteresis/2)
                {
                    if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                    $object->setStatus('ON',true,false);
                    Messages::sendByObject($this->idObject, $sendMessage);
                    $sendMessage = false;

                    // Вызываем метод on
                    if($this->termostat->method_on)
                    {
                        $object = new Objects();
                        $object->select($this->termostat->object);
                        if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                        Action::runAction($this->termostat->method_on, 'termostat', $this->idObject, null, false);
                        Messages::sendByObject($this->termostat->object, $sendMessage);
                    }
                    return 1;
                }
            } 
            else //Если термостат с функцией охлаждения
            {
                if (floatval($this->termostat->current) <= $this->termostat->optimal-$this->termostat->gisteresis/2)
                {
                    if (mb_strtoupper($object->status)== 'ON') $sendMessage = true;
                    $object->setStatus('OFF',true,false);
                    Messages::sendByObject($this->idObject, $sendMessage);
                    $sendMessage = false;

                    // Вызываем метод off
                    if($this->termostat->method_off)
                    {
                        $object = new Objects();
                        $object->select($this->termostat->object);
                        if (mb_strtoupper($object->status) == 'ON') $sendMessage = true;
                        Action::runAction($this->termostat->method_off, 'termostat', $this->idObject, null, false);
                        Messages::sendByObject($this->termostat->object, $sendMessage);
                    }
                    return 0;
                }

                if (floatval($this->termostat->current) >= $this->termostat->optimal+$this->termostat->gisteresis/2)
                {
                    if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                    $object->setStatus('ON',true,false);
                    Messages::sendByObject($this->idObject, $sendMessage);
                    $sendMessage = false;

                    // Вызываем метод on
                    if($this->termostat->method_on)
                    {
                        $object = new Objects();
                        $object->select($this->termostat->object);
                        if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                        Action::runAction($this->termostat->method_on, 'termostat', $this->idObject, null, false);
                        Messages::sendByObject($this->termostat->object, $sendMessage);
                    }
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
        $error = false;

        Events::exicute($this->idObject, 'onCheck');

        if(($this->placetype == 'port') || ($this->placetype == '1wire'))
        {

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
            
            //Если id термометра задан, то тогда это массив с термометрами
            if ($this->placetype == '1wire')
            {
                //вызываем status(int $port, int $device=null)
                $termometrs = Megad::status($termostat->port, 'list', $termostat->device);

                /*Перебираем вернувшийсяя массив - находим в нем нужный термостат, берем значение его температуры
                e2b5d7020000:23.62;1fa3d7020000:23.62*/
                $termometrsarray = explode(';', $termometrs);

                foreach ($termometrsarray as $termometr) 
                {
                    $termarray = explode(':', $termometr);
                    if ($termarray[0] == $termostat->id_termometr) $id_termometr = $termarray[0];
                    $termometr_value = $termarray[1];
                }
            } 
            //термометр висит прямо на порту
            else
            {
                $termometrs = Megad::status($termostat->port, 'get', $termostat->device);
                $termometrsarray = explode(':', $termometrs);
                $id_termometr = $termostat->id_termometr;
                $termometr_value = $termometrsarray[1];
            }
        } 
        //Термостат входит в состав унивесального датчика
        elseif ($this->placetype == 'usensor') 
        { 
            $result = Usensors::checkI2C($this->usensor);
            $termometr_value = $result['temp'];
        }

        $error = $this->checkValue($termometr_value);

        if (!$error) 
        {
            //Заносим значение термостата в БД в таблицу термостатов и в таблицу графиков
            parent::$db->query("UPDATE termostats
            SET `current` = $termometr_value
            WHERE id=$this->id_termostat");

            //Заносим температуру в таблицу элементов
            $temperature = '[{"status":"' . $termometr_value . '°С"}]';
            parent::$db->exec("UPDATE elements
                               SET `value` = '$temperature' 
                               WHERE `id_object` = {$this->idObject}
                               AND handle = 'temperature'");
            
            $this->termostat->current = $termometr_value;
            
            $sql = parent::$db->query("SELECT `value` from graph_termostats 
                                       WHERE `id_termostat` = $this->id_termostat 
                                       ORDER BY id DESC LIMIT 1");
            $termometr_prev_value = $sql->fetch(PDO::FETCH_OBJ);

            if ((floatval($termometr_value) >= $termometr_prev_value->value+0.5) ||
                (floatval($termometr_value) <= $termometr_prev_value->value-0.5))
            {
                Graphs::insertToTermostats($this->id_termostat, $termometr_value);
            }
        }

        //Отдаем значение визуальному компоненту
        $sql = parent::$db->query("SELECT id FROM `view_items` WHERE `id_object`= $this->idObject");
        $viewItem = $sql->fetch(PDO::FETCH_OBJ);

        if(isset($viewItem->id))
        {
            $view = new Views();
            $view->updateItem($viewItem->id);
        }
       
        return $termometr_value;
    }

    /**
     * Проверка снятого с термостата значения на пороговое и формирование аварии
     *
     * @param float $termometr_value - снятое с термостата значение
     */
    private function checkValue($termometr_value)
    {
        //Проверяем является ли значение числом
        if(!is_numeric($termometr_value))
        {
            System::addLog('error', 
                'Термостат "' . $this->name . '" (ID ' . $this->idObject .
                '). Некорректное значение ' . $termometr_value . '.',
                'sensor');
            // Messages::send(1,
            //     'Термостат "' . $this->name . '" (ID ' . $this->idObject .
            //     '). Некорректное значение: ' . $termometr_value . '°C.');
            return $error = true;
        }
        //Проверяем входит ли значение в диапазон измерений
        elseif (($termometr_value < $this->min_threshold) || ($termometr_value > $this->max_threshold))
        {
            System::addLog('error', 
                'Термостат "' . $this->name . '" (ID ' . $this->idObject .
                '). Значение ' . $termometr_value . ' выходит за пределы измерения.',
                'sensor');
            return $error = true;
        }
        //Проверяем входит ли значение в диапазон аварийных значений
        else 
        {
            if ($termometr_value > $this->max_alarm) 
            {
                System::addLog('warning',
                    'Термостат "' . $this->name . '" (ID ' . $this->idObject .
                    '). Значение ' . $humidity . ' ед. выше аварийного порога.',
                    'sensor');
            }

            if ($termometr_value < $this->min_alarm)
            {
                System::addLog('warning',
                    'Термостат "' . $this->name . '" (ID ' . $this->idObject .
                    '). Значение ' . $termometr_value . ' ед. ниже аварийного порога.',
                    'sensor');
            }
            return $error = false;
        }
    }

    /**
     * Заносим в таблицу термостатов данные об установленной пользователем температуре
     *
     * @param int $idObject - id термостата
     * @param float $value - Значение выбраной темпертуры
     */
    function set_temperature($idObject, $value)
    {
        //Заносим значение термостата в БД
        parent::$db->query("UPDATE termostats SET `optimal` = $value WHERE id_object='$idObject'");
    }

    /**
     * Установка режима отопления для термостата и изменение связанного графического элемента
     *
     * @param string $mode - режим, коорый хотим установить
     * @param int $idObject - id объекта, к которому привязан термостат
     * @return void
     */
    static function set_temperature_mode($mode, $idObject)
    {
        //Берем температуру у выбранного режима
        $modesql = parent::$db->query("SELECT `temperatures`.$mode AS temperature FROM `temperatures` 
                                       INNER JOIN `termostats` ON `temperatures`.`id_room` = `termostats`.`room` 
                                       INNER JOIN `objects` ON `termostats`.`id_object` = `objects`.`id`
                                       LEFT JOIN `view_items` ON `view_items`.`id_object` = `termostats`.`id_object`
                                       WHERE `termostats`.`id_object` = $idObject");

        $result = $modesql->fetch(PDO::FETCH_OBJ);

        //Заносим значение в БД для выбранного термостата
        self::set_temperature($idObject, $result->temperature);

        if(isset($result->view))
        {
            $view = new Views();
            $view->updateItem($result->view, $result->temperature);
        }
    }

    /**
     * Удаление старых значений температуры в таблице графиков
     *
     * @return void
     */
    static function deleteGraphOldValues()
    {
        Graphs::deleteOldValues('graph_termostats');
    }

}
