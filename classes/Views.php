<?php

/** Класс работы с визуальными элементами плана дома
 */
class Views extends System
{

    /** Получаем список итемов для главной страницы, упаковываем его в json и отдаем скрипту server.php, который
     отправляет этот json клиенту, запрашивающему данные
     */
    function get_all_items(){

        $sql = parent::$db->query("SELECT * FROM `view_items` WHERE `type`='i' ORDER BY `id`");
        while ($view_obj = $sql->fetch(PDO::FETCH_OBJ)) {
            // Если тип объекта кнопка или переключатель
            if (($view_obj->name=='light')||($view_obj->name=='light-own')||($view_obj->name=='socket'))
                $items_array = array('id'=>(int)$view_obj->id, 'name'=>$view_obj->name, 'status'=>$view_obj->status, 'left'=>$view_obj->position_left, 'top'=>$view_obj->position_top);

            // Если тип объекта термометр или гигрометр
            if (($view_obj->name=='temp')||($view_obj->name=='humidity'))
                $items_array = array('id'=>(int)$view_obj->id, 'name'=>$view_obj->name, 'value'=>$view_obj->value, 'left'=>$view_obj->position_left, 'top'=>$view_obj->position_top);

            $items[] = $items_array;

        }

       return $json = json_encode(array('status'=>'itemsLoad', 'items'=>$items));

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
                $settings_array = array('id'=>(int)$view_obj->id, 'name'=>$view_obj->name, 'status'=>$view_obj->status, 'title'=>$view_obj->title);

            if ($view_obj->name=='eco')
                $settings_array = array('id'=>(int)$view_obj->id, 'name'=>$view_obj->name, 'value'=>$view_obj->value, 'status'=>$view_obj->status, 'title'=>$view_obj->title);

            if (($view_obj->name=='radio')){
                $status = explode(',',$view_obj->status);
                $settings_array = array('id'=>(int)$view_obj->id, 'name'=>$view_obj->name, 'value'=>$view_obj->value, 'status'=>$status, 'title'=>$view_obj->title);

            }


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
                $settings_array = array('id'=>(int)$view_obj->id, 'name'=>$view_obj->name, 'title'=>$view_obj->title, 'items'=>$term_array);
            }


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


        //Если клиент отправил запрос на изменение состояния итема
        if ($data_array->status=='itemChange'){

            $item_id = $data_array->items[0]->id;
            $item_name = $data_array->items[0]->name;
            $item_status = $data_array->items[0]->status;
            $item_value = $data_array->items[0]->value;
            $item_title = $data_array->items[0]->title;


            if ($item_value == '') $item_value = 'NULL';

            //Обновляем данные в таблице представлений с учетом пришедших данных от клиента
            parent::$db->exec("UPDATE `view_items` SET `status` = '$item_status', `value` = $item_value, `title` = '$item_title'  WHERE `view_items`.`id` = $item_id");

            //Получаем id объекта из таблицы представлений
            $object = new Objects();
            $id_object = $object->view_oject($item_id);

            //Изменяем состояние объекта в БД

            //Если объект у итема существует
            if ($id_object!=null){

                //Если объект является термостатом или гигрометром
                if(($item_name=='temp')||($item_name=='humidity')){

                    //Добавляем данные в таблицу термостатов и больше ничего не делаем
                    $termostat = new Thermostats();
                    $termostat->set_temperature($id_object,$item_value);

                } else { //Если объект является обычной кнопкой

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