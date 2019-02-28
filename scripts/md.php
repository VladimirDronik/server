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

            //Меняем состояние связанного итема, пока не реализовано
            //$port = explode(':',$port->easy);
            //$state = file_get_contents("http://$ip_device/sec/?pt=$port&cmd=get"); //Получаем состояние порта, на который воздействуем
            //$state = explode('/',$state);
            //$view = new Views();
            //$view->update_item($port->object, $state[0]);

        }
        elseif ($port->script!=null) {

            // exec("cd ".$dir."/../scripts/custom_scripts && php -f penetration.php &"); //выполняем внешний скрипт
        }
        else{ // Выполняем внешний скрипт, который находим по объекту и его методу

            if($port->object!=null)
            {
                //Устанавливаем новый статус объекту, который связан с портом, вызвавшим скрипт
                $object = new Objects();
                $object->select($port->object);
                $object->set_status($port->status, false);


                //Запускаем связанный скрипт
                $script = new Scripts();
                $script->runscript($port->object, $port->method);
            }
        }

