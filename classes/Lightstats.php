<?php

/**
 * Класс работы с светостатами
 */


class Lightstats extends Objects
{
    private static $lightstat = null;

    /**
     * Конструктор определяет рабочие параметры у выбранного светостата
     *
     * @param int $id_lightstat
     */
    function __construct($idObject=null)
    {
        if($idObject!=null)
        {
            //Получаем все данные светостата
            $sql = parent::$db->query("SELECT lightstats.id_object AS idObject,
                                              lightstats.id AS lighstat_id,
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
                                              lightstats.`name`
                                       FROM lightstats 
                                       INNER JOIN objects ON  id_object=objects.id
                                       WHERE id_object=$idObject");

            self::$lightstat = $sql->fetch(PDO::FETCH_OBJ);
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
        $lightstat = self::$lightstat;

        //Если светостат с реакцией на посветление
        if ($lightstat->mode == 1)
        {

            if ($lightstat->current >= ($lightstat->optimal+$lightstat->gisteresis/2))
            {
                // Вызываем метод on
                if($lightstat->method_on)
                Action::runAction($lightstat->method_on, 'lightstat', $lightstat->idObject);
                return 1;
            }

            if ($lightstat->current < ($lightstat->optimal-$lightstat->gisteresis/2))
            {
                // Вызываем метод off
                if($lightstat->method_off)
                Action::runAction($lightstat->method_off, 'lightstat', $lightstat->idObject);
                return 0;
            }


        } 
        else //Если светостат с реакцией на потемнение
        {
            if ($lightstat->current <= ($lightstat->optimal-$lightstat->gisteresis/2))
            {
                // Вызываем метод on
                if($lightstat->method_on)
                Action::runAction($lightstat->method_on, 'lightstat', $lightstat->idObject);
                return 1;
            }

            if ($lightstat->current > $lightstat->optimal+$lightstat->gisteresis/2)
            {
                // Вызываем метод off
                if($lightstat->method_off)
                Action::runAction($lightstat->method_off, 'lightstat', $lightstat->idObject);
                return 0;
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
        $lightstat = self::$lightstat;
        $error = false;
        
        if($lightstat->placetype == 'port') 
        {
            //Ищем к какому порту и устройству принадлежит светостат
            $sql = parent::$db->query("SELECT ports_SDA.num_port AS SDA,
                                              ports_SCL.num_port AS SCL,
                                              devices.id AS device_id
                                       FROM lightstats     
                                       INNER JOIN ports AS ports_SDA ON ports_SDA.id = lightstats.port_SDA
                                       INNER JOIN ports AS ports_SCL ON ports_SCL.id = lightstats.port_SCL
                                       INNER JOIN devices ON ports_SDA.id_device = devices.id
                                       WHERE lightstats.id_object = $lightstat->idObject");

            $lightstat_i2c = $sql->fetch(PDO::FETCH_OBJ);
            $lux = Megad::getI2C($lightstat_i2c->device_id, $lightstat_i2c->SDA, $lightstat_i2c->SCL, 'bh1750');
        } 
        else 
        { 
            //Светостат входит в состав унивесального датчика
            $result = Usensors::checkI2C($lightstat->usensor_id);
            $lux = $result['lux'];
        }

        $error = self::validateValue($lux);


        if (!$error)
        {
            //Заносим значение светостата в БД в таблицу светостатов и в таблицу графиков
            parent::$db->query("UPDATE lightstats SET `current` = $lux WHERE `id_object` = $lightstat->idObject");      
            Graphs::insertToLightstats($lightstat->lighstat_id, $lux);
        }

        //Отдаем значение визуальному компоненту
        $sql = parent::$db->query("SELECT id FROM `view_items` WHERE `id_object`= $lightstat->idObject");
        $viewItem = $sql->fetch(PDO::FETCH_OBJ);

        if(isset($viewItem->id))
        {
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
        $sql = parent::$db->query("SELECT `current` FROM lightstats WHERE id_object = $idLightstat");
        if($lightstat = $lightstatsql->fetch(PDO::FETCH_OBJ));
        return $lightstat->current;
    }

    /**
     * Проверка значения на ошибки
     */
    private static function validateValue ($lux)
    {
        $lightstat = self::$lightstat;

        if ($lux > $lightstat->max_alarm) 
        {
            System::addLog('error', 
                'Светостат "'.$lightstat->name.'" (ID '.$lightstat->idObject.
                '). Значение '.$lightstat->current.' ед. выше аварийного порога.',
                'sensor');
            $error=false;
        }
        
        if ($lux < $lightstat->min_alarm)
        {
            System::addLog('error', 
                'Светостат "'.$lightstat->name.'" (ID '.$lightstat->idObject.
                '). Значение '.$lightstat->current.' ед. ниже аварийного порога.',
                'sensor');
            $error=false;
        }
        

        if (($lux < $lightstat->min_threshold) || ($lux > $lightstat->max_threshold))
        {
            System::addLog('error', 
                'Светостат "'.$lightstat->name.'" (ID '.$lightstat->idObject.
                '). Значение '.$lightstat->current.' выходит за пределы измерения.',
                'sensor');
            $error=true;
        }
        return $error;
    }
}
