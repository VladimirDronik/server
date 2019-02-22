<?php

/**
 Класс для работы с объектами
 */

class Objects extends System
{

    public $id;
    public $type;
    public $status;
    public $view;
    public $port;
    public $device;


    /** Ищем объект и его метод в таблице объектов выводим ссылку на скрипт или код*/
    function get(int $object, $id_method, string $method=null){
       //Если указан не id метода, а название конкретного метода
/*
        if ($id_method==null)
          $extended_str =  " 0 OR methods.method='$method'";



        $scriptsql = parent::$db->query("SELECT scripts.link AS link FROM methods 
                                        INNER JOIN scripts ON methods.script = scripts.id 
                                        WHERE (methods.id = $id_method $extended_str) AND methods.id_object = $object");
*/
        $scriptsql = parent::$db->query("SELECT scripts.link AS link FROM methods 
                                        INNER JOIN scripts ON methods.script = scripts.id 
                                        WHERE methods.id_object = $object");


        return $scriptsql->fetch(PDO::FETCH_OBJ);
    }

    /** Выбираем объект из таблицы объектов */
    function select($object){


        $sql = parent::$db->query("SELECT `objects`.`id`, `objects`.`type`, `objects`.`status`, `objects`.`view`, 
                                    `ports`.`id` AS port, `ports`.`id_device` AS device, `ports`.`status` AS portstate FROM `objects` LEFT JOIN `ports` ON 
                                    `objects`.`id` = `ports`.`object` WHERE `objects`.`id`= $object");
        $obj = $sql->fetch(PDO::FETCH_OBJ);

        $this->id = $obj->id;
        $this->type = $obj->type;
        $this->status = $obj->status;
        $this->view = $obj->view;
        $this->port = $obj->port;
        $this->portstate = $obj->portstate;
        $this->device = (int)$obj->device;
        return true;
    }




    /** Функция меняем состояние у объекта и его представления в соответствии с его статусом */
    function set_status($status){


        //Если статус объекта переключатель, то определяем текущее значение
        if ($status=='sw')
            if ($this->status=='on') $status='off'; else $status='on';

        //Изменяем статус объекта
        parent::$db->exec("UPDATE `objects` SET `status` = '$status' WHERE `id` = $this->id");


        //Если с объектом связан какой-либо порт на устройстве и этот порт out
        if(($this->port!=null)&&($this->portstate=='out')) {
            $script = new Scripts();
            if ($status=='on') $statusport=1;
            if ($status=='off') $statusport=0;
            $script->set($this->port, $statusport, $this->device);
        }

        //Если у объекта есть представление
        if ($this->view!=null) {
            //меняем представление объекта
            $view = new Views();
            $view->update_item($this->view, $status);
        }
    }


    /** Ищем id объекта, который соответствует представлению */
    function view_oject(int $item_id){

        $sql = parent::$db->query("SELECT `id` FROM `objects` WHERE `view`= $item_id");
        $view_obj = $sql->fetch(PDO::FETCH_OBJ);
        return $id_object = $view_obj->id;

    }


}
