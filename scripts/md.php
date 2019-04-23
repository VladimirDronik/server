<?php
/**
 * Основной скрипт, который запускается при возникновении события на любом из мегадевайсов. Реализовано замыкание
 * контактов какого-либо порта
 */



require_once '../include.php';
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

            file_get_contents("http://$ip_device/sec/?cmd=$port->easy");

            //Меняем состояние связанного итема
            $porteasy = explode(':',$port->easy)[0];
            $state = file_get_contents("http://$ip_device/sec/?pt=$porteasy&cmd=get"); //Получаем состояние порта, на который воздействуем
            $state = explode('/',$state)[0];
            $object = new Objects();
            $object->select($porteasy);
            $object->set_status($state,true,false);

        }
        elseif ($port->script!=null) {

            system("cd ".$dir."/../scripts/custom_scripts && php -f $port->script &"); //выполняем внешний скрипт
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

