<?php

/**
 * Класс работы с изображением от камер видеонаблюдения
 */
class Cameras extends Device
{
    /**
     * Извлекает из БД все доступные камеры и отдает название и превью в сокет
     */
    public function getAllCameras()
    {

        $query = parent::$db->query("SHOW TABLES FROM smarthome LIKE 'cameras';");
        
        if($query->fetch(PDO::FETCH_OBJ))
        {

            $sql = parent::$db->query("SELECT id, name, image, base64_image, type
                                       FROM cameras
                                       WHERE active=1 ORDER BY sort");
		
            while ($cameras = $sql->fetch(PDO::FETCH_OBJ))
            {
// var_dump ($cameras);
		        $cameras_array = array('id'=>(int)$cameras->id, 
                                       'name'=>$cameras->name,
                                       'type'=>$cameras->type,
		                        //     'room'=>$cameras->roomName,
                                       'image'=>$cameras->image,
                                       'base64_image'=>$cameras->base64_image
                                    );
		        $camsarr[] = $cameras_array;
                // var_dump ($cameras_array);
		    }
            
            $json = json_encode(array('status'=>'allCamerasLoad', 'cameras'=>$camsarr));
		    return $json;

        } 
        else return $json = json_encode(array('status'=>'allCamerasLoad', 'cameras'=>null));

    }

    /**
     * Извлекает из БД ссылку на камеру
     *
     * @param $id - ИД камеры для которой запрашиваем ссылку
     * @return - json строка с сылкой на камеру
     */
    public function getCamera($id)
    {
        $sql = parent::$db->query("SELECT link, type 
                                   FROM cameras 
                                   WHERE id = $id");
        $camera = $sql->fetch(PDO::FETCH_OBJ);
        
        self::addPath($id);

        $json = json_encode(array('status'=>'cameraLinkLoad', 'link'=>$camera->link, 'type'=>$camera->type));
        var_dump ($json);
        return $json;
    }

    public static function getRtspSnapshots()
    {
        $workdir = getenv('WORK_DIR');
        $sql = parent::$db->query("SELECT cameras.id, cameras.link, recorders.ip_address, recorders.login, recorders.password
                                   FROM cameras
                                   INNER JOIN recorders ON recorders.id = cameras.recorder_id
                                   WHERE cameras.recorder_id IS NOT NULL AND cameras.active = 1");

        while ($rtsp = $sql->fetch(PDO::FETCH_OBJ))
        {
            exec("(ffmpeg -y -rtsp_transport tcp -i rtsp://$rtsp->login:$rtsp->password@$rtsp->link " .
                 "-frames:v 1 -s 384*256 -ss 00:00:00.01 " .
                 "$workdir/adm/public/ela/images/cameras_snapshots/camera$rtsp->id.jpeg " .
                 "-hide_banner -v 0) >> /dev/null 2>&1", $output, $retval);
            
                 if (!$retval)
            {
                $img = file_get_contents("$workdir/adm/public/ela/images/cameras_snapshots/camera$rtsp->id.jpeg");
                $base = base64_encode($img);
                parent::$db->query("UPDATE cameras SET base64_image = " . parent::$db->quote($base) . " WHERE `id` = $rtsp->id");

            }
        }
        // usleep (1500000);
    }

    public function addPath($camera_id)
    {
        $sql = parent::$db->query("SELECT cameras.link, recorders.ip_address, recorders.login, recorders.password
                                   FROM cameras 
                                   INNER JOIN recorders ON recorders.id = cameras.recorder_id
                                   WHERE cameras.id = $camera_id");

        $rtsp = $sql->fetch(PDO::FETCH_OBJ);

        $data = array("source" => "rtsp://$rtsp->login:$rtsp->password@$rtsp->ip_address/$rtsp->link");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:9997/v3/config/paths/add/camera'.$camera_id);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $res = curl_exec($ch);
        if (curl_errno($ch)) echo 'Error:' . curl_error($ch);
        curl_close($ch);
        // var_dump ($res);
    }
}