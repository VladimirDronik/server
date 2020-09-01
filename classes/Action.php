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
     * @param int $idMethod - метод, который выполняем
     * @param string $whence - откуда был вызван скрипт
     * @param int $idCausing - id сущности, которая вызывала действие
     * @param string $params - передаваемые параметры
     * @param bool $sendMessage - отправлять сообщение или нет
     */
    static public function runAction($idMethod, $whence=null, $idCausing=null, $params=null, $sendMessage=true)
    {

        $object = new Objects();

        //Меняем состояние объекта и итема, которые вызвали действие
        $object->select($idCausing);


        //Если дейстивие происходит по замыканию порта в любом режиме
        // или если действие происходит без удержания
        // или если действие происходит с удержанием кнопки и объект является кнопкой
        if(
            ($params == '') ||
            ($params == 1) ||
            (($params == 2) && ($object->type == 'button'))
        ) {

            $sql = parent::$db->query("SELECT `easy`, `script`, `id_object`, `name`, `is_system`, `id_object` 
                                   FROM `methods` WHERE `methods`.`id`=$idMethod");
            $method = $sql->fetch(PDO::FETCH_OBJ);

            self::$easy = $method->easy;
            self::$idScript = $method->script;


                if (self::$easy)
                    self::easy($object, $params);
                elseif ((self::$idScript) && ($method->is_system == 0))
                    self::script($object);
                else
                    self::runSystem($method->id_object, self::params($whence, $idCausing, $params));

            Messages::sendByObject($idCausing, $sendMessage);
        }
    }

    /**
     * Выполнение простого действия в таблице портов
     * @param int $object объект, который вызвал действие
     */
    static private function easy($object, $params = null)
    {

        $porteasy = explode(';',self::$easy);

        $device = parent::getDeviceParams($porteasy[0]);
        $ip_device = $device->ip_address;

        $portAndCmd = explode(':', $porteasy[1]);
        $port = $portAndCmd[0];
        $command = $portAndCmd[1];

        if ($device->active) {

            //Если есть доп. параметры
            if (($params == 'ON') || ($params == 'OFF')) {
                if ($params == 'ON')
                    $command = 1;

                if($params == 'OFF')
                    $command = 0;

                file_get_contents("http://$ip_device/sec/?cmd=$port:$command");

            } else
            //Меняем статус порта на физическом устройстве так как есть без параметров
            file_get_contents("http://$ip_device/sec/?cmd=$porteasy[1]");

            //Получаем состояние порта, на который воздействуем
            $state = file_get_contents("http://$ip_device/sec/?pt=$porteasy[1]&cmd=get");
            $state = explode('/', $state)[0];

            $object->setStatus($state);

            //Если у порта, которым управляем имеется связанный объект, то меняем его состояние
            if ($object->select(null, $porteasy[0], explode(':', $porteasy[1])[0]))
            $object->setStatus($state, true, false);

        }
    }


    /**
     * Выполнение связанного с объектом скрипта
     * @param int $object объект, который вызвал действие
    */
    static private function script($object)
    {

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

    /**
     * Выполнение действий для объектов исходя из их типа
     */
    static public function runWithoutMethod($idObject)
    {
        $object = new Objects();
        $object->select($idObject);


        
        if ($object->type == 'drycontact') {

            //Получаем состояние порта, на котором висит данный элемент
            $status = $object->getPortState();

            //Присваиваем объекту это состояние
            $object->setStatus($status,true, false);


            $sql = parent::$db->query("SELECT `method_on`, `method_off`, `param_method_on`, `param_method_off` 
                                        FROM `drycontacts` WHERE `id_object`=$idObject");
            $drycontQuery = $sql->fetch(PDO::FETCH_OBJ);

            if ($object->status == 'ON')
                self::runAction($drycontQuery->method_on, null, $idObject, $drycontQuery->param_method_on);
            else
                self::runAction($drycontQuery->method_off, null, $idObject, $drycontQuery->param_method_off);

        }


    }
}