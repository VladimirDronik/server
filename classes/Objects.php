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


        $script = $scriptsql->fetch(PDO::FETCH_OBJ);

        return $script->link;
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

        //Если объект не указан явно, то пробуем искать его у порта на устройстве
        if ($object == null) {

            $sql = parent::$db->query("SELECT `objects`.`id`, `objects`.`type`, `objects`.`status`,
                                    `ports`.`num_port` AS port, `ports`.`id_device` AS device, `ports`.`status` AS portstate 
                                    FROM `objects` LEFT JOIN `ports` ON `objects`.`id` = `ports`.`object` 
                                    WHERE `ports`.`id_device` = $id_device AND `ports`.`num_port` = $num_port");

        }else
            $sql = parent::$db->query("SELECT `objects`.`id`, `objects`.`type`, `objects`.`status`, 
                                    `ports`.`num_port` AS port, `ports`.`id_device` AS device, `ports`.`status` AS portstate 
                                    FROM `objects` LEFT JOIN `ports` ON `objects`.`id` = `ports`.`object` 
                                    WHERE `objects`.`id`= $object");

        if ($sql->rowCount() != 0) {
            $obj = $sql->fetch(PDO::FETCH_OBJ);

            $this->id = $obj->id;
            $this->type = $obj->type;
            $this->status = $obj->status;
            $this->port = $obj->port;
            $this->portstate = $obj->portstate;
            $this->device = (int)$obj->device;
            return true;
        } else return false;

    }



    /**
     *
     * Меняем состояние у объекта и его представления в соответствии с его статусом
     *
     * @param string $status
     * @param bool $set_object_status - при true меняем статус объекта, при false не трогаем статус
     * @param bool $portrelease
     * @param string $whence - откуда было вызвано изменение статуса
     * @param int $idCausing - ид объекта, который инициировал изменение статуса для другого объекта
     * $portrelease=false - при этом параметре не меняем состояние физическог порта, а только состояние объекта
     * @return bool
     */
    function setStatus($status, $set_object_status = true, $portrelease = true, $whence = null, $idCausing = null)
    {

        //Если статус объекта переключатель, то определяем текущее значение
        $status = $this->checkSwitchState($status);

        //Выполняем по умолчанию смену состояния связанного порта и снимаем с порта реальный статус
        if($portrelease) {

            $portState = $this->set_port_state($status);
            if($portState)
                $status = $portState;
        }


        //Если вызвали с устройства, то меняем также статус вызвавшего объекта (это может быть кнопка)
        if(($whence == 'device') && ($idCausing != null)) {
            $idCausing->setStatus($status);
        }

        //Изменяем статус объекта
        if($set_object_status)
        parent::$db->exec("UPDATE `objects` SET `status` = '$status' WHERE `id` = $this->id");

        $this->status = $status;

        if($this->id)
        {
            //Если у объекта есть представление, то меняем его статус
            $sql = parent::$db->query("SELECT id FROM view_items WHERE id_object =  $this->id");
            $item = $sql->fetch(PDO::FETCH_OBJ);

            if(isset($item->id)) {

                $view = new Views();
                $view->updateItem($item->id, $status);
            }
        }


        return $status;

    }



    /**
     * Установка нового значения для порта без смены состояния связанного с ним объекта
     *
     * @param string $status
     * @return string - состояние физического порта
     *
     */
    function set_port_state($status)
    {

        $script = new Scripts();

        $status = $this->checkSwitchState($status);

        if ($status=='on') $statusport=1;
        if ($status=='off') $statusport=0;

        //назначаем порту новое значение
        return $script->set($this->port, $statusport, $this->device);
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
    function checkSwitchState($status)
    {

        //Если статус объекта переключатель, то определяем текущее значение
        if ($status=='sw')
            if ($this->status=='on') $status='off'; else $status='on';

        return $status;
    }

    /**
     * Получение состояния порта
     */
    function getPortState()
    {
       return Megad::status($this->port,'get',$this->device);
    }

}
