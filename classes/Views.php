<?php

/** Класс работы с визуальными элементами плана дома
 */
class Views extends System
{

    /** Получаем список итемов для главной страницы, упаковываем его в json и отдаем скрипту server.php, который
     отправляет этот json клиенту, запрашивающему данные
     */
    function get_room_items(){

        //Находим комнаты, кроме главной нулевой комнаты
        $sql_rooms = parent::$db->query("SELECT * FROM `rooms` WHERE `id`!=0 ORDER BY `sort`");

        while ($rooms_obj = $sql_rooms->fetch(PDO::FETCH_OBJ)) {

            unset($items_array);

            //Отдаем элементы
            $sql = parent::$db->query("SELECT * FROM `view_items` WHERE `type`='i' AND `room` = $rooms_obj->id AND `active` = 1 ORDER BY `sort`");
            while ($view_obj = $sql->fetch(PDO::FETCH_OBJ)) {
                // Если тип объекта кнопка или переключатель
                if (($view_obj->name == 'button') || ($view_obj->name == 'light') || ($view_obj->name == 'light-own') || ($view_obj->name == 'socket'))
                    $item = array('id' => (int)$view_obj->id, 'name' => $view_obj->name, 'on_image' => $view_obj->on_image, 'off_image' => $view_obj->off_image, 'on_title' => $view_obj->on_title, 'off_title' => $view_obj->off_title, 'status' => $view_obj->status, 'left' => $view_obj->position_left, 'top' => $view_obj->position_top);

                // Если тип объекта термометр или гигрометр
                if (($view_obj->name == 'temp') || ($view_obj->name == 'humidity'))
                    $item = array('id' => (int)$view_obj->id, 'name' => $view_obj->name, 'on_image' => $view_obj->on_image, 'off_image' => $view_obj->off_image, 'value' => $view_obj->value, 'left' => $view_obj->position_left, 'top' => $view_obj->position_top);

                $items_array[] = $item;

                $room = array('id' => (int)$rooms_obj->id,'name' => $rooms_obj->name,'image' => $rooms_obj->image,'style' => $rooms_obj->style, 'items' => $items_array);

            }

            $room_array[] = $room;

        }

        return $json = json_encode(array('status'=>'RoomItems', 'items'=>$room_array));

    }

    /** Получаем список итемов, которые относятся к главной комнате */
    function get_main_items(){


            //Отдаем элементы
            $sql = parent::$db->query("SELECT * FROM `view_items` WHERE `type`='i' AND `room` = 0 AND `active` = 1 ORDER BY `sort`");
            while ($view_obj = $sql->fetch(PDO::FETCH_OBJ)) {
                // Если тип объекта кнопка или переключатель
                if (($view_obj->name == 'button') || ($view_obj->name == 'light') || ($view_obj->name == 'light-own') || ($view_obj->name == 'socket'))
                    $item = array('id' => (int)$view_obj->id, 'name' => $view_obj->name, 'on_image' => $view_obj->on_image, 'off_image' => $view_obj->off_image, 'on_title' => $view_obj->on_title, 'off_title' => $view_obj->off_title, 'status' => $view_obj->status, 'left' => $view_obj->position_left, 'top' => $view_obj->position_top);

                // Если тип объекта термометр или гигрометр
                if (($view_obj->name == 'temp') || ($view_obj->name == 'humidity'))
                    $item = array('id' => (int)$view_obj->id, 'name' => $view_obj->name, 'on_image' => $view_obj->on_image, 'off_image' => $view_obj->off_image, 'value' => $view_obj->value, 'left' => $view_obj->position_left, 'top' => $view_obj->position_top);

                $items_array[] = $item;

            }


        return $json = json_encode(array('status'=>'MainItems', 'items'=>$items_array));

    }







