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
        $sql = parent::$db->query("SELECT `easy`, `script` FROM `methods` WHERE `methods`.`id`=$idMethod");
        $method = $sql->fetch(PDO::FETCH_OBJ);

        self::$easy = $method->easy;
        self::$idScript = $method->script;

        if (self::$easy)
            self::easy();
        else
            self::script();

    }

    /** Выполнение простого действия в таблице портов */
    static private function easy()
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

            //Меняем состояние связанного объекта и итема
            $object->select(null, $porteasy[0], explode(':', $porteasy[1])[0]);
            $object->set_status($state, true, false);

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