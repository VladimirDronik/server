<?php

/**
 * Класс работы с датчиком углекислотного газа
 */
class CarbDioxide extends Objects
{
    private static $carbdioxide = null;

    /**
     * Конструктор определяет рабочие параметры у выбранного датчика
     *
     * @param int $id_carbdioxide
     */
    function __construct($idObject=null)
    {
        if($idObject!=null)
        {
            //Получаем все данные датчика
            $sql = parent::$db->query("SELECT carbdioxides.id_object AS idObject,
                                              carbdioxides.id AS carbdioxide_id,
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
                                              `placetype`,
                                              `usensor_id`,
                                              carbdioxides.`name`
                                       FROM carbdioxides 
                                       INNER JOIN objects ON  id_object=objects.id
                                       WHERE id_object=$idObject");

            self::$carbdioxide = $sql->fetch(PDO::FETCH_OBJ);
        }
    }

    /**
     * Проверяем параметры дачика с которым рабоатем
     *
     * @return int
     *
     */
    function check()
    {
        $carbdioxide = self::$carbdioxide;
        $sendMessage = false;
        $object = new Objects();
        $object->select($carbdioxide->idObject);

        //Отправка значения для labels
        Labels::setValue(round($carbdioxide->current,1).' ppm', "текущее значение углекислого газа", $carbdioxide->idObject);

        //Если датчик с реакцией на увеличение значения
        if ($carbdioxide->mode == 1)
        {
            if ($carbdioxide->current >= $carbdioxide->optimal+$carbdioxide->gisteresis/2)
            {
                if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                $object->setStatus('ON',true,false);
                Messages::sendByObject($carbdioxide->idObject, $sendMessage);
                $sendMessage = false;

                // Вызываем метод on
                if($carbdioxide->method_on)
                {
                    $object = new Objects();
                    $object->select($carbdioxide->object);
                    if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                    Action::runAction($carbdioxide->method_on, 'carbdioxide', $carbdioxide->idObject, null, false);
                    Messages::sendByObject($carbdioxide->object, $sendMessage);
                }
                return 1;
            }

            if ($carbdioxide->current <= $carbdioxide->optimal-$carbdioxide->gisteresis/2) 
            {
                if (mb_strtoupper($object->status) == 'ON') $sendMessage = true;
                $object->setStatus('OFF',true,false);
                Messages::sendByObject($carbdioxide->idObject, $sendMessage);
                $sendMessage = false;

                // Вызываем метод off
                if($carbdioxide->method_off)
                {
                    $object = new Objects();
                    $object->select($carbdioxide->object);
                    if (mb_strtoupper($object->status) == 'ON') $sendMessage = true;
                    Action::runAction($carbdioxide->method_off, 'carbdioxide', $carbdioxide->idObject, null, false);
                    Messages::sendByObject($carbdioxide->object, $sendMessage);
                }
                return 0;
            }
        } 
        else //Если датчик с реакцией на потемнение
        {
            if ($carbdioxide->current <= $carbdioxide->optimal-$carbdioxide->gisteresis/2)
            {
                if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                $object->setStatus('ON',true,false);
                Messages::sendByObject($carbdioxide->idObject, $sendMessage);
                $sendMessage = false;

                // Вызываем метод on
                if($carbdioxide->method_on)
                {
                    $object = new Objects();
                    $object->select($carbdioxide->object);
                    if (mb_strtoupper($object->status) == 'OFF') $sendMessage = true;
                    Action::runAction($carbdioxide->method_on, 'carbdioxide', $carbdioxide->idObject, null, false);
                    Messages::sendByObject($carbdioxide->object, $sendMessage);
                }
                return 1;
            }

            if ($carbdioxide->current >= $carbdioxide->optimal+$carbdioxide->gisteresis/2)
            {
                if (mb_strtoupper($object->status) == 'ON') $sendMessage = true;
                $object->setStatus('OFF',true,false);
                Messages::sendByObject($carbdioxide->idObject, $sendMessage);
                $sendMessage = false;

                // Вызываем метод off
                if($carbdioxide->method_off)
                {
                    $object = new Objects();
                    $object->select($carbdioxide->object);
                    if (mb_strtoupper($object->status) == 'ON') $sendMessage = true;
                    Action::runAction($carbdioxide->method_off, 'carbdioxide', $carbdioxide->idObject, null, false);
                    Messages::sendByObject($carbdioxide->object, $sendMessage);
                }
                return 0;
            }
        }
    }

    /**
     * Получение значения датчика
     *
     * @return int
     */
    function getValue()
    {
        $carbdioxide = self::$carbdioxide;

        if($carbdioxide->placetype == 'port') 
        {
            //Ищем к какому порту и устройству принадлежит датчик
            $sql = parent::$db->query("SELECT ports_SDA.num_port AS SDA,
                                              ports_SCL.num_port AS SCL,
                                              devices.id AS device_id
                                       FROM carbdioxides     
                                       INNER JOIN ports AS ports_SDA ON ports_SDA.id = carbdioxides.port_SDA
                                       INNER JOIN ports AS ports_SCL ON ports_SCL.id = carbdioxides.port_SCL
                                       INNER JOIN devices ON ports_SDA.id_device = devices.id
                                       WHERE carbdioxides.id_object = $carbdioxide->idObject");

            $carbdioxide_i2c = $sql->fetch(PDO::FETCH_OBJ);
            $lux = Megad::getI2C($carbdioxide_i2c->device_id, $carbdioxide_i2c->SDA, $carbdioxide_i2c->SCL, ''); //TODO:: проверить это для получения параметра датчика
        } 
        else 
        { 
            //Светостат входит в состав унивесального датчика
            $result = Usensors::checkI2C($carbdioxide->usensor_id);
            $co2 = $result['co2'];
        }

        $error = self::validateValue($co2);

        if (!$error)
        {
            //Если считаноое значение не равно предыдущему, то пишем данные в БД
            if ($lux != $carbdioxide->current)
            {
            //Заносим значение светостата в БД в таблицу светостатов и в таблицу графиков
            parent::$db->query("UPDATE carbdioxides SET `current` = $co2 WHERE `id_object` = $carbdioxide->idObject");      
            Graphs::insertToCarbdioxides($carbdioxide->carbdioxide_id, $co2);
            //Далее работаем с полученным от датчика значением
            $carbdioxide->current = $lux;
            }
        }

        //Отдаем значение визуальному компоненту
        $sql = parent::$db->query("SELECT id FROM `view_items` WHERE `id_object`= $carbdioxide->idObject");
        $viewItem = $sql->fetch(PDO::FETCH_OBJ);

        if(isset($viewItem->id))
        {
            $view = new Views();
            $view->updateItem($viewItem->id);
        }
        return $lux;
    }

    /**
     * Получение значения датчика из таблицы
     */
    public static function getValueFromDB($idCarbdioxide)
    {
        $carbdioxidesql = parent::$db->query("SELECT `current` FROM carbdioxides WHERE id_object = $idCarbdioxide");
        if($carbdioxide = $carbdioxidesql->fetch(PDO::FETCH_OBJ));
        return $carbdioxide->current;
    }

    /**
     * Проверка значения на ошибки
     */
    private static function validateValue ($co2)
    {
        $carbdioxide = self::$carbdioxide;
        
        //Проверяем является ли значение числом
        if(!is_numeric($co2))
        {
            System::addLog('error', 
                'Датчик  "'.$carbdioxide->name.'" (ID '.$carbdioxide->idObject.
                '). Некорректное значение '.$co2.'.',
                'sensor');
            return $error = true;
        }
        //Проверяем входит ли значение в диапазон измерений
        elseif (($co2 < $carbdioxide->min_threshold) || ($co2 > $carbdioxide->max_threshold))
        {
            System::addLog('error', 
                'Датчик  "'.$carbdioxide->name.'" (ID '.$carbdioxide->idObject.
                '). Значение '.$co2.' выходит за пределы измерения.',
                'sensor');
            return $error = true;
        }
        //Проверяем входит ли значение в диапазон аварийных значений
        else
        {
            if ($co2 > $carbdioxide->max_alarm)
            {
                System::addLog('warning', 
                    'Датчик "'.$carbdioxide->name.'" (ID '.$carbdioxide->idObject.
                    '). Значение '.$co2.' ед. выше аварийного порога.',
                    'sensor');
            }
            
            if ($co2 < $carbdioxide->min_alarm)
            {
                System::addLog('warning', 
                    'Датчик "'.$carbdioxide->name.'" (ID '.$carbdioxide->idObject.
                    '). Значение '.$co2.' ед. ниже аварийного порога.',
                    'sensor');
            }
            return $error=false;
        }
    }

    /**
     * Заносим в таблицу датчиков углекислого газа данные об установленном пользователем уровне
     *
     * @param int $idObject - id объекта датчика
     * @param float $value - Значение выбраного уровня дачика
     */
    function set_carbdioxide($idObject, $value)
    {
        //Заносим значение в БД
        parent::$db->query("UPDATE carbdioxides SET `optimal` = $value WHERE id_object='$idObject'");
    }
}
