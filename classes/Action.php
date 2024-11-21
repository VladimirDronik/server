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
    static public function runAction($idMethod, $whence=null, $idCausing=null, $params=null, $method_params=null, $sendMessage=true)
    {
        if ($idCausing)
        {
            //Меняем состояние объекта и итема, которые вызвали действие
            $causingObject = new Objects();
            $causingObject->select($idCausing);
        }
        
        if ($idMethod != null) {

            $sql = parent::$db->query("SELECT `easy`, `script`, `id_object`, `name`, `is_system`
                FROM `methods` WHERE `methods`.`id`=$idMethod");
            $method = $sql->fetch(PDO::FETCH_OBJ);

            self::$easy = $method->easy;
            self::$idScript = $method->script;
            $object = new Objects();
            $object->select($method->id_object);

            if ($idCausing) Messages::sendByObject($idCausing, $sendMessage); // Вызов сообщений для вызывающего действие объекта
            if ($idCausing != $method->id_object)
                Messages::sendByObject($method->id_object, $sendMessage); //Вызов сообщений для объекта воздействия

            if (self::$easy) self::easy($object, $whence, $params);
            elseif ((self::$idScript) && ($method->is_system == 0)) self::script($object);
            else self::runSystem($method->id_object, self::params($whence, $idCausing, $method_params));

        }
          
        // }
    }

    /**
     * Выполнение простого действия в таблице портов
     * @param int $object объект, который вызвал действие
     */
    static private function easy($object, $whence, $params = null)
    {
        echo "Run easy action for obj = {$object->id}" . PHP_EOL;
        $porteasy = explode(';',self::$easy);
        if ($porteasy[0] == 'm') 
        {   
            //Если в easy указано устройство модбас
            //Запускаем команду на устройстве модбас по его id
            $params = str_replace("'", "", $params);
            return Modbus::putTaskIntoQueue($porteasy[1], 'write', 5, $params);

        } 
        else
        {
            //Если в easy указано другое устройство, нарпимер контроллер мегадевайс
            $device = parent::getDeviceParams($porteasy[0]);
            $ip_device = $device->ip_address;
            $password = $device->password;

            $portAndCmd = explode(':', $porteasy[1]);
            $port = $portAndCmd[0];
            $command = $portAndCmd[1];

            if ($device->active)
            {
                //Если есть доп. параметры
                if (($params == 'ON') || ($params == 'OFF'))
                {
                    if ($params == 'ON') $command = 1;
                    if($params == 'OFF') $command = 0;
                }
                $s = "http://$ip_device/$password?cmd=$port:$command";
                file_get_contents($s);
                
                //Получаем состояние порта, на который воздействуем
                $state = file_get_contents("http://$ip_device/$password?pt=$port&cmd=get");
                
                if ($object->extid)
                {
                    $extPort = explode('e', $portAndCmd[0])[1];
                    $state = mb_strtolower(explode(';', $state)[$extPort]);
                }
                else $state = mb_strtolower(explode('/', $state)[0]);
          

                //Если вызвали с устройства, то меняем также статус вызвавшего объекта (это может быть кнопка)
                if($whence == 'device') {
                    //$idCausing->setStatus($state);

                    //Если у порта, которым управляем имеется связанный объект, то меняем его состояние
                    if ($object->select(null, $porteasy[0], explode(':', $porteasy[1])[0]))
                        $object->setStatus($state, true, false);
                }

                //Меняем статус объекта, которым управляем
                $object->setStatus($state, true, false);

                return $state;
            }
        }
    }


    /**
     * Выполнение связанного с объектом скрипта
     * @param int $object объект, который вызвал действие
    */
    static private function script($object)
    {

        //$object->setStatus('sw');

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
    static private function params($whence, $idCausing = null, $method_params = null)
    {

        if (($whence)&&($idCausing)) {

            switch ($whence) {
                case 'view' :
                    if ($method_params == 'on')
                        $sql = parent::$db->query("SELECT `on_method_params` AS param FROM `view_items` WHERE `id_object`=$idCausing");
                    else
                        $sql = parent::$db->query("SELECT `off_method_params` AS param FROM `view_items` WHERE `id_object`=$idCausing");
                    $method = $sql->fetch(PDO::FETCH_OBJ);
                    break;

                case 'device':
                    //TODO : Исправить запрос, что бы параметры двойного и длительного нажатия тоже передавались
                    if ($method_params == 'dc')
                        $sql = parent::$db->query("SELECT `dc_method_params` AS param FROM `ports` WHERE `object`=$idCausing");
                    elseif ($method_params == 'lc')
                        $sql = parent::$db->query("SELECT `lc_method_params` AS param FROM `ports` WHERE `object`=$idCausing");
                    else
                        $sql = parent::$db->query("SELECT `method_params` AS param FROM `ports` WHERE `object`=$idCausing");
                    $method = $sql->fetch(PDO::FETCH_OBJ);
                    break;

                case 'scheduler':
                    if ($method_params)
                        $sql = parent::$db->query("SELECT `method_params` AS param FROM `scheduler_tasks` WHERE `object`=$idCausing");
                    $method = $sql->fetch(PDO::FETCH_OBJ);
                    break;
            }

            $paramsArray = explode(';', $method->param);
            $resParams = implode(' ', $paramsArray);
            $resParams = "'".$resParams."'";
            return $resParams;

        } else return null;
    }

    /**
     * Выполнение действий для объектов исходя из их типа
     */
    static public function runWithoutMethod($idObject, $portState)
    {
        if ($idObject)
        {
            $object = new Objects();
            $object->select($idObject);
            
            if ($object->type == 'drycontact') {
                
                //Получаем состояние порта, на котором висит данный элемент
                // $status = $object->getPortState();

                // $deviceParams = Megad::getDeviceParams($object->device);
                
                // $state = file_get_contents("http://$deviceParams->ip_address/$deviceParams->password?pt=$object->port&cmd=get");
                // if ($object->extid)
                // {
                //     $extPort = explode('e', $object->port)[1];
                //     $status = mb_strtolower(explode(';', $state)[$extPort]);
                // }
                // else $status = mb_strtolower(explode('/', $state)[0]);

                if ($portState == 1) $status = 'off';
                else $status = 'on';

                //Присваиваем объекту это состояние
                $object->setStatus($status, true, false);

                $sql = parent::$db->query("SELECT `method_on`, `method_off`, `param_method_on`, `param_method_off` 
                                            FROM `drycontacts` WHERE `id_object`=$idObject");
                $drycontQuery = $sql->fetch(PDO::FETCH_OBJ);

                Messages::sendByObject($idObject);

                if ($status == 'on')
                {
                    self::runAction($drycontQuery->method_on, null, $idObject, $drycontQuery->param_method_on, false);
                }
                else
                {
                    self::runAction($drycontQuery->method_off, null, $idObject, $drycontQuery->param_method_off, false);
                }
            }
        }
    }
}