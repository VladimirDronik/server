<?php

/**
 *  Класс для работы с объектами
 */

class Objects extends System
{

    /**
     * object id
     * @var int
     */
    public $id;

    /**
     * object type
     * @var string
     */
    public $type;

    /**
     * object status
     * @var string
     */
    public $status;

    /**
     * object view
     * @var int
     */
    public $view;

    /**
     *  port associated with object
     * @var int
     */
    public $port;

    /**
     *  id device associated with object
     * @var int
     */
    public $device;




    /**
     * Ищем объект и его метод в таблице объектов выводим ссылку на скрипт или код
     *
     * @param int $object
     * @param int $id_method
     * @return object
     */
    function get($object, $id_method)
    {
       //Если указан не id метода, а название конкретного метода

            /*
                    if ($id_method==null)
                      $extended_str =  " 0 OR methods.method='$method'";



                    $scriptsql = parent::$db->query("SELECT scripts.link AS link FROM methods
                                                    INNER JOIN scripts ON methods.script = scripts.id
                                                    WHERE (methods.id = $id_method $extended_str) AND methods.id_object = $object");
            */
        if ($id_method!=null)
            $methodstr = " AND methods.id = $id_method";


        $scriptsql = parent::$db->query("SELECT scripts.link AS link FROM methods 
                                        INNER JOIN scripts ON methods.script = scripts.id 
                                        WHERE methods.id_object = $object $methodstr");


        return $scriptsql->fetch(PDO::FETCH_OBJ);
    }




    /**
     * Выбираем объект из таблицы объектов, заносим его данные в публичные переменные
     *
     * @param int $object - ид объекта с которым планируем рабоатать, если null, то ищем объект по номеру порта
     * @param int $id_device - ид устройства, у которого порт связан с объектом
     * @param int $num_port - номер порта, который связан с устройством
     * @return bool
     */
    function select($object, $id_device=null, $num_port=null)
    {

        if ($object == null) {

            $sql = parent::$db->query("SELECT `objects`.`id`, `objects`.`type`, `objects`.`status`, `objects`.`view`, 
                                    `ports`.`num_port` AS port, `ports`.`id_device` AS device, `ports`.`status` AS portstate 
                                    FROM `objects` LEFT JOIN `ports` ON `objects`.`id` = `ports`.`object` 
                                    WHERE `ports`.`id_device` = $id_device AND `ports`.`num_port` = $num_port");

        }else
            $sql = parent::$db->query("SELECT `objects`.`id`, `objects`.`type`, `objects`.`status`, `objects`.`view`, 
                                    `ports`.`num_port` AS port, `ports`.`id_device` AS device, `ports`.`status` AS portstate 
                                    FROM `objects` LEFT JOIN `ports` ON `objects`.`id` = `ports`.`object` 
                                    WHERE `objects`.`id`= $object");

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



    /**
     *
     * Меняем состояние у объекта и его представления в соответствии с его статусом
     *
     * @param string $status
     * @param bool $set_object_status - при true меняем статус объекта, при false не трогаем статус
     * @param bool $portrelease
     * $portrelease=false - при этом параметре не меняем состояние физическог порта, а только состояние объекта
     * @return bool
     */
    function setStatus($status, $set_object_status=true, $portrelease=true)
    {


        //Если статус объекта переключатель, то определяем текущее значение
        $status = $this->check_switch_state($status);

        //Выполняем по умолчанию смену состояния связанного порта
        if($portrelease)
            $this->set_port_state($status);

        //Изменяем статус объекта
        if($set_object_status)
        parent::$db->exec("UPDATE `objects` SET `status` = '$status' WHERE `id` = $this->id");

        $this->status = $status;

        //Если у объекта есть представление, то меняем его статус
        $sql = parent::$db->query("SELECT id FROM view_items WHERE id_object =  $this->id");
        $view = $sql->fetch(PDO::FETCH_OBJ);

        if(isset($view->id)) {

            $view = new Views();
            $view->updateItem($view->id, $status);
        }


        /*
        if ($this->view!=null) {

            //меняем представление объекта
            $view = new Views();
            $view->updateItem($this->view, $status);

        }
*/
        return $status;

    }



    /**
     * Установка нового значения для порта без смены состояния связанного с ним объекта
     *
     * @param string $status
     * @return null
     *
     */
    function set_port_state($status)
    {

        $script = new Scripts();

        $status = $this->check_switch_state($status);

        if ($status=='on') $statusport=1;
        if ($status=='off') $statusport=0;

        //назначаем порту новое значение
        $script->set($this->port, $statusport, $this->device);
    }



    /**
     * Ищем id объекта, который соответствует представлению
     *
     * @param int $item_id
     * @return int
     */

/*
    function view_oject($item_id)
    {

        $sql = parent::$db->query("SELECT `id` FROM `objects` WHERE `view`= $item_id");
        $view_obj = $sql->fetch(PDO::FETCH_OBJ);
        return $id_object = $view_obj->id;

    }
*/



    /**
     * Определяем на что нужно сменить статус у объекта, если отправляем переключение
     *
     * @param string $status
     * @return string
     */
    function check_switch_state($status)
    {

        //Если статус объекта переключатель, то определяем текущее значение
        if ($status=='sw')
            if ($this->status=='on') $status='off'; else $status='on';

        return $status;
    }

}
