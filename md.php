<?php
/**
 * Основной скрипт, который запускается при возникновении события на любом из мегадевайсов. Реализовано замыкание
 * контактов какого-либо порта
 */



require_once 'include.php';
flush();


        /* Определяем какой мегадевайс вызвал скрипт */
        $ip_device = $_SERVER['REMOTE_ADDR'];
        $pt = $_GET['pt']; //Получаем номер входного порта, котоырй активировал скрипт
        //$state = file_get_contents("http://$ip_device/sec/?pt=$pt&cmd=get"); //Получаем состояние порта, который активировал скрипт

        //$state = explode('/',$state);

        Megad::$ip_device = $ip_device;

        $mega = new Megad();

        $port = $mega->get($pt); //взяли номер порта, который сработал - нашли нужный порт в таблице портов


        if ($port->easy!=null)
        { // Выполняем простое действие, указанное в easy

            $porteasy = explode(';',$port->easy);

            $device = $mega->ip_address($porteasy[0]);
            $ip_device = $device->ip_address;

            //Меняем статус порта на физическом устройстве
            if($device->active) {
                file_get_contents("http://$ip_device/sec/?cmd=$porteasy[1]");

                //Меняем состояние связанного итема
                $state = file_get_contents("http://$ip_device/sec/?pt=$porteasy[1]&cmd=get"); //Получаем состояние порта, на который воздействуем

                $state = explode('/', $state)[0];
                $object = new Objects();

                $object->select(null, $porteasy[0], explode(':', $porteasy[1])[0]);
                $object->set_status($state, true, false);
            }
        }
        elseif ($port->script!=null) {

            system("cd ".$dir."/../scripts && php -f $port->script &"); //выполняем внешний скрипт
        }
        else{ // Выполняем внешний скрипт, который находим по объекту и его методу

            if($port->object!=null)
            {

                //Запускаем связанный скрипт
                $script = new Scripts();
                $script->runscript($port->object, $port->method);

                //Устанавливаем новый статус объекту, который связан с портом, вызвавшим скрипт
                //Эту реализацию сделать только для объектов, которые могут иметь статус
               /*
                $object = new Objects();
                $object->select($port->object);
                $object->set_status($port->status, false, false);
                */
            }
        }

