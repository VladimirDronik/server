<?php

/**
 * Created by PhpStorm.
 * User: kinord
 * Date: 01.07.21
 * Time: 13:43
 */
class Events extends System
{

    public static function exicute($object, $nameEvent)
    {


        //Выполнение действий, которые соответствуют событиям
        $sql = parent::$db->query("SELECT * FROM `events` WHERE `id_object`= $object->id AND `event` = '$nameEvent'");


        if($sql->rowCount() > 0) {
            $events = $sql->fetchAll(PDO::FETCH_OBJ);

            foreach ($events as $event) {

                self::checkCondition($object, $event);
            }
        }

    }



    private static function checkCondition($idObject, $event)
    {
        $object = new Objects();
        $object->select($idObject);

        switch ($object->type) {

            case 'termostat' :
                $termostat = new Thermostats($object->id);
                $property = $termostat->getProperty($event);
                break;

        }


        switch ($event->comparison) {

            case '=':
                if  ($property == $event->value) self::runEventActions($event->id);
                break;

            case '<':
                if  ($property < $event->value) self::runEventActions($event->id);
                break;

            case '>':
                if  ($property > $event->value) self::runEventActions($event->id);
                break;

            case '!=':
                if  ($property != $event->value) self::runEventActions($event->id);
                break;

            default:  self::runEventActions($event->id); //Если не выбран параметр для сравнения
        }


    }



    private static function runEventActions($idEvent)
    {
        //Выбрать из таблицы экшенов все активные действия для данного события
        $sql = parent::$db->query("SELECT * FROM `actions` WHERE `id_event`= $idEvent AND `active` = 1");


        //Перечислить все дейсвтия и для каждого выполнить своё действие
        if($sql->rowCount() > 0) {
            $actions = $sql->fetchAll(PDO::FETCH_OBJ);
            foreach ($actions as $action) {

                switch ($action->type) {

                    case 'script' :
                        $script = new Scripts();
                        $script->runscript($action->relate, $action->params);
                        break;

                    case 'method' :
                        Objects::runMethod($action->relate, $action->params);
                        break;

                    case 'notification' :
                        Messages::send(1, $action->value);
                        break;

                    case 'sound' :
                        System::playSound($action->relate);
                        break;

                    case 'property' :
                        self::setProperty($action->relate, $action->value);
                        break;

                    case 'view' :
                        $view = new Views();
                        $view->updateItem($action->relate, $action->value);
                        break;

                    case 'log' :
                        System::addLog('message',$action->value);
                        break;

                    case 'alice' :
                        if($action->relate = 1)
                            YandexStation::say($action->params, $action->value);
                        elseif ($action->relate = 2)
                            YandexStation::cmd($action->params, $action->value);

                }
            }
        }
    }


    /**
     * Установка значеения свойства для выбранного объекта
     * @param $idObject
     * @param $propertyValue
     */
    private function setProperty($idObject, $propertyValue)
    {

        $propertyAndValue = explode('=',$propertyValue);

        $object = new Objects();
        $object->select($idObject);

        switch ($object->type) {

            case 'termostat' :
                $termostat = new Thermostats($object->id);
                $termostat->setProperty($propertyAndValue[0], $propertyAndValue[1]);
                break;

        }

    }

}