<?php

/**
 * Класс работы с визуальными элементами плана дома
 */



class Views extends System
{

    /** Получаем список итемов для главной страницы, упаковываем его в json и отдаем скрипту server.php, который
     * отправляет этот json клиенту, запрашивающему данные
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
        $sql_rooms = parent::$db->query(
            "SELECT DISTINCT `rooms`.name, `rooms`.id, `rooms`.sort, `rooms`.`image` AS image, `rooms`.`style` AS style  FROM `rooms` INNER JOIN `view_items` 
        ON `view_items`.`room_group` = `rooms`.`id` 
        WHERE  `view_items`.`active` = 1 
        AND `view_items`.`room_group` IS NOT NULL
        ORDER BY `rooms`.`sort`;");


        while ($rooms_obj = $sql_rooms->fetch(PDO::FETCH_OBJ)) {

            unset($items_array, $roomsArray, $roomsInGroup);


            //Отдаем элементы
            $sql = parent::$db->query("SELECT `view_items`.`id`,
                                              `view_items`.`type`, 
                                              `view_items`.`description`, 
                                              `view_items`.`icon`,
                                              `view_items`.`status`,
					                          `view_items`.`title`,
                                              `view_items`.`value`,
                                              `view_items`.`params`,  
                                              `view_items`.`color`, 
                                              `rooms`.`id` AS room_id,
                                              `rooms`.`name` AS room_name,
                                              `rooms`.`image` AS room_image
                                       FROM `view_items` 
                                       INNER JOIN `rooms` ON `rooms`.`id` = `view_items`.`room` 
                                       WHERE $whereString `room_group` = $rooms_obj->id 
                                       AND `active` = 1 ORDER BY `view_items`.`sort`");

            while ($viewObject = $sql->fetch(PDO::FETCH_OBJ)) {

                $item = self::getItem($viewObject);

                if($item)
                $items_array[] = $item;

                $roomsArray[$viewObject->room_id]['name'] = $viewObject->room_name;
                $roomsArray[$viewObject->room_id]['image'] = $viewObject->room_image;


            }



            foreach($roomsArray as $key => $value)
            $roomsInGroup[] = array('id' => (int)$key, 'name' => $value['name'], 'image' => $value['icon']);


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
                                              `view_items`.`params`,
                                              `view_items`.`color`,  
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

    /** Получаем список итемов, которые относятся к главной комнате (скрипты) */
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

                //if(isset($item))
                $items_array[] = $item;

