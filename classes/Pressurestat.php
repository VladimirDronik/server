<?php

/**
 * Класс работы с датчиком давления
 */
class Pressurestat extends Objects
{
    private static $pressurestat = null;

    /**
     * Конструктор определяет рабочие параметры у выбранного датчика давления
     *
     * @param int $id_pressurestat
     */
    function __construct($idObject=null)
    {
        if($idObject!=null)
        {
            //Получаем все данные датчика давления
            $sql = parent::$db->query("SELECT pressurestats.id_object AS idObject,
                                              pressurestats.id AS pressurestat_id,
                                              current,
                                              optimal,
                                              gisteresis,
                                              mode,
                                              object,
                                              method_on,
                                              method_off,
                                              `min_threshold`,
                                              `max_threshold`,
                                              `min_alarm`,
                                              `max_alarm`,
                                              `objects`.`type` as `type_object`,
                                              `usensor_id`,
                                              `type_sensor`,
                                              pressurestats.`name`
                                       FROM pressurestats 
                                       INNER JOIN objects ON  id_object=objects.id
                                       WHERE id_object=$idObject");

            self::$pressurestat = $sql->fetch(PDO::FETCH_OBJ);
        }
    }

    /**
     * Проверяем параметры датчика давления с которым рабоатаем
     *
     * @return int
     *
     */
    function check()
    {
        $pressurestat = self::$pressurestat;
        $sendMessage = false;
        $object = new Objects();
        $object->select($pressurestat->idObject);

        if ($pressurestat->type_sensor == 'ptsensor') $unit = ' бар.';
        else $unit = ' мм рт.ст.';

        //Отправка значения для labels
        Labels::setValue(round($pressurestat->current,1).$unit, "текущее давление", $pressurestat->idObject);

        //Если датчик с реакцией на повышение давления
        if ($pressurestat->mode == 1)
        {
            if ($pressurestat->current >= $pressurestat->optimal+$pressurestat->gisteresis/2)
            {
                if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                $object->setStatus('ON',true,false);
                Messages::sendByObject($pressurestat->idObject, $sendMessage);
                $sendMessage = false;

                // Вызываем метод on
                if($pressurestat->method_on)
                {
                    $object = new Objects();
                    $object->select($pressurestat->object);
                    if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                    Action::runAction($pressurestat->method_on, 'pressurestat', $pressurestat->idObject, null, false);
                    Messages::sendByObject($pressurestat->object, $sendMessage);
                }
                return 1;
            }

            if ($pressurestat->current <= $pressurestat->optimal-$pressurestat->gisteresis/2) 
            {
                if (mb_strtoupper($object->status) == 'ON') $sendMessage = true;
                $object->setStatus('OFF',true,false);
                Messages::sendByObject($pressurestat->idObject, $sendMessage);
                $sendMessage = false;

                // Вызываем метод off
                if($pressurestat->method_off)
                {
                    $object = new Objects();
                    $object->select($pressurestat->object);
                    if (mb_strtoupper($object->status) == 'ON') $sendMessage = true;
                    Action::runAction($pressurestat->method_off, 'pressurestat', $pressurestat->idObject, null, false);
                    Messages::sendByObject($pressurestat->object, $sendMessage);
                }
                return 0;
            }
        } 
        else //Если датчик с реакцией на уменьшение давления
        {
            if ($pressurestat->current <= $pressurestat->optimal-$pressurestat->gisteresis/2)
            {
                if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                $object->setStatus('ON',true,false);
                Messages::sendByObject($pressurestat->idObject, $sendMessage);
                $sendMessage = false;

                // Вызываем метод on
                if($pressurestat->method_on)
                {
                    $object = new Objects();
                    $object->select($pressurestat->object);
                    if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                    Action::runAction($pressurestat->method_on, 'pressurestat', $pressurestat->idObject, null, false);
                    Messages::sendByObject($pressurestat->object, $sendMessage);
                }
                return 1;
            }

            if ($pressurestat->current >= $pressurestat->optimal+$pressurestat->gisteresis/2)
            {
                if (mb_strtoupper($object->status) == 'ON') $sendMessage = true;
                $object->setStatus('OFF',true,false);
                Messages::sendByObject($pressurestat->idObject, $sendMessage);
                $sendMessage = false;

                // Вызываем метод off
                if($pressurestat->method_off)
                {
                    $object = new Objects();
                    $object->select($pressurestat->object);
                    if (mb_strtoupper($object->status) == 'ON') $sendMessage = true;
                    Action::runAction($pressurestat->method_off, 'pressurestat', $pressurestat->idObject, null, false);
                    Messages::sendByObject($pressurestat->object, $sendMessage);
                }
                return 0;
            }
        }
    }

    /**
     * Получение значения датчика давления
     *
     * @return int
     */
    function getPress()
    {
        $pressurestat = self::$pressurestat;

        //Датчик входит в состав унивесального датчика
        $result = Usensors::checkI2C($pressurestat->usensor_id);
        if ($pressurestat->type_sensor == 'ptsensor') $pressure = $result['pressure'];
        else $pressure = $result['atm_pressure'];
        
        $error = self::validateValue($pressure);

        if (!$error)
        {
            //Если считаноое значение не равно предыдущему, то пишем данные в БД
            if ($pressure != $pressurestat->current)
            {
            //Заносим значение светостата в БД в таблицу светостатов и в таблицу графиков
            parent::$db->query("UPDATE pressurestats SET `current` = $pressure WHERE `id_object` = $pressurestat->idObject");      
            Graphs::insertToPressurestats($pressurestat->pressurestat_id, $pressure);
            //Далее работаем с полученным от датчика значением
            $pressurestat->current = $pressure;
            }
        }

        //Отдаем значение визуальному компоненту
        $sql = parent::$db->query("SELECT id FROM `view_items` WHERE `id_object`= $pressurestat->idObject");
        $viewItem = $sql->fetch(PDO::FETCH_OBJ);

        if(isset($viewItem->id))
        {
            $view = new Views();
            $view->updateItem($viewItem->id);
        }
        return $pressure;
    }

    /**
     * Получение значения датчка давления из таблицы
     */
    public static function getValueFromDB($idPressurestat)
    {
        $pressurestat = parent::$db->query("SELECT `current` FROM pressurestats WHERE id_object = $idPressurestat");
        if($pressurestat = $pressurestat->fetch(PDO::FETCH_OBJ));
        return $pressurestat->current;
    }

    /**
     * Проверка значения на ошибки
     */
    private static function validateValue ($pressure)
    {
        $pressurestat = self::$pressurestat;
        
        //Проверяем является ли значение числом
        if(!is_numeric($pressure))
        {
            System::addLog('error', 
                'Датчик давления "'.$pressurestat->name.'" (ID '.$pressurestat->idObject.
                '). Некорректное значение '.$pressure.'.',
                'sensor');
            return $error = true;
        }
        //Проверяем входит ли значение в диапазон измерений
        elseif (($pressure < $pressurestat->min_threshold) || ($pressure > $pressurestat->max_threshold))
        {
            System::addLog('error', 
                'Датчик давления "'.$pressurestat->name.'" (ID '.$pressurestat->idObject.
                '). Значение '.$pressure.' выходит за пределы измерения.',
                'sensor');
            return $error = true;
        }
        //Проверяем входит ли значение в диапазон аварийных значений
        else
        {
            if ($pressure > $pressurestat->max_alarm)
            {
                System::addLog('warning', 
                    'Светостат "'.$pressurestat->name.'" (ID '.$pressurestat->idObject.
                    '). Значение '.$pressure.' ед. выше аварийного порога.',
                    'sensor');
            }
            
            if ($pressure < $pressurestat->min_alarm)
            {
                System::addLog('warning', 
                    'Светостат "'.$pressurestat->name.'" (ID '.$pressurestat->idObject.
                    '). Значение '.$pressure.' ед. ниже аварийного порога.',
                    'sensor');
            }
            return $error=false;
        }
    }

    /**
     * Заносим в таблицу датчиков давления данные об установленном пользователем уровне освещенности
     *
     * @param int $idObject - id объекта светостата
     * @param float $value - Значение выбраного уровня освещенности
     */
    function set_pressure($idObject, $value)
    {
        //Заносим значение в БД
        parent::$db->query("UPDATE pressurestats SET `optimal` = $value WHERE id_object='$idObject'");
    }
}
