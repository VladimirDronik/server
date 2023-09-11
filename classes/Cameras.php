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
        
        // self::getRtspSnapshots();

		$sql = parent::$db->query("SELECT cameras.id, cameras.name, cameras.image, rooms.name AS roomName
                                   FROM cameras 
                                   INNER JOIN rooms ON rooms.id = cameras.room
                                   WHERE cameras.active=1 ORDER BY cameras.sort");
		
        while ($cameras = $sql->fetch(PDO::FETCH_OBJ))
        {

		    $cameras_array = array('id'=>(int)$cameras->id, 
                                   'name'=>$cameras->name,
		                           'room'=>$cameras->roomName,
                                   'image'=>$cameras->image);
		    $camsarr[] = $cameras_array;

		}
        $json = json_encode(array('status'=>'allCamerasLoad', 'cameras'=>$camsarr));
        var_dump($json);
		return $json;
        } else
        	return $json = json_encode(array('status'=>'allCamerasLoad', 'cameras'=>null));
            
    }

    /**
     * Извлекает из БД ссылку на камеру
     *
     * @param $id - ИД камеры для которой запрашиваем ссылку
     * @return - json строка с сылкой на камеру
     */
    public function getCamera($id)
    {
        $sql = parent::$db->query("SELECT link, type FROM cameras WHERE id = $id");
        $camera = $sql->fetch(PDO::FETCH_OBJ);
        
        self::addPath($id);

        $json = json_encode(array('status'=>'cameraLinkLoad', 'link'=>$camera->link, 'type'=>$camera->type));
        var_dump ($json);
        return $json;
    }

    public function getRtspSnapshots()
    {
        $sql = parent::$db->query("SELECT id, rtsp_link FROM cameras WHERE rtsp_link IS NOT NULL AND active = 1");

        while ($rtsp = $sql->fetch(PDO::FETCH_OBJ))
        {
            // passthru("(ffmpeg -y -rtsp_transport tcp -i $rtsp->rtsp_link -frames:v 1 -s 384*256 -ss 00:00:00.01 \
            // /opt/touchon/workdir/adm/public/ela/images/cameras_snapshots/camera".$rtsp->id.".jpeg \
            // -hide_banner -v 0 &) >> /dev/null 2>&1");
            exec("(ffmpeg -y -rtsp_transport tcp -i $rtsp->rtsp_link -frames:v 1 -s 384*256 -ss 00:00:00.01 \
            /opt/touchon/workdir/adm/public/ela/images/cameras_snapshots/camera".$rtsp->id.".jpeg \
            -hide_banner -v 0 &) >> /dev/null 2>&1");
            // exec("ffmpeg -y -rtsp_transport tcp -i rtsp://admin:zxcvzxcv123@192.168.1.100/ISAPI/Streaming/channels/101 \
            // -frames:v 1 -s 384*256 -ss 00:00:00.01 \
            // /opt/touchon/workdir/adm/public/ela/images/cameras_snapshots/camera_test.jpeg \
            // -hide_banner -v 0");
        }
        usleep (1500000);
    }

    public function addPath($camera_id)
    {
        $sql = parent::$db->query("SELECT rtsp_link, channel FROM cameras WHERE id = $camera_id");
        $stream = $sql->fetch(PDO::FETCH_OBJ);

        $data = array('source' => $stream->rtsp_link);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:9997/v2/config/paths/add/camera'.$camera_id);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $res = curl_exec($ch);
        if (curl_errno($ch)) echo 'Error:' . curl_error($ch);
        curl_close($ch);
        var_dump ($res);
    }
}