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
    function getGroupItems($viewType = null)
    {

        if($viewType)
            $whereString = " `type` =  '$viewType' AND";
            else
                $whereString = '';

        //Находим итемы, кроме главной нулевой комнаты
        $sql_rooms = parent::$db->query("SELECT `rooms`.* FROM `rooms` INNER JOIN `view_items` 
                                         ON `view_items`.`room_group` = `rooms`.`id` 
                                         WHERE  `view_items`.`active` = 1 
                                         AND `view_items`.`room_group` IS NOT NULL
                                         GROUP BY `rooms`.`id` 
                                         ORDER BY `rooms`.`sort`");


        while ($rooms_obj = $sql_rooms->fetch(PDO::FETCH_OBJ)) {

            unset($items_array);

            //Отдаем элементы
            $sql = parent::$db->query("SELECT `view_items`.`id`,
                                              `view_items`.`type`, 
                                              `view_items`.`description`, 
                                              `view_items`.`icon`,
                                              `view_items`.`status`, 
                                              `rooms`.`id` AS room_id,
                                              `rooms`.`name` AS room_name
                                       FROM `view_items` 
                                       INNER JOIN `rooms` ON `rooms`.`id` = `view_items`.`room` 
                                       WHERE $whereString `room_group` = $rooms_obj->id 
                                       AND `active` = 1 ORDER BY `view_items`.`sort`");

            while ($viewObject = $sql->fetch(PDO::FETCH_OBJ)) {

                $item = self::getItem($viewObject);

                if($item)
                $items_array[] = $item;

                $roomsArray[$viewObject->room_id] = $viewObject->room_name;


            }

            foreach($roomsArray as $key => $value)
            $roomsInGroup[] = array('id' => (int)$key, 'name' => $value);


            $room = array('id' => (int)$rooms_obj->id,
                'name' => $rooms_obj->name,
                'image' => $rooms_obj->image,
                'style' => $rooms_obj->style,
                'items' => $items_array,
                'roomsInGroup' => $roomsInGroup);

            $groupRoomsArray[] = $room;
        }

        return $json = json_encode(array('status'=>'RoomItems', 'items'=>$groupRoomsArray));
    }


    /**
     * Получаем список элементов выбранной комнаты
     * @param $idRoom - ид выбранной комнаты
     */
    function getRoomItems($idRoom) {

        //Отдаем элементы
        $sql = parent::$db->query("SELECT `view_items`.`id`,
                                              `view_items`.`type`, 
                                              `view_items`.`description`, 
                                              `view_items`.`icon`,
                                              `view_items`.`status`, 
                                              `rooms`.`id` AS room_id,
                                              `rooms`.`image` AS room_image,
                                              `rooms`.`style` AS room_style,
                                              `rooms`.`name` AS room_name
                                       FROM `view_items` 
                                       INNER JOIN `rooms` ON `rooms`.`id` = `view_items`.`room` 
                                       WHERE `room` = $idRoom 
                                       AND `active` = 1 ORDER BY `view_items`.`sort`");

        while ($viewObject = $sql->fetch(PDO::FETCH_OBJ)) {

            $item = self::getItem($viewObject);

            if($item)
                $items_array[] = $item;

            $roomID = $viewObject->room_id;
            $roomImage = $viewObject->room_image;
            $roomStyle = $viewObject->room_style;
        }

        return $json = json_encode(array('status'=>'singleRoom',
                                        'id' => $roomID,
                                        'name' => $roomImage,
                                        'style' => $roomStyle,
                                        'items' => $items_array));
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
                                  'type' => $viewObject->type,
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
     * $idRoom - id помещения, для которого получаем значение температуры
     */
    function getTemperatures($idroom)
    {

        $sql = parent::$db->query("SELECT `temperatures`.`id` AS id, `rooms`.`name` AS name, `temperatures`.`normal`,
                                   `temperatures`.`night`, `temperatures`.`eco`
                                   FROM `temperatures` INNER JOIN rooms 
                                   ON `temperatures`.`id_room` = `rooms`.`id` WHERE `rooms`.`id` = $idroom ORDER BY `temperatures`.`sort`");

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
    static private function getTermostats($view, $typeOutput = 'array')
    {
        $sql = parent::$db->query("SELECT  `termostats`.`current`, `termostats`.`optimal`, 
                                            `termostats`.`gisteresis`, `view_items`.`title` AS `title` 
                                    FROM `termostats` INNER JOIN view_items 
                                    ON termostats.id_object = view_items.id_object
                                    WHERE `view_items`.`id` = $view->id");
        if($sql->rowCount() > 0)
        while ($termostat = $sql->fetch(PDO::FETCH_OBJ)) {


            $curTemp = round($termostat->current);
            $newTemp = $termostat->optimal + $termostat->gisteresis;

            if($typeOutput == 'array')
            $item = array('id' => (int)$view->id, 'type' => $view->type,
                'cur_value' => $curTemp,  'set_value' => $newTemp, 'title' => $termostat->title,
                'left' => $view->position_left, 'top' => $view->position_top);
            else

            $item = '{"id":'.$view->id.',
            "type":"'.$view->type.'","cur_value":"'.$curTemp.'",
            "set_value":"'.$newTemp.'",
            "title":"'.$view->title.'",
            "left":"'.$view->position_left.'",
            "top":"'.$view->position_top.'"
            }';

            return $item;
        } else return false;

    }

    
    /** 
     * Получаем данные из таблицы графиков
     * */
    function getGraphs($params)
    {

        $paramsArray = explode('&',$params);
        $startDate = explode("=",$paramsArray[0])[1];
        $endDate = explode("=", $paramsArray[1])[1];

        //Перебираем комнаты в, которых установлены термостаты
        $sql = parent::$db->query("SELECT `temperatures`.`id_room` AS id, `rooms`.`name` AS name, `rooms`.`style`  
                                   FROM `temperatures` INNER JOIN rooms 
                                   ON `temperatures`.`id_room` = `rooms`.`id` ORDER BY `temperatures`.`sort`");

        while ($temp = $sql->fetch(PDO::FETCH_OBJ)) {

            unset($temperatureLog);

            if ($startDate == $endDate) {

                $datetimeString = " `graph_termostats`.`datetime` ";
                $valueString = " `graph_termostats`.`value` ";
                $whereString = " datetime > '$startDate' ";
                $groupString = "";

            } else {

                $datetimeString = " date_format(`graph_termostats`.`datetime`, '%Y-%m-%d') ";
                $valueString = " avg( `graph_termostats`.`value`) ";
                $whereString = " datetime > '$startDate' AND datetime < '$endDate' ";
                $groupString = " GROUP BY date_format(`graph_termostats`.`datetime`, '%Y-%m-%d') ";
            }


            //Ищем данные в таблице графиков, которые относятся к данным термостатам
            $sql_graph = parent::$db->query("SELECT $datetimeString AS `date`, $valueString AS `value` FROM `graph_termostats` 
                                              INNER JOIN `termostats` ON `graph_termostats`.`id_termostat` = `termostats`.`id` 
                                              WHERE `termostats`.`room`=$temp->id AND MINUTE(`graph_termostats`.`datetime`)='00' 
                                              AND $whereString
                                              $groupString
                                              ");

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
            $events_array = array('id'=>(int)$view_obj->id, 'type'=>$view_obj->type, 'type'=>$view_obj->type, 'time'=>$view_obj->time, 'days'=>$days_array);
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
            $itemDescription = $data_array->items[0]->description;
            $item_name = $data_array->items[0]->type;
            $item_status = $data_array->items[0]->status;
            $item_value = $data_array->items[0]->value;
            $set_value = $data_array->items[0]->set_value;

            //Получаем id объекта из таблицы представлений
            $object = $this->getObjectAndMethod($item_id);

            $idObject = $object->id_object;
            $onMethod = $object->on_method;
            $offMethod = $object->off_method;

            //Если объект у итема существует
            if ($idObject!=null){

                //Если объект является термостатом или гигрометром
                if(($item_name=='temp')||($item_name=='humidity')){


                    if ($set_value == '') $set_value = 'NULL';

                    //Обновляем данные в таблице представлений с учетом пришедших данных от клиента
                    parent::$db->exec("UPDATE `view_items` SET `status` = '$item_status', `value` = $set_value  WHERE `view_items`.`id` = $item_id");

                    //Добавляем данные в таблицу термостатов и больше ничего не делаем
                    $termostat = new Thermostats();
                    $termostat->set_temperature($idObject, $set_value);


                } elseif ($item_name=='switch') { //Если объект является переключателем

                    $newObject = new Objects();
                    $newObject->select($idObject);

                    //Меняем состояние итема и состояние объекта, физическим портом не управляем
                    $newObject->setStatus($item_status, true, false);

                    if($item_status == 'on')
                        $idMethod = $onMethod;
                    else
                        $idMethod = $offMethod;

                    if($idMethod)
                    //Выполняем действие для данного объекта
                    Action::runAction($idMethod, 'view', $item_id);
                    else
                        System::addlog('error','Метод для кнопки "'.$itemDescription.'"" не определен');


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
    function updateItem($idItem, $itemStatus = null)
    {

        global $localsocket;

        $itemStatus = mb_strtolower($itemStatus);

        // Обновляем данные в таблице представлений
       // parent::$db->exec("UPDATE `view_items` SET `status` = IF(`type`='temp', `status`, '$itemStatus'),
         //                   `value` = IF(`type`='temp', '$itemStatus', `value`) WHERE `view_items`.`id` = $idItem");

        parent::$db->exec("UPDATE `view_items` SET `status` = '$itemStatus'
                            WHERE `view_items`.`id` = $idItem");


        // Получаем необходимые данные из таблицы представлений для итемов, которые связаны с данным объектом

        $sql = parent::$db->query("SELECT * FROM `view_items` WHERE `id`= $idItem");

        while ($viewItem = $sql->fetch(PDO::FETCH_OBJ)) {

           //Если тип итема - это термометр, то отдаем структуру термометра, иначе отдаем структуру обычного итема
            if($viewItem->type == 'temp'){

                $itemTermostat = $this->getTermostats($viewItem, 'string');
                $message = '{ "status": "itemChange", "items": ['.$itemTermostat.']}';

            }  else

                $message = '{ "status": "itemChange", "items": [{"id":'.$viewItem->id.',
            "type":"'.$viewItem->type.'","status":"'.$viewItem->status.'",
            "icon":"'.$viewItem->icon.'",
            "title":"'.$viewItem->title.'"}]}';


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

        $sql = parent::$db->query("SELECT `id_object`, `on_method`, `off_method`  FROM `view_items` WHERE `id`= $idItem");
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
        if (($viewObject->type == 'button') ||
            ($viewObject->type == 'switch') ||
            ($viewObject->type == 'light') ||
            ($viewObject->type == 'light-own') ||
            ($viewObject->type == 'socket'))

            return array('id' => (int)$viewObject->id,
                'type' => $viewObject->type,
                'icon' => $viewObject->icon,
                'title' => $viewObject->title,
                'status' => $viewObject->status);

        // Если тип объекта термометр
        if ($viewObject->type == 'temp') {
            return self::getTermostats($viewObject, 'array');
        }
    }
}