<?php

/**
 * Created by PhpStorm.
 * User: kinord
 * Date: 01.09.19
 * Time: 7:48
 */
class Action extends Megad
{

    /**
     * @var string
     */
    static private $easy;

    /**
     * @var int $script
     */
    static private $idScript;

    /**
     * Ищем порт соответвующий объекту
     *
     * @param int $idObject
     * */


    /**
     * Функция вызывается при реакции на физическое устройство или реакция на веб интерфейс
     * @param $idMethod - метод, который выполняем
     * @param $whence - откуда был вызван скрипт
     * @param $idCausing - id сущности, которая вызывала действие
     * @param $params - передаваемые параметры
     */
    static public function runAction($idMethod, $whence=null, $idCausing=null, $params=null)
    {

        $sql = parent::$db->query("SELECT `easy`, `script`, `id_object`, `name`, `is_system`, `id_object` 
                                   FROM `methods` WHERE `methods`.`id`=$idMethod");
        $method = $sql->fetch(PDO::FETCH_OBJ);

        self::$easy = $method->easy;
        self::$idScript = $method->script;


        if($method->is_system)
            self::runSystem($method->id_object, self::params($whence, $idCausing, $params));
        else
        if (self::$easy)
            self::easy($method->id_object);
        else
            self::script($method->id_object);

        Messages::sendByObject($method->id_object);
    }

    /**
     * Выполнение простого действия в таблице портов
     * @param int $idObject ид объекта, который вызвал действие
     */
    static private function easy($idObject)
    {

        $porteasy = explode(';',self::$easy);

        $device = parent::getDeviceParams($porteasy[0]);
        $ip_device = $device->ip_address;


        if($device->active) {
            //Меняем статус порта на физическом устройстве
            file_get_contents("http://$ip_device/sec/?cmd=$porteasy[1]");

            //Получаем состояние порта, на который воздействуем
            $state = file_get_contents("http://$ip_device/sec/?pt=$porteasy[1]&cmd=get");
            $state = explode('/', $state)[0];

            $object = new Objects();

            //Меняем состояние объекта и итема, которые вызвали действие
            $object->select($idObject);
            $object->setStatus($state);

            //Если у порта, которым управляем имеется связанный объект, то меняем его состояние
            if ($object->select(null, $porteasy[0], explode(':', $porteasy[1])[0]))
            $object->setStatus($state, true, false);

        }
    }


    /**
     * Выполнение связанного с объектом скрипта
     * @param int $idObject ид объекта, который вызвал действие
    */
    static private function script($idObject)
    {

        $object = new Objects();
        //Меняем состояние объекта и итема, которые вызвали действие
        $object->select($idObject);
        $object->setStatus('sw');

        //Запускаем связанный скрипт
        $script = new Scripts();
        $script->runscript(self::$idScript);

    }

    /**
     * Запуск системного скрипта на выполнение
     * @param int $idObject id объекта счетчика
     * @param string $param передаваемый в скрипт параметр
     */
    static private function runSystem($idObject, $params=null)
    {
        $params = $idObject . ' ' .$params;

        //Запускаем связанный скрипт
        $script = new Scripts();
        $script->runscript(self::$idScript, $params);
    }


    /**
     * Определение параметров, с которыми должен вызываться скрипт
     * @param $method
     * @param $whence
     * @param $params - дополнителные параметры
     */
    static private function params($whence, $idCausing = null, $params = null)
    {

        if (($whence)&&($idCausing)) {

            switch ($whence) {

                case 'view' :
                    $sql = parent::$db->query("SELECT `id_method_params` AS param FROM `view_items` WHERE `id`=$idCausing");
                    $method = $sql->fetch(PDO::FETCH_OBJ);
                    break;

                case 'device':
                    //TODO : Исправить запрос, что бы параметры двойного и длительного нажатия тоже передавались
                    $sql = parent::$db->query("SELECT `id_method_params` AS param FROM `view_items` WHERE `id`=$idCausing");
                    $method = $sql->fetch(PDO::FETCH_OBJ);
                    break;

                case 'scheduler':
                    $sql = parent::$db->query("SELECT `method_params` AS param FROM `scheduler_tasks` WHERE `id`=$idCausing");
                    $method = $sql->fetch(PDO::FETCH_OBJ);
                    break;

            }

            $paramsArray = explode(';', $method->param);
            $resParams = implode(' ', $paramsArray).' '.$params;
            return $resParams;

        } else return null;
    }


}