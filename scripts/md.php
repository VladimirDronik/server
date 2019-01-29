<?php
/**
 * Основной скрипт, который запускается при возникновении события на любом из мегадевайсов. Реализовано замыкание
 * контактов какого-либо порта
 */

require_once '../include.php';
flush();


/* Определяем какой мегадевайс вызвал скрипт */
$ip_device =  $_SERVER['REMOTE_ADDR'];
$pt = $_GET['pt']; //Получаем номер входного порта, котоырй активировал скрипт
$state = file_get_contents("http://$ip_device/sec/?pt=$pt&cmd=get"); //Получаем состояние порта, который активировал скрипт


Megad::$ip_device = $ip_device;

$mega = new Megad();

$port = $mega->get($pt); //взяли номер порта, который сработал - нашли нужный порт в таблице портов

        if ($port->easy!=null)
        { // Выполняем простое действие, указанное в easy

            echo $port->easy;

        }
        elseif ($port->script!=null) {

            exec("cd ".$dir."/../scripts/custom_scripts && php -f penetration.php &"); //выполняем внешний скрипт
            }
            else{ // Выполняем внешний скрипт, который находим по объекту и его методу

                if($port->object!=null)
                {
                    $state = explode('/',$state);

                    $script = new Scripts();
                    $script->runscript($port->object, $port->method, $state[0]);
                }
            }


//Меняем состояние связанного итема
$view = new Views();
$view->update_item($port->object, $state);
