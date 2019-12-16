<?php

/**
 * Класс работы с визуальными элементами плана дома
 */



class Views extends System
{

    /** Получаем список итемов для главной страницы, упаковываем его в json и отдаем скрипту server.php, который
     отправляет этот json клиенту, запрашивающему данные
     *
     * @param string $viewType - указание на то, какие типы элементов ожидаются из БД, если не указано, то все загружаем
     */
    function getRoomItems($viewType = null)
    {

        if($viewType)
            $whereString = " `type_name` =  '$viewType' AND";
            else
                $whereString = '';

        //Находим итемы, кроме главной нулевой комнаты
        $sql_rooms = parent::$db->query("SELECT `rooms`.* FROM `rooms` INNER JOIN `view_items` 
                                         ON `view_items`.`room` = `rooms`.`id` 
                                         WHERE  `view_items`.`active` = 1 
                                         GROUP BY `rooms`.`id` 
                                         ORDER BY `rooms`.`sort`");


        while ($rooms_obj = $sql_rooms->fetch(PDO::FETCH_OBJ)) {

            unset($items_array);

            //Отдаем элементы
            $sql = parent::$db->query("SELECT * FROM `view_items` 
                                       WHERE $whereString `room` = $rooms_obj->id 
                                       AND `active` = 1 ORDER BY `sort`");

            while ($viewObject = $sql->fetch(PDO::FETCH_OBJ)) {

                $items_array[] = self::getItem($viewObject);

                $room = array('id' => (int)$rooms_obj->id,
                              'name' => $rooms_obj->name,
                              'image' => $rooms_obj->image,
                              'style' => $rooms_obj->style,
                              'items' => $items_array);
            }

            $room_array[] = $room;
        }

        return $json = json_encode(array('status'=>'RoomItems', 'items'=>$room_array));
    }


    /** Получаем список итемов, которые относятся к главной комнате */
    function getMainItems()
    {
            //Отдаем элементы
            $sql = parent::$db->query("SELECT * FROM `view_items` WHERE `room` IS NULL AND `active` = 1 ORDER BY `sort`");

            while ($viewObject = $sql->fetch(PDO::FETCH_OBJ)) {

                $items_array[] = self::getItem($viewObject);
            }

        if(isset($items_array))
        return $json = json_encode(array('status'=>'MainItems', 'items'=>$items_array));

    }




    /** Получаем список итемов, которые относятся к сценам */
    function getScenesItems()
    {

        //Находим сцены в таблице сцен, у которых статус=активен
        $sql_scenes = parent::$db->query("SELECT * FROM `scenes` WHERE `active`=1 ORDER BY `sort`");
        while ($scenes_obj = $sql_scenes->fetch(PDO::FETCH_OBJ)) {

            unset($items_array);
            //Отдаем элементы
            $sql = parent::$db->query("SELECT * FROM `view_items` WHERE `scene` = $scenes_obj->id AND `active` = 1 ORDER BY `sort`");
            while ($viewObject = $sql->fetch(PDO::FETCH_OBJ)) {

                $item = self::getItem($viewObject);

                // Если тип объекта термометр или гигрометр
                if (($viewObject->type == 'temp') || ($viewObject->type == 'humidity'))
                    $item = array('id' => (int)$viewObject->id,
                                  'type' => $viewObject->type_name,
                                  'on_image' => $viewObject->on_image,
                                  'off_image' => $viewObject->off_image, 'value' => $viewObject->value, 'left' => $viewObject->position_left, 'top' => $viewObject->position_top);

                //if(isset($item))
                $items_array[] = $item;

                //if(isset($items_array))
                $scenes = array('id' => (int)$scenes_obj->id,'name' => $scenes_obj->name,'image' => $scenes_obj->image, 'backgroung-color' => $scenes_obj->backgroung_color, 'label' => $scenes_obj->label, 'items' => $items_array);

            }

           if (isset($scenes))
            $scenes_array[] = $scenes;

        }

        if (isset($scenes_array))
        return $json = json_encode(array('status'=>'ScenesItems', 'items'=>$scenes_array));

    }



    /**
     * Получаем все пункты меню
     */
    function getMenu()
    {

        $sql = parent::$db->query("SELECT `id`, `name`, `title`, `link`, `image` FROM `menu` WHERE `active`=1 ORDER BY `sort`");
        while ($menu = $sql->fetch(PDO::FETCH_OBJ)) {

            $menu_array = array('id'=>(int)$menu->id, 'name'=>$menu->name, 'title'=>$menu->title, 'link'=>$menu->link, 'image'=>$menu->image);
            $menures[] = $menu_array;
        }

        $json = json_encode(array('menu'=> $menures));
        return $json;
    }



    /** 
     * Получаем список элементов и отдаем для отображения пресетов температуры
     */
    function getTemperatures()
    {

        $sql = parent::$db->query("SELECT `temperatures`.`id` AS id, `rooms`.`name` AS name, `temperatures`.`normal`,
                                   `temperatures`.`night`, `temperatures`.`eco`
                                   FROM `temperatures` INNER JOIN rooms 
                                   ON `temperatures`.`id_room` = `rooms`.`id` ORDER BY `temperatures`.`sort`");

        while ($temp = $sql->fetch(PDO::FETCH_OBJ)) {

            $temp_array = array('id'=>(int)$temp->id, 'name'=>$temp->name, 'normal'=>$temp->normal, 'night'=>$temp->night, 'eco'=>$temp->eco);
            $temperatures[] = $temp_array;
        }

        $json = json_encode(array('status'=>'TemperaturesLoad', 'items'=> $temperatures));
        return $json;
    }


    /**
     * Отдаем значение температуры визуальному отображению термостата
     * @param object $view -  итем с термостатом
     */
    static private function getTermostats($view)
    {
        $sql = parent::$db->query("SELECT  `termostats`.`current`, `termostats`.`optimal`, 
                                            `termostats`.`gisteresis`, `view_items`.`on_title` AS `title` 
                                    FROM `termostats` INNER JOIN view_items 
                                    ON termostats.id_object = view_items.id_object
                                    WHERE `view_items`.`id` = $view->id");

        while ($termostat = $sql->fetch(PDO::FETCH_OBJ)) {


            $curTemp = round($termostat->current);
            $newTemp = $termostat->optimal + $termostat->gisteresis;

            $item = array('id' => (int)$view->id, 'type' => $view->type_name,
                'cur_value' => $curTemp,  'set_value' => $newTemp, 'title' => $view->title,
                'left' => $view->position_left, 'top' => $view->position_top);

            return $item;
        }

    }

    
    /** 
     * Получаем данные из таблицы графиков
     * */
    function getGraphs()
    {

        //Перебираем комнаты в, которых установлены термостаты
        $sql = parent::$db->query("SELECT `temperatures`.`id_room` AS id, `rooms`.`name` AS name, `rooms`.`style`  
                                   FROM `temperatures` INNER JOIN rooms 
                                   ON `temperatures`.`id_room` = `rooms`.`id` ORDER BY `temperatures`.`sort`");

        while ($temp = $sql->fetch(PDO::FETCH_OBJ)) {

            unset($temperatureLog);

            //Ищем данные в таблице графиков, которые относятся к данным термостатам
            $sql_graph = parent::$db->query("SELECT `graph_termostats`.`datetime` AS `date`, `graph_termostats`.`value` AS `value` FROM `graph` 
                                              INNER JOIN `termostats` ON `graph_termostats`.`id_termostat` = `termostats`.`id` 
                                              WHERE `termostats`.`room`=$temp->id AND MINUTE(`graph_termostats`.`datetime`)='00' ");
            while ($temperatures = $sql_graph->fetch(PDO::FETCH_OBJ)) {
                $temperatureLog[] = array('date'=>$temperatures->date, 'value'=>$temperatures->value);
            }

            $rooms[] = array('room'=>$temp->name, 'style'=>$temp->style, 'temperatureLog'=>$temperatureLog);
        }

        return $json = json_encode(array('status'=>'graphsLoad', 'rooms'=>$rooms));
    }



    /** 
     * Получаем список итемов для страницы настроек, упаковываем в json и отправляем клиенту, через скрипт server.php
     */
    function getAllSettings()
    {

    }



    /** 
     * Получаем список событий, упаковываем в json и отправляем клиенту, через скрипт server.php
     *
     * @var string $period буквенный элемент для обозначения периода события
     * @return array json
     */
    function getEvents($period)
    {


        $sql = parent::$db->query("SELECT `scheduler_points`.`id` AS `id`, `type`, `time` AS `time`, `days`, `scheduler_tasks`.`name` AS `name` 
                                    FROM `scheduler_points` 
                                    INNER JOIN `scheduler_tasks` ON `scheduler_points`.`id_task`=`scheduler_tasks`.`id` 
                                    WHERE `system` = 0 AND `type`='$period'");

        while ($view_obj = $sql->fetch(PDO::FETCH_OBJ)) {
            unset($days_array);
            $days_array = explode(',',$view_obj->days);
            $events_array = array('id'=>(int)$view_obj->id, 'type'=>$view_obj->type_name, 'type'=>$view_obj->type, 'time'=>$view_obj->time, 'days'=>$days_array);
            $events[] = $events_array;
        }

        if (isset($events))
        return $json = json_encode(array('status'=>$period.'_eventsLoad', 'events'=>$events));
    }



    /**
     * Получаем данные от клиента и выполняем действия в зависимости от этого
     */
    function resData($data)
    {

        $data_array = json_decode($data);


        //Если клиент отправил запрос на изменение состояния термометра на странице термометров
        if ($data_array->status=='temperaturesChange'){

            $item_id = $data_array->item->id;
            $item_value = $data_array->item->value;
            $item_key = $data_array->item->key;

            //Обновляем данные в таблице температур
            parent::$db->exec("UPDATE `temperatures` SET  `$item_key` = $item_value  WHERE `id` = $item_id");

        }


        //Если клиент отправил запрос на изменение состояния события
        if ($data_array->status=='eventChange'){

            //Обновляем данные в таблице представлений с учетом пришедших данных от клиента
            parent::$db->exec("UPDATE `sheduler_points` SET `status` = '$item_status', `value` = $item_value  WHERE `view_items`.`id` = $item_id");

        }


        //Если клиент отправил запрос на изменение состояния итема
        if ($data_array->status=='itemChange'){

            $item_id = $data_array->items[0]->id;
            $item_name = $data_array->items[0]->type;
            $item_status = $data_array->items[0]->status;
            $item_value = $data_array->items[0]->value;

            //Получаем id объекта из таблицы представлений
            $object = $this->getObjectAndMethod($item_id);

            $idObject = $object->id_object;
            $idMethod = $object->id_method;

            //Если объект у итема существует
            if ($idObject!=null){

                //Если объект является термостатом или гигрометром
                if(($item_name=='temp')||($item_name=='humidity')){


                    if ($item_value == '') $item_value = 'NULL';

                    //Обновляем данные в таблице представлений с учетом пришедших данных от клиента
                    parent::$db->exec("UPDATE `view_items` SET `status` = '$item_status', `value` = $item_value  WHERE `view_items`.`id` = $item_id");

                    //Добавляем данные в таблицу термостатов и больше ничего не делаем
                    $termostat = new Thermostats();
                    $termostat->set_temperature($idObject, $item_value);


                } else { //Если объект является обычной кнопкой

                    $newObject = new Objects();
                    $newObject->select($idObject);

                    //Меняем состояние итема и состояние объекта, физическим портом не управляем
                    $newObject->setStatus($item_status, true, false);

                    //Выполняем действие для данного объекта
                    Action::runAction($idMethod);


                }

                //TODO: проверить является ли объект виртуальным

            }


        }


    }


    /**
     * Обновление состояния итема в таблице представлений и у клиентов
     *
     * @var int idItem - ид итема у которого будем менять статус
     * @var string itemStatus - значение статуса для итема
     */
    function updateItem($idItem, $itemStatus)
    {

        global $localsocket;

        $itemStatus = mb_strtolower($itemStatus);

        // Обновляем данные в таблице представлений
        parent::$db->exec("UPDATE `view_items` SET `status` = IF(`type_name`='temp', `status`, '$itemStatus'),
                            `value` = IF(`type_name`='temp', '$itemStatus', `value`) WHERE `view_items`.`id` = $idItem");

        // Получаем необходимые данные из таблицы представлений для итемов, которые связаны с данным объектом

        $sql = parent::$db->query("SELECT * FROM `view_items` WHERE `id`= $idItem");

        while ($viewItem = $sql->fetch(PDO::FETCH_OBJ)) {

           //Если тип итема - это термометр, то отдаем структуру термометра, иначе отдаем структуру обычного итема
            if($viewItem->type_name == 'temp'){

                $itemTermostat = $this->getTermostats($viewItem);
                $message = '{ "status": "itemChange", "items": ['.$itemTermostat.']}';

            }  else

                $message = '{ "status": "itemChange", "items": [{"id":'.$viewItem->id.',
            "type":"'.$viewItem->type_name.'","status":"'.$viewItem->status.'",
            "icon":"'.$viewItem->on_image.'",
            "title":"'.$viewItem->on_title.'"}]}';


            $res_json = (['user' => 'all', 'message' => $message]);
            $res_json = json_encode($res_json);


            //Отправляем клиенту измененные данные
            // connect to a local tcp-server
            $instance = stream_socket_client($localsocket);
            // send message
            fwrite($instance,  $res_json . "\n");

        }

    }


    /**
     * получение объекта и метода, которые соответвуют представлению
     *
     * @param  int $item_id - ид метода
     * @return object
     */
    function getObjectAndMethod($idItem)
    {

        $sql = parent::$db->query("SELECT `id_object`, `id_method`  FROM `view_items` WHERE `id`= $idItem");
        return $sql->fetch(PDO::FETCH_OBJ);
    }


    /**
     * Формирование массива с параметрами итема
     *
     * @param object $viewObject
     * @return array
     */
    static private function getItem($viewObject)
    {

        // Если тип объекта кнопка или переключатель
        if (($viewObject->type_name == 'button') ||
            ($viewObject->type_name == 'switch') ||
            ($viewObject->type_name == 'light') ||
            ($viewObject->type_name == 'light-own') ||
            ($viewObject->type_name == 'socket'))

            return array('id' => (int)$viewObject->id,
                'type' => $viewObject->type_name,
                'icon' => $viewObject->on_image,
                'title' => $viewObject->on_title,
                'status' => $viewObject->status);

        // Если тип объекта термометр
        if ($viewObject->type_name == 'temp') {
            return self::getTermostats($viewObject);
        }
    }
}