    /** Получаем список итемов, которые относятся к сценам */
    function get_scenes_items(){

        //Находим сцены в таблице сцен, у которых статус=активен
        $sql_scenes = parent::$db->query("SELECT * FROM `scenes` WHERE `active`=1 ORDER BY `sort`");
        while ($scenes_obj = $sql_scenes->fetch(PDO::FETCH_OBJ)) {

            unset($items_array);
            //Отдаем элементы
            $sql = parent::$db->query("SELECT * FROM `view_items` WHERE `type`='i' AND `scene` = $scenes_obj->id AND `active` = 1 ORDER BY `sort`");
            while ($view_obj = $sql->fetch(PDO::FETCH_OBJ)) {
                // Если тип объекта кнопка или переключатель
                if (($view_obj->name == 'button') || ($view_obj->name == 'light') || ($view_obj->name == 'light-own') || ($view_obj->name == 'socket'))
                    $item = array('id' => (int)$view_obj->id, 'name' => $view_obj->name, 'on_image' => $view_obj->on_image, 'off_image' => $view_obj->off_image, 'on_title' => $view_obj->on_title, 'off_title' => $view_obj->off_title, 'status' => $view_obj->status, 'left' => $view_obj->position_left, 'top' => $view_obj->position_top);

                // Если тип объекта термометр или гигрометр
                if (($view_obj->name == 'temp') || ($view_obj->name == 'humidity'))
                    $item = array('id' => (int)$view_obj->id, 'name' => $view_obj->name, 'on_image' => $view_obj->on_image, 'off_image' => $view_obj->off_image, 'value' => $view_obj->value, 'left' => $view_obj->position_left, 'top' => $view_obj->position_top);

                $items_array[] = $item;

                $scenes = array('id' => (int)$scenes_obj->id,'name' => $scenes_obj->name,'image' => $scenes_obj->image,'label' => $scenes_obj->label, 'items' => $items_array);

            }

            $scenes_array[] = $scenes;

        }

        return $json = json_encode(array('status'=>'ScenesItems', 'items'=>$scenes_array));

    }







    /** Получаем список итемов, которые относятся к сценам */
    function get_temperatures(){

        $sql = parent::$db->query("SELECT * FROM `temperatures` ORDER BY `sort`");
        while ($temp = $sql->fetch(PDO::FETCH_OBJ)) {

            $temp_array = array('id'=>(int)$temp->id, 'name'=>$temp->name, 'normal'=>$temp->normal, 'night'=>$temp->night, 'eco'=>$temp->eco);
            $temperatures[] = $temp_array;
        }

        $json = json_encode(array('status'=>'TemperaturesLoad', 'items'=> $temperatures));
        return $json;
    }




    /** Получаем список итемов для страницы настроек, упаковываем в json и отправляем клиенту, через скрипт server.php */
    function get_all_settings(){
        $sql = parent::$db->query("SELECT * FROM `view_items` WHERE `type`='s'  ORDER BY `id`");
        while ($view_obj = $sql->fetch(PDO::FETCH_OBJ))
        {

            if (($view_obj->name=='setting1')||($view_obj->name=='message')||($view_obj->name=='plan'))
                $settings_array = array('id'=>(int)$view_obj->id, 'name'=>$view_obj->name, 'status'=>$view_obj->status);

            // Если тип объекта термометр или гигрометр
            if (($view_obj->name=='temp')||($view_obj->name=='humidity'))
                $settings_array = array('id'=>(int)$view_obj->id, 'name'=>$view_obj->name, 'value'=>$view_obj->value);

            if ($view_obj->name=='checkbox')
                $settings_array = array('id'=>(int)$view_obj->id, 'name'=>$view_obj->name, 'status'=>$view_obj->status, 'title'=>$view_obj->on_title);

            if ($view_obj->name=='eco')
                $settings_array = array('id'=>(int)$view_obj->id, 'name'=>$view_obj->name, 'value'=>$view_obj->value, 'status'=>$view_obj->status, 'title'=>$view_obj->on_title);

            if (($view_obj->name=='radio')){
                $status = explode(',',$view_obj->status);
                $settings_array = array('id'=>(int)$view_obj->id, 'name'=>$view_obj->name, 'value'=>$view_obj->value, 'status'=>$status, 'title'=>$view_obj->on_title);

            }

/*
            // Если тип объекта группа термометров
            if ($view_obj->name=='temps'){

                $term_array = null;

                $subitems_array = explode(',',$view_obj->items);

                $sql2 = parent::$db->query("SELECT * FROM `view_items` 
                                          WHERE `type`='t' AND (`id` = $subitems_array[0] OR `id` = $subitems_array[1] OR `id` = $subitems_array[2])  
                                          ORDER BY `id`");

                while ($term = $sql2->fetch(PDO::FETCH_OBJ)) {
                    $terms = array('id'=>(int)$term->id, 'name'=>$term->name, 'value'=>$term->value);
                    $term_array[] = $terms;
                }
                $settings_array = array('id'=>(int)$view_obj->id, 'name'=>$view_obj->name, 'title'=>$view_obj->on_title, 'items'=>$term_array);
            }
*/

            $settings[] = $settings_array;
        }

         $json = json_encode(array('status'=>'settingsLoad', 'settings'=>$settings));
        //print_r($json);
        return $json;
    }

