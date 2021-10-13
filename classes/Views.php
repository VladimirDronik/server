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

            unset($items_array, $roomsArray, $roomsInGroup);


            //Отдаем элементы
            $sql = parent::$db->query("SELECT `view_items`.`id`,
                                              `view_items`.`type`, 
                                              `view_items`.`description`, 
                                              `view_items`.`icon`,
                                              `view_items`.`status`,
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
     * Отдаем значение температуры визуальному отображению термостата
     * @param object $view -  итем с термостатом
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


                $curTemp = round($hygrostat->current);
                $newTemp = (float)$hygrostat->optimal;


                if($typeOutput == 'array')
                    $item = array('id' => (int)$view->id, 'type' => $view->type, 'icon' => $view->icon,
                        'cur_value' => $curTemp,  'set_value' => $newTemp, 'title' => $hygrostat->title,
                        'left' => $view->position_left, 'top' => $view->position_top,  'params' => $hygrostat->params);
                else

                    $item = '{"id":'.$view->id.',
            "type":"'.$view->type.'","cur_value":"'.$curTemp.'",
            "set_value":"'.$newTemp.'",
            "title":"'.$view->title.'",
            "left":"'.$view->position_left.'",
            "top":"'.$view->position_top.'",
            "params":"'.$hygrostat->params.'"
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

            $itemID = $data_array->items[0]->id;
            $itemDescription = $data_array->items[0]->description;
            $itemType = $data_array->items[0]->type;
            $itemStatus = $data_array->items[0]->status;
            $itemValue = $data_array->items[0]->value;
            $set_value = $data_array->items[0]->set_value;


            //Получаем id объекта из таблицы представлений
            $object = $this->getObjectAndMethod($itemID);

            if ($object->id_object != null) {

                $idObject = $object->id_object;
                $onMethod = $object->on_method;
                $offMethod = $object->off_method;

                $newObject = new Objects();
                $newObject->select($idObject);


                //Если объект является термостатом или гигрометром
                if (($itemType == 'termostat') || ($itemType == 'temp') ||  ($itemType == 'humidity')) {


                    if ($set_value == '') $set_value = 'NULL';

                    //Обновляем данные в таблице представлений с учетом пришедших данных от клиента
                    parent::$db->exec("UPDATE `view_items` SET `status` = '$itemStatus', `value` = $set_value  WHERE `view_items`.`id` = $itemID");


                    //Добавляем данные в таблицу термостатов и больше ничего не делаем
                    $termostat = new Thermostats();
                    $termostat->set_temperature($idObject, $set_value);

                    //Отпарвляем данные о температуре остальным клиентам
                    self::updateItem($itemID);


                } elseif (($itemType == 'switch') || ($itemType == 'button')) { //Если объект является переключателем или кнопкой


                    self::updateItem($itemID, $itemStatus);

                    if (!self::runButtonMethod($newObject, $itemStatus, $onMethod, $offMethod, $itemID, $itemType))
                        System::addlog('error', 'Метод для кнопки "' . $itemDescription . '"" не определен', 'button');

                } elseif ($itemType == 'label') {

                    if (!self::runButtonMethod($newObject, $itemStatus, $onMethod, $offMethod, $itemID, $itemType))
                        System::addlog('error', 'Метод для кнопки "' . $itemDescription . '"" не определен', 'button');


                } elseif ($itemType == 'dimmer') {

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

           //Если тип итема - это термометр, то отдаем структуру термометра, иначе отдаем структуру обычного итема
            if($viewItem->type == 'termostat'){

                $itemTermostat = $this->getTermostats($viewItem, 'string');
                $message = '{ "status": "itemChange", "items": ['.$itemTermostat.']}';

            }  else

                $message = '{ "status": "itemChange", "items": [{"id":'.$viewItem->id.',
            "type":"'.$viewItem->type.'","status":"'.$viewItem->status.'",
            "icon":"'.$viewItem->icon.'",
            "title":"'.$viewItem->title.'",
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
            ($viewObject->type == 'socket'))

            return array('id' => (int)$viewObject->id,
                'type' => $viewObject->type,
                'icon' => $viewObject->icon,
                'title' => $viewObject->title,
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

            if ($itemStatus == 'on')
                $idMethod = $onMethod;
            else
                $idMethod = $offMethod;

        } else $idMethod = $onMethod;


        if($idMethod) {
            //Выполняем действие для данного объекта
            Action::runAction($idMethod, 'view', $newObject->id);
            return true;
        } else return false;

    }

    public function getCounts() {

        $day = date('w')-1;
        $week_start = date('Y-m-d', strtotime('-'.$day.' days'));
        $todayDate = date('Y-m-d');

        $month_start = date('Y').date('m').'-01';
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

        if ($period == 'month')
        $sql = "SELECT DATE_FORMAT(datetime,'%d') AS date, SUM(value) AS value FROM `graph_counts` 
                WHERE `datetime` >= NOW() - INTERVAL 30 DAY AND id_count = $idCount
                GROUP BY DAY(datetime)  ORDER BY datetime";

        if ($period == 'year')
            $sql = "SELECT DATE_FORMAT(datetime,'%b') AS date, SUM(value) AS value FROM `graph_counts` 
                    WHERE `datetime` >= NOW() - INTERVAL 12 MONTH  AND id_count = $idCount
                    GROUP BY MONTH(datetime)  ORDER BY datetime";


        $sqlquery = parent::$db->query($sql);
        while ($counts = $sqlquery->fetch(PDO::FETCH_OBJ)) {

            $graphs_value[] = array('date'=>$counts->date, 'value'=>$counts->value);
        }
        
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
            $sql = "SELECT `id`, `name`, `type`, `image`, `value`, `position` FROM elements WHERE page = {$page->id} AND active = 1 AND parent = 0 ORDER BY position, sort";

            $queryElements = parent::$db->query($sql);
            while ($element = $queryElements->fetch(PDO::FETCH_OBJ)) {

                $image = explode('.', $element->image)[0];

                //Если тип - аккордеон, то ищем еще дочерние элементы
                if($element->type == 'accordeon') {
                    $sqlChildElements = "SELECT id, name, type, image, value FROM elements WHERE active = 1 AND parent = {$element->id} ORDER BY sort";
                    $queryChildElements = parent::$db->query($sqlChildElements);
                    while ($childElement = $queryChildElements->fetch(PDO::FETCH_OBJ)) {

                        $imageChild = explode('.', $childElement->image)[0];

                        $value[] = array('id' => $childElement->id, 'image' => $imageChild, 'title' => $childElement->name, 'type' => $childElement->type,
                            'value' => json_decode($childElement->value));
                    }
                    $element->value = json_encode($value);

                    $elements[] = array('id' => $element->id, 'image'=>$image, 'title'=>$element->name, 'type'=>$element->type,
                        'position' => $element->position, 'elements'=>json_decode($element->value));
                }else
                    $elements[] = array('id' => $element->id, 'image'=>$image, 'title'=>$element->name, 'type'=>$element->type,
                        'position' => $element->position, 'value'=>json_decode($element->value));

            }

            $pages[] = array('id' => $page->id, 'name' => $page->name, 'elements' => $elements);
        }

        return  $json = json_encode(array('status'=>'pageLoad', 'pages' => $pages));
    }
}