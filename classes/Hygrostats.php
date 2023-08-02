<?php

/**
 * Класс работы с термостатами
 */

class Hygrostats extends Objects
{
    private static $hygrostat = null;

    /**
     * Конструктор определяет рабочие параметры у выбранного гигростата
     *
     * @param int $idObject
     */
    function __construct($idObject=null)
    {
        if($idObject != null) {
            //Получаем все данные гигростата
            $sql = parent::$db->query("SELECT hygrostats.id AS id_hygrostat,
                                              hygrostats.id_object AS idObject,
                                              current,
                                              optimal,
                                              gisteresis,
                                              hygrostats.type AS type,
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
                                              hygrostats.`name`,
                                              `subdev_id`
                                       FROM hygrostats 
                                       INNER JOIN objects ON id_object=objects.id
                                       WHERE id_object=$idObject");

            self::$hygrostat = $sql->fetch(PDO::FETCH_OBJ);
        }
    }

    /**
     * Проверяем параметры термостата с которыми работаем
     *
     * @return int
     *
     */
    function check()
    {
        $hygrostat = self::$hygrostat;
        $sendMessage = false;
        $object = new Objects();
        $object->select($hygrostat->idObject);

        // Events::exicute($this->idObject, 'onStatus');

        if($hygrostat->current) 
        {
            //Если гигростат с функцией осушения
            if ($hygrostat->type == 0)
            {
                if ($hygrostat->current >= $hygrostat->optimal)
                {
                    if ($object->status == 'OFF') $sendMessage = true;
                    $object->setStatus('ON',true,false);
                    Messages::sendByObject($hygrostat->idObject, $sendMessage);
                    $sendMessage = false;
                    
                    // Вызываем метод on
                    if($hygrostat->method_on)
                    {
                        $object = new Objects();
                        $object->select($hygrostat->object);
                        if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                        Action::runAction($hygrostat->method_on, 'hygrostat', $hygrostat->object, null, false);
                        Messages::sendByObject($hygrostat->object, $sendMessage);
                    }
                    return 1;
                }
            
                if ($hygrostat->current < $hygrostat->optimal)
                {
                    if ($object->status == 'ON') $sendMessage = true;
                    $object->setStatus('OFF',true,false);
                    Messages::sendByObject($hygrostat->idObject, $sendMessage);
                    $sendMessage = false;
                    
                    // Вызываем метод off
                    if($hygrostat->method_off)
                    {
                        $object = new Objects();
                        $object->select($hygrostat->object);
                        if (mb_strtoupper($object->status) == 'ON') $sendMessage = true;
                        Action::runAction($hygrostat->method_off, 'hygrostat', $hygrostat->object, null, false);
                        Messages::sendByObject($hygrostat->object, $sendMessage);
                    }
                    return 0;
                }
            } 
            else //Если гигростат с функцией увлажнения
            {
                if ($hygrostat->current >= $hygrostat->optimal)
                {
                    if ($object->status == 'ON') $sendMessage = true;
                    $object->setStatus('OFF',true,false);
                    Messages::sendByObject($hygrostat->idObject, $sendMessage);
                    $sendMessage = false;
                    
                    // Вызываем метод off
                    if($hygrostat->method_off)
                    {
                        $object = new Objects();
                        $object->select($hygrostat->object);
                        if (mb_strtoupper($object->status) == 'ON') $sendMessage = true;
                        $object->setStatus('OFF',true,true);
                        Messages::sendByObject($hygrostat->object, $sendMessage);
                    }
                    return 0;
                }

                if ($hygrostat->current < $hygrostat->optimal)
                {
                    if ($object->status == 'OFF') $sendMessage = true;
                    $object->setStatus('ON',true,false);
                    Messages::sendByObject($hygrostat->idObject, $sendMessage);
                    $sendMessage = false;

                    // Вызываем метод on
                    if($hygrostat->method_on)
                    {
                        $object = new Objects();
                        $object->select($hygrostat->object);
                        if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                        $object->setStatus('ON',true,true);
                        Messages::sendByObject($hygrostat->object, $sendMessage);
                    }
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
        $hygrostat = self::$hygrostat;

        if ($hygrostat->placetype == 'usensor') { //Термостат входит в состав унивесального датчика

            $result = Usensors::checkI2C($hygrostat->usensor_id);
            $humidity = $result['hum'];
        } 

        $error = self::checkValue($humidity);

        if (!$error)
        {
            //Если значение влажности изменилось более чем на половину гистерезиса, то пишем в БД
            if (($humidity > $hygrostat->current+$hygrostat->gisteresis/2) || ($humidity < $hygrostat->current-$hygrostat->gisteresis/2)) 
            {
                //Заносим значение гигростата в БД в таблицу гигростатов и в таблицу графиков
                parent::$db->query("UPDATE hygrostats SET `current` = $humidity WHERE `id_object` = $hygrostat->idObject");
                Graphs::insertToHygrostats($hygrostat->id_hygrostat, $humidity);
                $hygrostat->current = $humidity;
            }
        }

        //Отдаем значение визуальному компоненту
        $sql = parent::$db->query("SELECT id FROM `view_items` WHERE `id_object`= $hygrostat->idObject");
        $viewItem = $sql->fetch(PDO::FETCH_OBJ);

        if(isset($viewItem->id)) 
        {
            $view = new Views();
            $view->updateItem($viewItem->id);
        }
    }

    /**
     * Проверка снятого с гигростата значения на пороговое и формирование аварии
     *
     * @param float $humidity - снятое с гигростата значение
     */
    private function checkValue($humidity) 
    {
        $hygrostat = self::$hygrostat;

        //Проверяем является ли значение числом
        if(!is_numeric($humidity))
        {
            System::addLog('error', 
                'Гигростат "'.$hygrostat->name.'" (ID '.$hygrostat->idObject.
                '). Некорректное значение '.$humidity.'.',
                'sensor');
            return $error = true;
        }
        //Проверяем входит ли значение в диапазон измерений
        elseif (($humidity < $hygrostat->min_threshold) || ($humidity > $hygrostat->max_threshold))
        {
            System::addLog('error', 
                'Гигростат "'.$hygrostat->name.'" (ID '.$hygrostat->idObject.
                '). Значение '.$humidity.' выходит за пределы измерения.',
                'sensor');
            return $error = true;
        }
        //Если равно 0, то для влажности считаем некорректным значением
        elseif (($humidity == 0))
        {
            System::addLog('error', 
                'Гигростат "'.$hygrostat->name.'" (ID '.$hygrostat->idObject.
                '). Некорректное значение '.$humidity.'.',
                'sensor');
            return $error = true;
        }   
        //Проверяем входит ли значение в диапазон аварийных значений
        else 
        {
            if ($humidity > $hygrostat->max_alarm) 
            {
                System::addLog('warning',
                    'Гигростат "'.$hygrostat->name.'" (ID '.$hygrostat->idObject.
                    '). Значение '.$humidity.' ед. выше аварийного порога.',
                    'sensor');
            }
        
            if ($humidity < $hygrostat->min_alarm)
            {
                System::addLog('warning',
                    'Гигростат "'.$hygrostat->name.'" (ID '.$hygrostat->idObject.
                    '). Значение '.$humidity.' ед. ниже аварийного порога.',
                    'sensor');
            }
            return $error = false;
        }
    }

    /**
     * Заносим в таблицу гигростатов данные об установленной пользователем температуре
     *
     * @param int $idObject - id термостата
     * @param float $value - Значение выбраной темпертуры
     */
    function set_humiduty($idObject, $value)
    {
        //Заносим значение термостата в БД
        parent::$db->query("UPDATE hygrostats SET `optimal` = $value WHERE id_object='$idObject'");
    }

}
