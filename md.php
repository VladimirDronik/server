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
        $click = $_GET['click']; //Одинарный (1) или двойной (2) клик
        $long = $_GET['m']; // При удержании передается 2, при отпускании 1

        System::addLog('device', 'сработал порт устройства '.$_SERVER['REMOTE_ADDR'].': '.$pt.', click='.$click.', long='.$long);

        Megad::$ip_device = $ip_device;

        $mega = new Megad();

        $port = $mega->get($pt); //взяли номер порта, который сработал - нашли нужный порт в таблице портов

        //Определяем сработал одинарый, двойной клик или длительное нажатие
        if ($click == 2)
            $method = $port->dc_method;
        elseif ($long == 2)
            $method = $port->lc_method;
        else
            $method = $port->method;


        //Взяли объект и метод в тиблице портов, выполняем действие для данного объекта
        if($method)
        Action::runAction($method);



