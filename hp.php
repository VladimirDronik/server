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


        if (($hiteproDevice->type == 'switch') && ($obj->status == true))
            $status = 'ON';
        else $status = 'OFF';


        system::$db->query("UPDATE hiteprodev SET status = '$status' WHERE id = {$obj->id}");

        //Изменяем статус объекта
        if ($hiteproDevice->id_object) {
            $object = new Objects();
            $object->select($hiteproDevice->id_object);
            $object->setStatus($status,true,false);
        }
    }