                //if(isset($items_array))
                $scenes = array('id' => (int)$scenes_obj->id,'name' => $scenes_obj->label,'image' => '/ela/images/scenes/'.$scenes_obj->image,
                    'backgroung-color' => $scenes_obj->backgroung_color, 'items' => $items_array);

            }

           if (isset($scenes))
            $scenes_array[] = $scenes;

        }

        if (isset($scenes_array))
        return $json = json_encode(array('status'=>'ScenesItems', 'items'=>$scenes_array));

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

            $temp_array = array('id'=>(string)$temp->id,
                                'name'=>(string)$temp->name,
                                'normal'=>(string)$temp->normal,
                                'night'=>(string)$temp->night, 
                                'eco'=>(string)$temp->eco);
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
                                            `termostats`.`gisteresis`, `view_items`.`title` AS `title`, `view_items`.`params` 
                                    FROM `termostats` INNER JOIN view_items 
                                    ON termostats.id_object = view_items.id_object
                                    WHERE `view_items`.`id` = $view->id");
        if($sql->rowCount() > 0)
        while ($termostat = $sql->fetch(PDO::FETCH_OBJ)) {


            $curTemp = round($termostat->current);
            $newTemp = (float)$termostat->optimal;


            if($typeOutput == 'array')
            $item = array('id' => (int)$view->id, 'type' => $view->type, 'icon' => $view->icon,
                'cur_value' => $curTemp,  'set_value' => $newTemp, 'title' => $termostat->title,
                'left' => $view->position_left, 'top' => $view->position_top,  'params' => $termostat->params);
            else

            $item = '{"id":'.$view->id.',
            "type":"'.$view->type.'","cur_value":"'.$curTemp.'",
            "set_value":"'.$newTemp.'",
            "title":"'.$view->title.'",
            "left":"'.$view->position_left.'",
            "top":"'.$view->position_top.'",
            "params":"'.$termostat->params.'"
            }';

            return $item;
        } else return false;

    }


    /**
     * Отдаем значение влажность визуальному отображению гигростата
     * @param object $view -  итем с гигростатом
     */
    static private function getHygrostats($view, $typeOutput = 'array')
    {
        $sql = parent::$db->query("SELECT  `hygrostats`.`current`, `hygrostats`.`optimal`, 
                                            `hygrostats`.`gisteresis`, `view_items`.`title` AS `title`, 
                                            `view_items`.`params` 
                                    FROM `hygrostats` INNER JOIN view_items 
                                    ON hygrostats.id_object = view_items.id_object
                                    WHERE `view_items`.`id` = $view->id");
        if($sql->rowCount() > 0)
            while ($hygrostat = $sql->fetch(PDO::FETCH_OBJ)) {


                $curValue = round($hygrostat->current);
                $newValue = (float)$hygrostat->optimal;


                if($typeOutput == 'array')
                    $item = array('id' => (int)$view->id, 'type' => $view->type, 'icon' => $view->icon,
                        'cur_value' => $curValue,  'set_value' => $newValue, 'title' => $hygrostat->title,
                        'left' => $view->position_left, 'top' => $view->position_top,  'params' => $hygrostat->params);
                else

                    $item = '{"id":'.$view->id.',
            "type":"'.$view->type.'","cur_value":"'.$curValue.'",
            "set_value":"'.$newValue.'",
            "title":"'.$view->title.'",
            "left":"'.$view->position_left.'",
            "top":"'.$view->position_top.'",
            "params":"'.$hygrostat->params.'"
            }';

                return $item;
            } else return false;

    }

     /**
     * Отдаем значение влажность визуальному отображению светостсата
     * @param object $view -  итем со светостатом
     */
    static private function getLightstats($view, $typeOutput = 'array')
    {
        $sql = parent::$db->query("SELECT  `lightstats`.`current`, `lightstats`.`optimal`, 
                                            `lightstats`.`gisteresis`, `view_items`.`title` AS `title`, 
                                            `view_items`.`params` 
                                    FROM `lightstats` INNER JOIN view_items 
                                    ON lightstats.id_object = view_items.id_object
                                    WHERE `view_items`.`id` = $view->id");
        if($sql->rowCount() > 0)
            while ($lightstat = $sql->fetch(PDO::FETCH_OBJ)) {


                $curValue = round($lightstat->current);
                if ($curValue > 100) $curValue = 100; 

                $newValue = (float)$lightstat->optimal;


                if($typeOutput == 'array')
                    $item = array('id' => (int)$view->id, 'type' => $view->type, 'icon' => $view->icon,
                        'cur_value' => $curValue,  'set_value' => $newValue, 'title' => $lightstat->title,
                        'left' => $view->position_left, 'top' => $view->position_top,  'params' => $lightstat->params);
                else

                    $item = '{"id":'.$view->id.',
            "type":"'.$view->type.'","cur_value":"'.$curValue.'",
            "set_value":"'.$newValue.'",
            "title":"'.$view->title.'",
            "left":"'.$view->position_left.'",
            "top":"'.$view->position_top.'",
            "params":"'.$lightstat->params.'"
            }';

                return $item;
            } else return false;

    }

    /**
     * Отдаем значение влажность визуальному отображению датчика давления
     * @param object $view -  итем с датчиком давления
     */
    static private function getPressurestats($view, $typeOutput = 'array')
    {
        $sql = parent::$db->query("SELECT  `pressurestats`.`current`, `pressurestats`.`optimal`, 
                                            `pressurestats`.`gisteresis`, `pressurestats`.`type_sensor`, `view_items`.`title` AS `title`, 
                                            `view_items`.`params` 
                                    FROM `pressurestats` INNER JOIN view_items 
                                    ON pressurestats.id_object = view_items.id_object
                                    WHERE `view_items`.`id` = $view->id");
        if($sql->rowCount() > 0)
            while ($pressurestat = $sql->fetch(PDO::FETCH_OBJ)) {


                $curValue = round($pressurestat->current);
                $newValue = (float)$pressurestat->optimal;

                if ($pressurestat->type_sensor = 'bmx280') {
                    $unit = ' мм рт.ст.';
                } elseif ($pressurestat->type_sensor = 'ptsensor') $unit = ' бар.';

                if($typeOutput == 'array')
                    $item = array('id' => (int)$view->id, 'type' => $view->type, 'icon' => $view->icon,
                        'cur_value' => $curValue,  'set_value' => $newValue, 'unit' => $unit, 'title' => $pressurestat->title,
                        'left' => $view->position_left, 'top' => $view->position_top,  'params' => $pressurestat->params);
                else

                    $item = '{"id":'.$view->id.',
            "type":"'.$view->type.'","cur_value":"'.$curValue.'",
            "set_value":"'.$newValue.'",
            "unit": "'.$unit.'",
            "title":"'.$view->title.'",
            "left":"'.$view->position_left.'",
            "top":"'.$view->position_top.'",
            "params":"'.$pressurestat->params.'"
            }';

                return $item;
            } else return false;

    }


     /**
     * Отдаем значение датчика давления углекислого газа
     * @param object $view -  итем с датчиком
     */
    static private function getCarbdioxide($view, $typeOutput = 'array')
    {
        $sql = parent::$db->query("SELECT  `carbdioxide`.`current`, `carbdioxide`.`optimal`, 
                                            `carbdioxide`.`gisteresis`, `carbdioxide`.`type_sensor`, `view_items`.`title` AS `title`, 
                                            `view_items`.`params` 
                                    FROM `carbdioxides` INNER JOIN view_items 
                                    ON carbdioxides.id_object = view_items.id_object
                                    WHERE `view_items`.`id` = $view->id");
        if($sql->rowCount() > 0)
            while ($carbdioxide = $sql->fetch(PDO::FETCH_OBJ)) {


                $curValue = round($carbdioxide->current);
                $newValue = (float)$carbdioxide->optimal;
                $unit = "ppm";

                if($typeOutput == 'array')
                    $item = array('id' => (int)$view->id, 'type' => $view->type, 'icon' => $view->icon,
                        'cur_value' => $curValue,  'set_value' => $newValue, 'unit' => $unit, 'title' => $carbdioxide->title,
                        'left' => $view->position_left, 'top' => $view->position_top,  'params' => $carbdioxide->params);
                else

                    $item = '{"id":'.$view->id.',
            "type":"'.$view->type.'","cur_value":"'.$curValue.'",
            "set_value":"'.$newValue.'",
            "unit": "'.$unit.'",
            "title":"'.$view->title.'",
            "left":"'.$view->position_left.'",
            "top":"'.$view->position_top.'",
            "params":"'.$carbdioxide->params.'"
            }';

                return $item;
            } else return false;

    }



    
    /** 
     * Получаем данные из таблицы графиков
     * */
    function getGraphs($idRoom, $params)
    {

        $paramsArray = explode('&',$params);
        $startDate = explode("=",$paramsArray[0])[1];
        $endDate = explode("=", $paramsArray[1])[1];

        /*
        //Перебираем комнаты в, которых установлены термостаты
        $sql = parent::$db->query("SELECT `temperatures`.`id_room` AS id, `rooms`.`name` AS name, `rooms`.`style`  
                                   FROM `temperatures` INNER JOIN rooms 
                                   ON `temperatures`.`id_room` = `rooms`.`id` WHERE `rooms`.`id`=$idRoom ORDER BY `temperatures`.`sort`");

        while ($temp = $sql->fetch(PDO::FETCH_OBJ)) {
*/
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


            $sql_termostats = parent::$db->query("SELECT `id`, `name` FROM `termostats` WHERE room=".$idRoom);
            while ($termostat = $sql_termostats->fetch(PDO::FETCH_OBJ)) {


                //Ищем данные в таблице графиков, которые относятся к данным термостатам
                $sql_graph = parent::$db->query("SELECT $datetimeString AS `date`, $valueString AS `value` FROM `graph_termostats` 
                                                  INNER JOIN `termostats` ON `graph_termostats`.`id_termostat` = `termostats`.`id` 
                                                  WHERE `termostats`.`id`={$termostat->id} AND MINUTE(`graph_termostats`.`datetime`)='00' 
                                                  AND $whereString
                                                  $groupString
                                                  ");

                while ($temperatures = $sql_graph->fetch(PDO::FETCH_OBJ)) {
                    $temperatureLog[] = array('date' => $temperatures->date, 'value' => round($temperatures->value, 1));
                }

                $datagrapf[] = array('id_termostat' => $termostat->id, 'termostat_name' => $termostat->name, 'temperatureLog' => $temperatureLog);
                unset($temperatureLog);
            }
 //       }

        return $json = json_encode(array('status'=>'graphsLoad', 'data'=>$datagrapf));
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
            $events_array = array('id'=>(int)$view_obj->id, 'type'=>$view_obj->type, 'time'=>$view_obj->time, 'days'=>$days_array);
            $events[] = $events_array;
        }

        if (isset($events))
        return $json = json_encode(array('status'=>$period.'_eventsLoad', 'events'=>$events));
    }



    /**
     * Получаем данные от клиента и выполняем действия в зависимости от этого
     */
    public function resData($data)
    {
   
        $data_array = json_decode($data);

        //Если клиент отправил запрос на изменение состояния термометра на странице термометров
        if ($data_array->status == 'temperaturesChange') {

            $itemID = $data_array->item->id;
            $itemValue = $data_array->item->value;
            $item_key = $data_array->item->key;

            //Обновляем данные в таблице температур
            parent::$db->exec("UPDATE `temperatures` SET  `$item_key` = $itemValue  WHERE `id_room` = $itemID");

        }


        //Если клиент отправил запрос на изменение состояния события
        if ($data_array->status == 'eventChange') {

            //Обновляем данные в таблице представлений с учетом пришедших данных от клиента
            parent::$db->exec("UPDATE `sheduler_points` SET `status` = '$itemStatus', `value` = $itemValue  WHERE `view_items`.`id` = $itemID");

        }


        //Если клиент отправил запрос на изменение состояния итема
        if ($data_array->status == 'itemChange') {

            if (isset($data_array->items[0]->id)) $itemID = $data_array->items[0]->id;
            if (isset($data_array->items[0]->description)) $itemDescription = $data_array->items[0]->description;
            if (isset($data_array->items[0]->type)) $itemType = $data_array->items[0]->type;
            if (isset($data_array->items[0]->status)) $itemStatus = $data_array->items[0]->status;
            if (isset($data_array->items[0]->value)) $itemValue = $data_array->items[0]->value;
            if (isset($data_array->items[0]->set_value)) $set_value = $data_array->items[0]->set_value;

            //Получаем id объекта из таблицы представлений
            $object = $this->getObjectAndMethod($itemID);

            if ($object->id_object != null) {

                $idObject = $object->id_object;
                $onMethod = $object->on_method;
                $offMethod = $object->off_method;

                $newObject = new Objects();
                $newObject->select($idObject);


                switch ($itemType) {
                    case 'termostat':
                        
                        if ($set_value == '') $set_value = 'NULL';

                        //Обновляем данные в таблице представлений с учетом пришедших данных от клиента
                        parent::$db->exec("UPDATE `view_items` SET `status` = '$itemStatus' WHERE `view_items`.`id` = $itemID");
    
    
                        //Добавляем данные в таблицу термостатов и больше ничего не делаем
                        $termostat = new Thermostats();
                        $termostat->set_temperature($idObject, $set_value);
    
                        //Запускаем метод 
                        Action::runAction($onMethod, 'view', $idObject);
    
                        //Добавляем запись в лог
                        system::addLog('user', "Оптимальная температура для термостата ID $idObject изменена на " . $set_value . "°C.", 'socket_server');
    
                        //Отпарвляем данные о температуре остальным клиентам
                        self::updateItem($itemID);

                        break;

                    case 'hygrostat':
                        if ($set_value == '') $set_value = 'NULL';

                        //Обновляем данные в таблице представлений с учетом пришедших данных от клиента
                        parent::$db->exec("UPDATE `view_items` SET `status` = '$itemStatus' WHERE `view_items`.`id` = $itemID");

                         //Добавляем данные в таблицу гигростатов и больше ничего не делаем
                         $hygrostat = new Hygrostats();
                         $hygrostat->set_humiduty($idObject, $set_value);

                         //Запускаем метод 
                         Action::runAction($onMethod, 'view', $idObject);

                         //Добавляем запись в лог
                         system::addLog('user', "Оптимальная влажность для гигростата ID $idObject изменена на " . $set_value . "% ", 'socket_server');
   
                         //Отправляем данные о влажности остальным клиентам
                         self::updateItem($itemID);

                        break;
                        
                    case 'lightstat':
                        if ($set_value == '') $set_value = 'NULL';

                        //Обновляем данные в таблице представлений с учетом пришедших данных от клиента
                        parent::$db->exec("UPDATE `view_items` SET `status` = '$itemStatus' WHERE `view_items`.`id` = $itemID");

                         //Добавляем данные в таблицу светостатов и больше ничего не делаем
                         $lightstat = new Lightstats();
                         $lightstat->set_light($idObject, $set_value);

                         //Запускаем метод 
                         Action::runAction($onMethod, 'view', $idObject);

                         //Добавляем запись в лог
                         system::addLog('user', "Оптимальная освещенность для светостата ID $idObject изменена на " . $set_value . "% ", 'socket_server');
    
                         //Отправляем данные об освещенности остальным клиентам
                         self::updateItem($itemID);

                        break;    

                    case 'pressurestat':
                        if ($set_value == '') $set_value = 'NULL';
    
                        //Обновляем данные в таблице представлений с учетом пришедших данных от клиента
                        parent::$db->exec("UPDATE `view_items` SET `status` = '$itemStatus' WHERE `view_items`.`id` = $itemID");
    
                        //Добавляем данные в таблицу датчиков давления и больше ничего не делаем
                             $pressurestat = new Pressurestat();
                             $pressurestat->set_pressure($idObject, $set_value);
    
                        //Запускаем метод 
                        Action::runAction($onMethod, 'view', $idObject);
    
                        //Добавляем запись в лог
                        system::addLog('user', "Оптимальное давление для датчика с ID $idObject изменено на " . $set_value . " ", 'socket_server');
        
                        //Отправляем данные о давлении остальным клиентам
                        self::updateItem($itemID);
    
                        break;      
                        
                    case 'carbdioxide':
                        if ($set_value == '') $set_value = 'NULL';
    
                        //Обновляем данные в таблице представлений с учетом пришедших данных от клиента
                        parent::$db->exec("UPDATE `view_items` SET `status` = '$itemStatus' WHERE `view_items`.`id` = $itemID");
    
                        //Добавляем данные в таблицу датчиков и больше ничего не делаем
                        $carbdioxide = new CarbDioxide();
                        $carbdioxide->set_carbdioxide($idObject, $set_value);
    
                        //Запускаем метод 
                        Action::runAction($onMethod, 'view', $idObject);
    
                        //Добавляем запись в лог
                        system::addLog('user', "Оптимальное значение для датчика ID $idObject изменена на " . $set_value . "% ", 'socket_server');
        
                        //Отправляем данные о датчике остальным клиентам
                        self::updateItem($itemID);
    
                        break;    

                    case 'switch':
                    case 'button':
                        self::updateItem($itemID, $itemStatus);

                        if (!self::runButtonMethod($newObject, $itemStatus, $onMethod, $offMethod, $itemID, $itemType))
                            System::addlog('error', 'Метод для кнопки "' . $itemDescription . '"" не определен', 'button');
                        
                        break;

                    case 'label':
                        
                        if (!self::runButtonMethod($newObject, $itemStatus, $onMethod, $offMethod, $itemID, $itemType))
                        System::addlog('error', 'Метод для кнопки "' . $itemDescription . '"" не определен', 'button');

                        break;

                    case  'conditioner':

                        if (isset($data_array->items[0]->temp)) $temperature = $data_array->items[0]->temp;
                        if (isset($data_array->items[0]->mode)) $mode = $data_array->items[0]->mode;
                        if (isset($data_array->items[0]->fan)) $fan = $data_array->items[0]->fan;
                        if (isset($data_array->items[0]->vdir)) $vdir = $data_array->items[0]->vdir;
                        if (isset($data_array->items[0]->hdir)) $hdir = $data_array->items[0]->hdir;
                        
                        $conditioner = new Conditioner($idObject);
                        if (isset($itemStatus)) $conditioner->setAcPower (strtolower($itemStatus));
                        if (isset($temperature)) $conditioner->setAcTemperature ($temperature);
                        if (isset($mode)) $conditioner->setAcMode ($mode);
                        if (isset($fan)) $conditioner->setAcFanSpeed ($fan);
                        if (isset($vdir)) $conditioner->setAcVDir ($vdir);
                        if (isset($hdir)) $conditioner->setAcHDir ($hdir);

                        // $conditioner->setValue($temperature, $itemStatus, $mode, $fan);

                        break;

                    case  'dimmer':

                        $dimmer = new Dimmer($idObject);

                        //Если значение димера не установлено, то значит сработало одиночное нажатие на кнопку димера
                        if ($itemValue === null) {
    
                            if (!self::runButtonMethod($newObject, $itemStatus, $onMethod, $offMethod, $itemID, $itemType))
                                System::addlog('error', 'Метод для диммера "' . $itemDescription . '"" не определен', 'dimmer');
    
                        } else { //пришло конкретное значение диммера
    
                            //Устанавливаем яркость диммера
                            $dimmer->setValue($itemValue);
                            $status = 'ON';
    
                            if ($itemValue == 0) {
                                //Выключаем диммер
                                $status = 'OFF';
                            }
    
                            $newObject->setStatus($status, true, false);
    
                        }

                        break;

                    
                    case 'customizable_light':
                        
                        $status = $itemStatus;

                        $deviceTables = ['lamps', 'dimmers', 'dali_devices', 'tapes'];

                        foreach ($deviceTables as $table)
                        {
                            $sql = parent::$db->query("SELECT * FROM `$table` WHERE `id_object` = $idObject");
                            if ($sql->fetch(PDO::FETCH_OBJ))
                            {
                                $deviceTable = $table;
                                break;
                            }
                        }


                        if ($table == 'dali_devices')
                        {

                            if ($itemValue[0]->type == 'cct')
                            {
                                $brightness = $itemValue[0]->brightness;
                                $cct = $itemValue[0]->cct;
                                Dali::setColorTemperature($idObject, $cct);
                                Dali::setBrightness($idObject, $brightness);
                            }

                            if ($itemValue[0]->type == 'dim')
                            {
                                $brightness = $itemValue[0]->brightness;
                                Dali::setBrightness($idObject, $brightness);
                            }
                                
                            if ($status == 'off' || (isset($brightness) && $brightness == 0)) Dali::daliOff($idObject);
                            else Dali::daliOn($idObject);
                        }

                        
                        if ($table == 'tapes')
                        {
                            $tape = new Tape($idObject);
                            // $color = 0;
                            // $shade = 0;
                            // $bright = 0;
                            // $wbright = 0;

                            //Пришли конкретные значения для ленты
                            if ($itemValue[0]->type == 'hsv' || $itemValue[0]->type == 'hsv_dim')
                            {
                                $hue = $itemValue[0]->h;
                                $saturation = $itemValue[0]->s;
                                $brightness = $itemValue[0]->v;
                                $tape->tapeSetColor($hue, $saturation);
                                $tape->tapeSetBrightness($brightness);
                            }
                            
                            if ($itemValue[0]->type == 'hsv_dim' || $itemValue[0]->type == 'dim' || $itemValue[0]->type == 'cct')
                            {
                                $brightness = $itemValue[0]->brightness;
                                $tape->tapeSetBrightness($brightness);
                            }

                            if ($itemValue[0]->type == 'cct')
                            {
                                $cct = $itemValue[0]->cct;
                                // Переводим в проценты значение цветовой температуры
                                $cctInPercent = round((($cct-1000)*100)/(10000-1000));
                                $tape->tapeSetTemperature($cctInPercent);
                            }
                        
                            if ($status == 'off' || (isset($brightness) && $brightness == 0)) $tape->tapeOff();
                            else $tape->tapeOn();

                            // $newObject->setStatus($status, true, false);
                        }

                        if ($table == 'dimmers' || $table == 'lamps')
                        {
                            $dimmer = new Dimmer($idObject);
                            $brightness = $itemValue[0]->brightness;
                            //Если значение димера не установлено, то значит сработало одиночное нажатие на кнопку димера
                            if (!isset($brightness))
                            {
                                if ($status == 'off') $brightness = 0;
                                else $brightness = $dimmer->getValue();
                            }
                            
                            if ($brightness == 0) $status = 'off'; //Выключаем диммер
                            $dimmer->setValue($brightness);
                            $newObject->setStatus($status, true, false);
                        }
                        
                    break;

                    case  'curtain':

                        $curtain = new Curtain($idObject);

                        if (!isset($set_value))
                        {
                            if ($itemStatus == "on") $curtain->open();
                            if ($itemStatus == "off") $curtain->close();
                        }
                        else $curtain->setPercent($set_value);

                    break;
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
    public function updateItem($idItem, $itemStatus = null)
    {

        global $localsocket;

        $itemStatus = mb_strtolower($itemStatus);

        // Обновляем данные в таблице представлений
       // parent::$db->exec("UPDATE `view_items` SET `status` = IF(`type`='temp', `status`, '$itemStatus'),
         //                   `value` = IF(`type`='temp', '$itemStatus', `value`) WHERE `view_items`.`id` = $idItem");

        if($itemStatus)
        parent::$db->exec("UPDATE `view_items` SET `status` = '$itemStatus'
                            WHERE `view_items`.`id` = $idItem");


        // Получаем необходимые данные из таблицы представлений для итемов, которые связаны с данным объектом

        $sql = parent::$db->query("SELECT * FROM `view_items` WHERE `id`= $idItem");

        while ($viewItem = $sql->fetch(PDO::FETCH_OBJ)) {

           //Если тип итема - это термостат, гигростат или светостат, то отдаем нужную структуру 
            if($viewItem->type == 'termostat'){

                $itemTermostat = self::getTermostats($viewItem, 'string');
                $message = '{ "status": "itemChange", "items": ['.$itemTermostat.']}';

            } elseif ($viewItem->type == 'hygrostat') {

                $itemHygrostat = self::getHygrostats($viewItem, 'string');
                $message = '{ "status": "itemChange", "items": ['.$itemHygrostat.']}';
            } elseif ($viewItem->type == 'lightstat') {
                $itemLightstat = self::getLightstats($viewItem, 'string');
                $message = '{ "status": "itemChange", "items": ['.$itemLightstat.']}';
            } else
            //иначе отдаем структуру обычного итема
                $message = '{ "status": "itemChange", "items": [{"id":'.$viewItem->id.',
            "type":"'.$viewItem->type.'","status":"'.$viewItem->status.'",
            "icon":"'.$viewItem->icon.'",
            "title":"'.$viewItem->title.'",
            "value":"'.$viewItem->value.'",
            "params":"'.$viewItem->params.'"}]}';


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
     * получение объекта и метода и прочих данных, которые соответвуют представлению
     *
     * @param  int $item_id - ид метода
     * @return object
     */
    function getObjectAndMethod($idItem)
    {
        $sql = parent::$db->query("SELECT `id_object`, `on_method`, `off_method` FROM `view_items` WHERE `id`= $idItem");

        if($sql->rowCount() > 0)
        return $sql->fetch(PDO::FETCH_OBJ);
        else return false;
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
            ($viewObject->type == 'dimmer') ||
            ($viewObject->type == 'light-own') ||
            ($viewObject->type == 'label') ||
            ($viewObject->type == 'link') ||
            ($viewObject->type == 'conditioner') ||
            ($viewObject->type == 'socket') ||
            ($viewObject->type == 'tape') ||
            ($viewObject->type == 'customizable_light') ||
            ($viewObject->type == 'curtain'))

            return array('id' => (int)$viewObject->id,
                'type' => $viewObject->type,
                'icon' => $viewObject->icon,
                'title' => $viewObject->title,
                'value' => $viewObject->value,
                'status' => $viewObject->status,
                'left' => $viewObject->position_left,
                'params' => $viewObject->params,
                'color' => $viewObject->color,
                'top' => $viewObject->position_top);

        // Если тип объекта термометр
        if ($viewObject->type == 'termostat') {
            return self::getTermostats($viewObject, 'array');
        }

        // Если тип объекта гигрометр
        if ($viewObject->type == 'hygrostat') {
            return self::getHygrostats($viewObject, 'array');
        }

         // Если тип объекта светостат
         if ($viewObject->type == 'lightstat') {
            return self::getLightstats($viewObject, 'array');
        }

        // Если тип объекта датчик температуры
        if ($viewObject->type == 'pressurestat') {
            return self::getPressurestats($viewObject, 'array');
        }

         // Если тип объекта датчик углекислого газа
         if ($viewObject->type == 'carbdioxide') {
            return self::getCarbdioxide($viewObject, 'array');
        }
        
    }

    /** Функция отдает параметры выбранного димера
     *
     * @param int $idDimmer
     * @return json
     */
    function getDimmer($idDimmer) {

        $sql = parent::$db->query("SELECT `dimmers`.`value` AS value,
                                   `view_items`.`description` AS description,
                                    `objects`.`status` AS state
                                   FROM `dimmers`
                                   INNER JOIN objects ON objects.id = dimmers.id_object 
                                   INNER JOIN view_items ON view_items.id_object = objects.id 
                                   WHERE view_items.id = $idDimmer");

        if($sql->rowCount() > 0) {

            $dimmer = $sql->fetch(PDO::FETCH_OBJ);

            //Если нужно отправлять статус ON, когда value > 0
            /*
            if ($dimmer->value > 0)
                $state = 'OFF';
            else
                $state = 'ON';
            */

            $items = array('id' => $idDimmer,
                'type' => 'dimmer',
                'name' => $dimmer->description,
                'status' => $dimmer->state,
                'value' => $dimmer->value);

          return  $json = json_encode(array('status' => 'dimerLoad', 'entity'=> $items));

        }  else System::addlog('error','Данные для отображения"'.$idDimmer.'"" не найдены', 'dimmer');

    }

    /**
     * Функция выполняет метод кнопки в зависимости от состоятиния
     */
    static private function runButtonMethod($newObject, $itemStatus, $onMethod, $offMethod, $itemId, $itemType) {
        //Для кнопки без фиксации не выполняем действий по смене статуса
        if (($itemType != 'button') && ($itemType != 'label')) {
            /*Меняем состояние итема и состояние объекта, физическим портом не управляем.
            Это действие выполняем в любом случае. Повторно статус отправляем еще в Action, если прочитали с устройства
            */
            $newObject->setStatus($itemStatus, false, false);

            if ($itemStatus == 'on') {
                $idMethod = $onMethod;
                $methodParam = 'on';
            }
            else {
                $idMethod = $offMethod;
                $methodParam = 'off';
            } 
        } 
        else {
            $idMethod = $onMethod;
            $methodParam = 'on';
        }


        if($idMethod) {
            //Выполняем действие для данного объекта
            Action::runAction($idMethod, 'view', $newObject->id, null, $methodParam);
            return true;
        } else return false;

    }


    public function getCounts() {

        $day = date('w')-1;
        $week_start = date('Y-m-d', strtotime('-'.$day.' days'));
        $todayDate = date('Y-m-d');

        $month_start = date('Y').date('-m').'-01';
        $year_start = date('Y').'-01-01';


        $sql = parent::$db->query("SELECT id, name, type, today_value, total_value, unit FROM counts");

        while ($counts = $sql->fetch(PDO::FETCH_OBJ)) {



            $sqlweekly = parent::$db->query("SELECT SUM(value) AS value FROM `graph_counts` 
                                              WHERE datetime >= '$week_start' 
                                              AND datetime <= '$todayDate' AND id_count=$counts->id");


            $weekly = (string)ceil($sqlweekly->fetch(PDO::FETCH_OBJ)->value);
            if($weekly == null) $weekly = "0";


            $sqlmonthly = parent::$db->query("SELECT SUM(value) AS value FROM `graph_counts` 
                                              WHERE datetime >=  '$month_start' 
                                              AND datetime <= '$todayDate' AND id_count=$counts->id");


            $monthly = (string)ceil($sqlmonthly->fetch(PDO::FETCH_OBJ)->value);
            if($monthly == null) $monthly = "0";


            $sqlyearly = parent::$db->query("SELECT SUM(value) AS value FROM `graph_counts` 
                                              WHERE datetime >=  '$year_start'
                                              AND datetime <= '$todayDate' AND id_count=$counts->id");


            $yearly = (string)ceil($sqlyearly->fetch(PDO::FETCH_OBJ)->value);
            if($yearly == null) $yearly = "0";


            $totalValue = ceil($counts->total_value);
            $nulls = 6-strlen($totalValue);

            $total ='';

            for($i=0;$i<$nulls;$i++) {
                $total .= '0';
                }
            $total .= $totalValue;
            

            $today = (string)ceil($counts->today_value);


            $counts_array = array('id'=>(int)$counts->id, 'name'=>$counts->name, 'type'=>$counts->type, 'unit'=>$counts->unit,
                                    'today_value'=>$today, 'total_value'=>(string)$total,
                                    'weekly_value'=>(string)ceil($weekly), 'monthly_value'=>(string)ceil($monthly), 'yearly_value'=>(string)ceil($yearly));
            $countsarr[] = $counts_array;


        }

        return $json = json_encode(array('status'=>'countsLoad', 'counts'=>$countsarr));

    }


    public function getCountsGraphs($idCount, $period) {

         if (($period == 'month')||($period == '(period)'))
        $sql = "SELECT DATE_FORMAT(datetime,'%d') AS date, SUM(value) AS value FROM `graph_counts` 
                WHERE `datetime` >= NOW() - INTERVAL 30 DAY AND id_count = $idCount
                GROUP BY DAY(datetime)  ORDER BY datetime";

        if ($period == 'year')
            $sql = "SELECT DATE_FORMAT(datetime,'%b') AS date, SUM(value) AS value FROM `graph_counts` 
                    WHERE `datetime` >= NOW() - INTERVAL 12 MONTH  AND id_count = $idCount
                    GROUP BY MONTH(datetime)  ORDER BY datetime";


        $sqlquery = parent::$db->query($sql);
        if($sqlquery ->rowCount() > 0) {
		while ($counts = $sqlquery->fetch(PDO::FETCH_OBJ)) {

		    $graphs_value[] = array('date'=>$counts->date, 'value'=>$counts->value);
		}
        } else $graphs_value = null;
        
        return  $json = json_encode(array('status'=>'countsGraphsLoad', 'id_count' => $idCount, 'values'=>$graphs_value));


    }


    /**
     * Функция отдает пункты меню в json
     */
    public function getMenu()
    {
        //Сначала находим все родительские пункты
        $sql = "SELECT id, title, link, image, params FROM menu WHERE active = 1 AND parent = 0 ORDER BY sort";

        $queryParrent = parent::$db->query($sql);
        while ($parrent = $queryParrent->fetch(PDO::FETCH_OBJ)) {

            unset($childs);

            $sql = "SELECT id, title, link, image, params FROM menu WHERE active = 1 AND parent = {$parrent->id} ORDER BY sort";
            $queryChild = parent::$db->query($sql);


            while ($child = $queryChild->fetch(PDO::FETCH_OBJ)) {

                $imageChild = explode('.',$child->image)[0];
                $childs[] = array('image'=>$imageChild, 'title'=>$child->title, 'link'=>$child->link, 'params'=>$child->params);
            }

            $imageParrent = explode('.',$parrent->image)[0];
            $parents[] = array('image'=>$imageParrent, 'title'=>$parrent->title, 'link'=>$parrent->link,
                'params'=>$parrent->params, 'childs'=>$childs);
        }

        return  $json = json_encode(array('status'=>'menuLoad', 'elements' => $parents));

    }

    public function getPage($link)
    {
        //Запрашиваем данные о нужных страницах
        $sql = "SELECT id, name, type FROM pages WHERE link = '$link' ORDER BY sort";

        $queryPage = parent::$db->query($sql);
        while ($page = $queryPage->fetch(PDO::FETCH_OBJ)) {

            unset($elements);

            //Запрашиваем данные для компонентов страницы
            $sql = "SELECT `id`, `name`, `type`, `image`, `value`, `position`, `handle` FROM elements WHERE page = {$page->id} AND active = 1 AND parent = 0 ORDER BY position, sort";

            $queryElements = parent::$db->query($sql);
            if($queryElements->rowCount() > 0) {
            while ($element = $queryElements->fetch(PDO::FETCH_OBJ)) {

                $image = explode('.', $element->image)[0];

                //Если тип - аккордеон, то ищем еще дочерние элементы
                if($element->type == 'accordeon') {
                    $sqlChildElements = "SELECT id, name, type, image, value, `handle` FROM elements WHERE active = 1 AND parent = {$element->id} ORDER BY sort";
                    $queryChildElements = parent::$db->query($sqlChildElements);
                    while ($childElement = $queryChildElements->fetch(PDO::FETCH_OBJ)) {

                        $imageChild = explode('.', $childElement->image)[0];

                        $value[] = array('id' => $childElement->id, 'image' => $imageChild, 'title' => $childElement->name, 'type' => $childElement->type,
                            'handle' => $childElement->handle, 'value' => json_decode($childElement->value));
                    }
                    $element->value = json_encode($value);

                    $elements[] = array('id' => (string)$element->id, 'image'=>$image, 'title'=>$element->name, 'type'=>$element->type,
                        'position' => (string)$element->position, 'handle' => $element->handle, 'elements'=>json_decode($element->value));
                }else
                    $elements[] = array('id' => (string)$element->id, 'image'=>$image, 'title'=>$element->name, 'type'=>$element->type,
                        'position' => (string)$element->position, 'handle' => $element->handle, 'value'=>json_decode($element->value));

            }

            $pages[] = array('id' => (string)$page->id, 'name' => $page->name, 'elements' => $elements);
            }
        }

        return  $json = json_encode(array('status'=>'pageLoad', 'pages' => $pages));
    }


    /**
     * Получение внутренней страницы для выбранного элемента
     */
    public function getInternalPage($idElement)
    {

        //Запрашиваем данные о нужных внутренних страницах
        $sql = "SELECT internalPages.id AS intpage, elements.type, elements.page AS idpage, 
                elements.handle AS handle, elements.id_object AS idObject
                FROM internalPages 
                INNER JOIN elements ON internalPages.idelement = elements.id     
                WHERE idelement = $idElement ORDER BY sort";


        $queryPage = parent::$db->query($sql);
        while ($page = $queryPage->fetch(PDO::FETCH_OBJ)) {


            $elementsSQL = "SELECT id, name, type, value FROM elements WHERE page = {$page->idpage}
                                AND position=1 ORDER BY sort";

            $queryElements = parent::$db->query($elementsSQL);
            while ($element = $queryElements->fetch(PDO::FETCH_OBJ)) {

                $elements[] = array('id' => (string)$element->id, 'title'=>$element->name, 'type'=>$element->type,
                    'position' => "1", 'value'=>json_decode($element->value));
            }


            if ($page->handle == 'automode') {

                // Извлекаем значения температуры для котла
                $valuesSQL = "SELECT id, t_out, t_water FROM boiler_auto WHERE id_object = {$page->idObject}
                                ORDER BY `t_out`";
                $queryValues = parent::$db->query($valuesSQL);

                while ($element = $queryValues->fetch(PDO::FETCH_OBJ)) {

                    $values[] = array('id' => (string)$element->id, 't_out' => (string)$element->t_out, 't_water' => (string)$element->t_water);
                }
                    $items[] = array('elements' => $elements, 'values' => $values);

                      $json = json_encode(array('status'=>'internalPage', 'type' => 'BoilerAuto',
                          'idPage' => (string)$page->intpage, 'pages' => $items));
                echo $json;
                return $json;

            } elseif($page->handle == 'manualmode') {

                $manualValueSQL = "SELECT set_value, min_value, max_value, feed_heat_temp FROM boiler_manual 
                                    INNER JOIN boiler ON boiler.id_object = boiler_manual.id_object 
                                    WHERE boiler_manual.id_object = {$page->idObject}";
                $queryManValue = parent::$db->query($manualValueSQL);

                if ($queryManValue->rowCount() != 0) {
                    $manualValue = $queryManValue->fetch(PDO::FETCH_OBJ);

                    $cur_value = round($manualValue->feed_heat_temp);
                    $set_value = round($manualValue->set_value);

                    $values[] = array('cur_value' => (string)$cur_value, 'set_value' => (string)$set_value,
                        'min' => (string)$manualValue->min_value, 'max' => (string)$manualValue->max_value);


                    $items[] = array('elements' => $elements, 'valuesManual' => $values);

                    $json = json_encode(array('status' => 'internalPage', 'type' => 'BoilerManual',
                        'idPage' => (string)$page->intpage, 'pages' => $items));

                    echo $json;
                    return $json;
                }
            } else {

                $waterValueSQL = "SELECT set_value, min_value, max_value, water_temp, target_water_temp FROM boiler_water 
                                    INNER JOIN boiler ON boiler.id_object = boiler_water .id_object 
                                    WHERE boiler_water.id_object = {$page->idObject}";
                $queryWaterValue = parent::$db->query($waterValueSQL);

                if ($queryWaterValue->rowCount() != 0) {
                    $manualValue = $queryWaterValue->fetch(PDO::FETCH_OBJ);

                    $cur_value = round($manualValue->water_temp);
                    $set_value = round($manualValue->target_water_temp);

                    $values[] = array('cur_value' => (string)$cur_value, 'set_value' => (string)$set_value,
                        'min' => (string)$manualValue->min_value, 'max' => (string)$manualValue->max_value);


                    $items[] = array('elements' => (string)$elements, 'valuesManual' => (string)$values);

                    $json = json_encode(array('status' => 'internalPage', 'type' => 'BoilerWaterManual',
                        'idPage' => (string)$page->intpage, 'pages' => (string)$items));

                    echo $json;
                    return $json;
                }
            }
        }
    }


    public function setInternalPage($items) {

 
    }

    public function sendPage($idPage) {

        global $localsocket;
        
        $sql = "SELECT link FROM pages WHERE id = $idPage";
        $queryResult = parent::$db->query($sql);
        if ($queryResult->rowCount()>0) {
        
        $page = $queryResult->fetch(PDO::FETCH_OBJ);
              	
        
        $res_json = (['user' => 'all', 'message' => $this->getPage($page->link)]);
        $res_json = json_encode($res_json);
        $instance = stream_socket_client($localsocket);
        // send message
        fwrite($instance,  $res_json . "\n");
        
        }

    }


    /**
     * Отправляет значение элементов для страниц
     * @param $idElement
     * @param $data
     */
    public function sendPageElement($data) {

        global $localsocket;
/*
        $data_array = json_decode($data);

        foreach ($data_array->items AS $element) {
            $id = $element->id;
            $value = $element->value;
            $message[] = array('id' => $id, 'value' => $value);
        }
*/
        $res_json = (['user' => 'all', 'message' => $data ]);
        $res_json = json_encode($res_json);

        $instance = stream_socket_client($localsocket);
        // send message
        fwrite($instance,  $res_json . "\n");
    }


    /** Функция отдает параметры выбранного кондицинера
     *
     */
    public function getConditioner($idConditionerView)
    {
        $sql = parent::$db->query(" SELECT `conditioner_types`.`temperature` AS 'temp_range',
                                       `conditioner_types`.`mode` AS 'modes',
                                       `conditioner_types`.`fan` AS 'fans',
                                       `conditioner_types`.`vdir` AS 'vdirs',
                                       `conditioner_types`.`hdir` AS 'hdirs',
                                       `objects`.`status` AS 'state',
                                       `conditioners`.`temp`,
                                       `conditioners`.`mode`,
                                       `conditioners`.`fan`,
                                       `conditioners`.`vdir`,
                                       `conditioners`.`hdir`
                                FROM `conditioner_types`
                                INNER JOIN `conditioners` ON `conditioners`.`type` = `conditioner_types`.`id`
                                INNER JOIN `objects` ON `objects`.`id` = `conditioners`.`id_object`
                                INNER JOIN `view_items` ON `view_items`.`id_object` = `objects`.`id` 
                                WHERE `view_items`.`id` = $idConditionerView");

        if($sql->rowCount() > 0)
        {
            $conditioner = $sql->fetch(PDO::FETCH_OBJ);

            $operationModes = json_decode($conditioner->modes);
            $operationModes = get_object_vars($operationModes);
            $operationModes = array_keys($operationModes);

            $fanModes = json_decode($conditioner->fans);
            $fanModes = get_object_vars($fanModes);
            $fanModes = array_keys($fanModes);
            $fanModes = array_map('strval', $fanModes);

            $temperatureRange = json_decode($conditioner->temp_range);

            $items = [
                'id' => $idConditionerView,
                'type' => 'conditioner',
                'state' => $conditioner->state,
                'min' => $temperatureRange->min,
                'max' => $temperatureRange->max,
                'temp' => $conditioner->temp,
                'operation_modes' => $operationModes,
                'operation' => $conditioner->mode,
                'fan_modes' => $fanModes,
                'fan' => $conditioner->fan
            ];


            if (isset($conditioner->vdirs))
            {
                $vdir_modes = json_decode($conditioner->vdirs);
                $vdir_modes = get_object_vars($vdir_modes);
                $vdir_modes = array_keys($vdir_modes);
                $vdir_modes = array_map('strval', $vdir_modes);
                $items += [
                    'vdir_modes' => $vdir_modes,
                    'vdir' => $conditioner->vdir
                ];
            }

            if (isset($conditioner->hdirs))
            {
                $hdir_modes = json_decode($conditioner->hdirs);
                $hdir_modes = get_object_vars($hdir_modes);
                $hdir_modes = array_keys($hdir_modes);
                $hdir_modes = array_map('strval', $hdir_modes);
                $items += [
                    'hdir_modes' => $hdir_modes,
                    'hdir' => $conditioner->hdir
                ];
            }
            
            return  $json = json_encode(array('status' => 'conditionerLoad', 'entity'=> $items));

        } else System::addlog('error','Данные для отображения"'.$idConditionerView.'"" не найдены', 'conditioner');
    }

    /**
     * Функия отдает параметры RGB ленты
     */
    public function getTape(int $idTape) {
        $sql = parent::$db->query("SELECT tapes.id, h, s, v, w, tapes.type, tapes.status FROM tapes
         INNER JOIN objects ON objects.id = tapes.id_object 
         INNER JOIN view_items ON view_items.id_object = objects.id 
         WHERE view_items.id = $idTape");
         
        if($sql->rowCount() > 0) {

            $tape = $sql->fetch(PDO::FETCH_OBJ);

            $sqlcolors =  parent::$db->query("SELECT value FROM colors WHERE type = 'hsv'");
            while ($color = $sqlcolors->fetch(PDO::FETCH_OBJ)) {
                if($color)
                $colors_array[] = $color->value;
            }


            $items = array(
                'id' => $idTape, 
                'type' => $tape->type,
                'status' => $tape->status,
                'h' => $tape->h,
                's' => $tape->s,
                'v' => $tape->v,
                'w' => $tape->w,
                'colors' => $colors_array,
            );

            return  $json = json_encode(array('status' => 'tapeLoad', 'entity'=> $items));


        }   else System::addlog('error','Данные для отображения"'.$idTape.'"" не найдены', 'tape');

    }

    /** Функция отдает параметры выбранного настраиваемого источника света
     *
     * @param int $customizableLightViewId
     * @return json
     */
    function getCustomizableLight($customizableLightViewId)
    {
        $isDeviceFound = false;
        $items = ['id' => (int)$customizableLightViewId];
        
        $sql = parent::$db->query(" SELECT `dali_devices`.`brightness`,
                                           `dali_devices`.`cct`,
                                           `objects`.`status`
                                    FROM `dali_devices`
                                    INNER JOIN `objects` ON `objects`.`id` = `dali_devices`.`id_object`
                                    INNER JOIN `view_items` ON `view_items`.`id_object` = `objects`.`id`
                                    WHERE `view_items`.`id` = $customizableLightViewId");
        if ($customizableLight = $sql->fetch(PDO::FETCH_OBJ))
        {
            $isDeviceFound = true;
            if (isset($customizableLight->cct))
            {
                $items += [
                    'type' => 'cct',
                    'cct' => $customizableLight->cct
                ];
            }
            else $items += ['type' => 'brightness'];
            
            $items += [
                'status' => $customizableLight->status,
                'brightness' => $customizableLight->brightness
            ];
        }


        $sql = parent::$db->query(" SELECT `tapes`.`h`,
                                           `tapes`.`s`,
                                           `tapes`.`v`,
                                           `tapes`.`w`,
                                           `tapes`.`cct`,
                                           `tapes`.`type`,
                                           `objects`.`status`
                                    FROM `tapes`
                                    INNER JOIN `objects` ON `objects`.`id` = `tapes`.`id_object` 
                                    INNER JOIN `view_items` ON `view_items`.`id_object` = `objects`.`id`
                                    WHERE `view_items`.`id` = $customizableLightViewId");
        if ($customizableLight = $sql->fetch(PDO::FETCH_OBJ))
        {
            $isDeviceFound = true;
            $items += ['status' => $customizableLight->status];

            $sqlcolors = parent::$db->query("SELECT `value` FROM `colors` WHERE type = 'hsv'");
            while ($color = $sqlcolors->fetch(PDO::FETCH_OBJ)) if($color) $colors_array[] = $color->value;

            if ($customizableLight->type == "RGBW")
            {
                $items += [
                    'type' => "hsv_dim",
                    'h' => $customizableLight->h,
                    's' => $customizableLight->s,
                    'v' => $customizableLight->v,
                    'brightness' => $customizableLight->w,
                    'colors' => $colors_array
                ];
            }
            if ($customizableLight->type == "RGB")
            {
                $items += [
                    'type' => "hsv",
                    'h' => $customizableLight->h,
                    's' => $customizableLight->s,
                    'v' => $customizableLight->v,
                    'colors' => $colors_array
                ];
            }
            
            if ($customizableLight->type == "W")
            {
                $items += [
                    'type' => "dim",
                    'brightness' => $customizableLight->w
                ];
            }

            if ($customizableLight->type == "CCT")
            {
                // Переводим проценты в значение cct в диапазоне от 1000 до 10000
                $cct = 1000+((10000-1000)/100*$customizableLight->cct);
                $items += [
                    'type' => "cct",
                    'cct' => $cct,
                    'brightness' => $customizableLight->w
                ];
            }
        }

        $sql = parent::$db->query(" SELECT `dimmers`.`value`, `objects`.`status`
                                    FROM `dimmers`
                                    INNER JOIN `objects` ON `objects`.`id` = `dimmers`.`id_object` 
                                    INNER JOIN `view_items` ON `view_items`.`id_object` = `objects`.`id`
                                    WHERE `view_items`.`id` = $customizableLightViewId");
        if($customizableLight = $sql->fetch(PDO::FETCH_OBJ))
        {
            $isDeviceFound = true;
            $items += [
                'type' => 'dim',
                'status' => mb_strtolower($customizableLight->status),
                'brightness' => $customizableLight->value
            ];
        }

        $sql = parent::$db->query(" SELECT `lamps`.`value`, `objects`.`status`
                                    FROM `lamps`
                                    INNER JOIN `objects` ON `objects`.`id` = `lamps`.`id_object` 
                                    INNER JOIN `view_items` ON `view_items`.`id_object` = `objects`.`id`
                                    WHERE `view_items`.`id` = $customizableLightViewId");
        if($customizableLight = $sql->fetch(PDO::FETCH_OBJ))
        {
            $isDeviceFound = true;
            $items += [
                'type' => 'dim',
                'status' => mb_strtolower($customizableLight->status),
                'brightness' => $customizableLight->value
            ];
        }
        
        if ($isDeviceFound) return json_encode(array('status' => 'customizableLightLoad', 'entity'=> $items));
    }

    /** Функция отдает параметры выбранного привода штор
     *
     * @param int $curtainViewId
     * @return json
     */
    function getCurtain($curtainViewId)
    {
        $items = ['id' => (int)$curtainViewId];
        
        $sql = parent::$db->query(" SELECT `curtains`.`place`,
                                           `curtains`.`percent`,
                                           `objects`.`status`
                                    FROM `curtains`
                                    INNER JOIN `objects` ON `objects`.`id` = `curtains`.`id_object`
                                    INNER JOIN `view_items` ON `view_items`.`id_object` = `objects`.`id`
                                    WHERE `view_items`.`id` = $curtainViewId");
        if ($curtain = $sql->fetch(PDO::FETCH_OBJ))
        {
            $items += ['type' => $curtain->place];
            if ($curtain->place == 'rs485') $items += ['openRate' => $curtain->percent];
            return json_encode(array('status' => 'curtainLoad', 'entity'=> $items));
        }      
    }
}



