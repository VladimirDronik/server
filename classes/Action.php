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
     * Определяем по методу какое действие необходимо выполнить и выполняем его
     */
    static public function runAction($idMethod)
    {
        $sql = parent::$db->query("SELECT `easy`, `script`, `id_object` FROM `methods` WHERE `methods`.`id`=$idMethod");
        $method = $sql->fetch(PDO::FETCH_OBJ);

        self::$easy = $method->easy;
        self::$idScript = $method->script;


        if (self::$easy)
            self::easy($method->id_object);
        else
            self::script();

    }

    /**
     * Выполнение простого действия в таблице портов
     * @param int $idObject ид объекта, который вызвал действие
     */
    static private function easy($idObject)
    {

        $porteasy = explode(';',self::$easy);

        $device = parent::ip_address($porteasy[0]);
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

            //Меняем состояние связанного объекта и итема для порта, которым управляем
            $object->select(null, $porteasy[0], explode(':', $porteasy[1])[0]);
            $object->setStatus($state, true, false);

        }
    }


    /**
     * Выполнение связанного с объектом скрипта
    */
    static private function script()
    {


        //Запускаем связанный скрипт
        $script = new Scripts();
        $script->runscript(self::$idScript);

    }



}