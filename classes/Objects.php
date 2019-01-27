<?php

/**
 Класс для работы с объектами
 */

class Objects extends System
{


    /** Ищем объект и его метод в таблице объектов выводим ссылку на скрипт или код*/
    function get(int $object, $id_method, string $method=null){
       //Если указан не id метода, а название конкретного метода

        if ($id_method==null)
          $extended_str =  " 0 OR methods.method='$method'";


        $scriptsql = parent::$db->query("SELECT scripts.link AS link FROM methods 
                                        INNER JOIN scripts ON methods.script = scripts.id 
                                        WHERE (methods.id = $id_method $extended_str) AND methods.id_object = $object");
        return $scriptsql->fetch(PDO::FETCH_OBJ);
    }


    /** Ищем id объекта в таблице представлений */
    function view_oject(int $item_id){

        $sql = parent::$db->query("SELECT `id_object` FROM `view_items` WHERE `id`= $item_id");
        $view_obj = $sql->fetch(PDO::FETCH_OBJ);
        return $id_object = $view_obj->id_object;

    }

    /* Изменение состояния (свойства) объекта в БД*/
    function set_property(int $id_object, $id_method){


    }

}