    /** Получаем список событий для страницы настроек, упаковываем в json и отправляем клиенту, через скрипт server.php */
    function get_all_events(){
        $sql = parent::$db->query("SELECT * FROM `view_items` WHERE `type`='e'  ORDER BY `id`");
        while ($view_obj = $sql->fetch(PDO::FETCH_OBJ)) {
            $events_array = array('id'=>(int)$view_obj->id, 'name'=>$view_obj->name, 'title'=>$view_obj->title, 'status'=>$view_obj->status, 'value'=>$view_obj->value, 'date'=>$view_obj->date);
            $events[] = $events_array;
        }

        return $json = json_encode(array('status'=>'eventsLoad', 'events'=>$events));

    }


    /**  Получаем данные от клиента и выполняем действия в зависимости от этого  */
    function res_data($data){

        $data_array = json_decode($data);


        //Если клиент отправил запрос на изменение состояния термометра на странице термометров
        if ($data_array->status=='temperaturesChange'){

            $item_id = $data_array->item->id;
            $item_value = $data_array->item->value;
            $item_key = $data_array->item->key;

            //Обновляем данные в таблице температур
            parent::$db->exec("UPDATE `temperatures` SET  `$item_key` = $item_value  WHERE `id` = $item_id");

        }




        //Если клиент отправил запрос на изменение состояния итема
        if ($data_array->status=='itemChange'){

            $item_id = $data_array->items[0]->id;
            $item_name = $data_array->items[0]->name;
            $item_status = $data_array->items[0]->status;
            $item_value = $data_array->items[0]->value;

            //Получаем id объекта из таблицы представлений
            $object = new Objects();
            $id_object = $object->id = $object->view_oject($item_id);
            $object->view = $item_id;

            //Если объект у итема существует
            if ($id_object!=null){

                //Если объект является термостатом или гигрометром
                if(($item_name=='temp')||($item_name=='humidity')){


                    if ($item_value == '') $item_value = 'NULL';

                    //Обновляем данные в таблице представлений с учетом пришедших данных от клиента
                    parent::$db->exec("UPDATE `view_items` SET `status` = '$item_status', `value` = $item_value  WHERE `view_items`.`id` = $item_id");

                    //Добавляем данные в таблицу термостатов и больше ничего не делаем
                    $termostat = new Thermostats();
                    $termostat->set_temperature($id_object,$item_value);

                } else { //Если объект является обычной кнопкой


                    //Меняем состояние связанного элемента  и одновременно состояние итема
                    $object->set_status($item_status, false);

                    //Запускаем соответствующий скрипт на выполнение.
                    $script = new Scripts();
                    $script->runscript($id_object, null, $item_status);

                }

            }


        }



    }


    /** Обновление состояния итема в таблице представлений и у клиентов*/
    function update_item(int $id_item, string $item_status){

        global $localsocket;

        $item_status = mb_strtolower($item_status);

        // Обновляем данные в таблице представлений
        parent::$db->exec("UPDATE `view_items` SET `status` = '$item_status' WHERE `view_items`.`id` = $id_item");

        // Получаем необходимые данные из таблицы представлений для итемов, которые связаны с данным объектом

        $sql = parent::$db->query("SELECT * FROM `view_items` WHERE `id`= $id_item");

        while ($ret_item = $sql->fetch(PDO::FETCH_OBJ)) {

            // Формируем json строку для отправки клиентам
           // $item = array( 'id' => $ret_item->id, 'name' => $ret_item->name, 'status' => $ret_item->status, 'left' => $ret_item->position_left, 'top' => $ret_item->position_top);
           // $message = array('status' => 'itemChange', 'items'=>[$item]);

            $message = '{ "status": "itemChange", "items": [{"id":'.$ret_item->id.',
            "name":"'.$ret_item->name.'","status":"'.$ret_item->status.'",
            "on_image":"'.$ret_item->on_image.'","off_image":"'.$ret_item->off_image.'",
            "on_title":"'.$ret_item->on_title.'","off_title":"'.$ret_item->off_title.'",
            "left":"'.$ret_item->position_left.'","top":"'.$ret_item->position_top.'"}]}';


            $res_json = (['user' => 'all', 'message' => $message]);
            $res_json = json_encode($res_json);


            //Отправляем клиенту измененные данные
            // connect to a local tcp-server
            $instance = stream_socket_client($localsocket);
            // send message
            fwrite($instance,  $res_json . "\n");

        }

    }



}