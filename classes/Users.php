<?php

/** Класс работы с пользователями и их устройствами */
class Users extends System
{

    /** Получаем id девайса с которого делается запрос и сравниваем его с имеющимися в таблице devuser, а также отправляем
     * дефолтный дашборд */
    function checkuser($device){

        global $localsocket;


        $sql = parent::$db->query("SELECT * FROM `devusers` WHERE `dev_id`= '$device'");

        if ($result_dev = $sql->fetch(PDO::FETCH_OBJ)){
            //Если нашли юзера, то шлем ему его дефолтный дашборд

            $message = '{ "status": "success", "defscene":'.$result_dev->def_scene.'}';
            $success = true;
        }
        else {
            //Возврат ошибки при несуществующем клиенте

            $message = '{ "status": "error", "errorMessage": "access denied"}';
            $success = false;
       }

        //$message = '{ "status": "success", "defscene":0}';
        //$success = true;

        $res_json = (['user' => $device, 'message' => $message]);
        $res_json = json_encode($res_json);

        $instance = stream_socket_client($localsocket);
        // send message
        fwrite($instance,  $res_json . "\n");

        return $success;
    }




    /** Получаем массив с устройствами юзеров и отправляем клиенту */
    function get_all_users(){

        global $localsocket;

        $sql = parent::$db->query("SELECT * FROM `devusers` ORDER BY `id`");
        while ($users_obj = $sql->fetch(PDO::FETCH_OBJ)) {
            $users_array = array('id' => $users_obj->dev_id, 'dashboard' => $users_obj->def_scene);
            $users[] = $users_array;
        }

        $res_json = json_encode(array('status'=>'usersLoad', 'events'=>$users));

/*
        $instance = stream_socket_client($localsocket);
        // send message
        fwrite($instance,  $res_json . "\n");
*/
        return $res_json;
    }


    /** Функция добавления нового устройства юзера */
    function adduser($dev_id,$def_scene, $old_id = null){

        parent::$db->query("INSERT INTO devusers (`id`, `dev_id`, `def_scene`)
                                      VALUES (null, '$dev_id',$def_scene)");

    }

    /** Функция редактирования устройства юзера */
    function edituser($dev_id, $def_scene, $old_id){

        parent::$db->query("UPDATE devusers SET `dev_id` = '$dev_id', `def_scene` = $def_scene
                                         WHERE `dev_id` = '$old_id'");

    }

    /** Функция удаления устройства юзера */
    function deleteuser($dev_id,$def_scene, $old_id = null){

        parent::$db->query("DELETE FROM devusers WHERE `dev_id` = '$dev_id'");

    }
}