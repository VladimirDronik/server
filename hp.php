<?php
/**
 * Основной скрипт, который запускается при возникновении события на любом из мегадевайсов. Реализовано замыкание
 * контактов какого-либо порта
 */

require_once 'include.php';


    $input = file_get_contents('php://input');

    $json = json_decode($input);


    foreach ($json AS $obj) {

        //Определяем тип объекта, который сработал
        $sql = system::$db->query("SELECT type, id_object, status FROM hiteprodev WHERE id = {$obj->id}");

        $hiteproDevice = $sql->fetch(PDO::FETCH_OBJ);


        if ($hiteproDevice->type == 'transmitter') {

            $sqlswitch =  system::$db->query("SELECT id_object, id_method FROM switches WHERE id_object = {$hiteproDevice->id_object}");
            $switch = $sqlswitch->fetch(PDO::FETCH_OBJ);

            Action::runAction($switch->id_method, 'device', $switch->id_object);

        }



        //system::$db->query("UPDATE hiteprodev SET status = '$status' WHERE id = {$obj->id}");
/*
        //Изменяем статус объекта
        if ($hiteproDevice->id_object) {
            $object = new Objects();
            $object->select($hiteproDevice->id_object);
            $object->setStatus($status,true,false);
        }
*/
    }





