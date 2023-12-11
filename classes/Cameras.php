<?php

/**
 * Класс работы с изображением от камер видеонаблюдения
 */
class Cameras extends Device
{

    private $validRtspLink;

    /**
     * Извлекает из БД все доступные камеры и отдает название и превью в сокет
     */
    static public function getAllCameras()
    {

        $query = parent::$db->query("SHOW TABLES FROM smarthome LIKE 'cameras';");
        
        if($query->fetch(PDO::FETCH_OBJ)) {

            $sql = parent::$db->query("SELECT id, name, type,
                                       CASE
                                       WHEN type = 'media_server' THEN base64_image
                                       WHEN type = 'direct_link' THEN image
                                       END AS image
                                       FROM cameras
                                       WHERE active=1 ORDER BY sort");
		
            while ($cameras = $sql->fetch(PDO::FETCH_OBJ)) {
            
                $cameras_array = array('id'    => (int)$cameras->id, 
                                       'name'  => $cameras->name,
                                       'type'  => $cameras->type,
                                       'image' => $cameras->image);
                $camsarr[] = $cameras_array;
		    }
            
            $json = json_encode(array('status'=>'allCamerasLoad', 'cameras'=>$camsarr));
		    return $json = json_encode(array('status'=>'allCamerasLoad', 'cameras'=>$camsarr));
        }

        else return $json = json_encode(array('status'=>'allCamerasLoad', 'cameras'=>null));
    }


    /**
     * Извлекает из БД ссылку на камеру
     *
     * @param $id - ИД камеры для которой запрашиваем ссылку
     * @return - json строка с сылкой на камеру
     */
    static public function getCamera($id) {
        
        $sql = parent::$db->query("SELECT type,
                                   CASE
                                   WHEN type = 'media_server' THEN 'null'
                                   WHEN type = 'direct_link' THEN link
                                   END AS link
                                   FROM cameras WHERE id = $id");

        $camera = $sql->fetch(PDO::FETCH_OBJ);
        
        if ($camera->type == 'media_server') self::addPath($id);

        $json = json_encode(array('status'=>'cameraLinkLoad', 'id'=>$id, 'link'=>$camera->link, 'type'=>$camera->type));
        var_dump ($json);
        return $json;
    }


    static public function getRtspSnapshots(int $recorder_id = null)
    {
        if ($recorder_id)
            $sql = parent::$db->query("SELECT cameras.id FROM cameras
                                        INNER JOIN recorders ON recorders.id = cameras.recorder_id
                                        WHERE cameras.recorder_id = $recorder_id
                                        AND cameras.active = 1");
        else
            $sql = parent::$db->query("SELECT id FROM cameras
                                        WHERE recorder_id IS NOT NULL
                                        AND type = 'media_server'
                                        AND active = 1");

        if ($sql->rowCount() > 0)
        {
            while ($camera = $sql->fetch(PDO::FETCH_OBJ))
            {
                exec("(ffmpeg -y -rtsp_transport tcp -i " . self::getValidRtspLink($camera->id) . 
                    " -frames:v 1 -s 384*256 -ss 00:00:00.05 " .
                    getenv('WORK_DIR') . "/adm/public/ela/images/cameras_snapshots/camera$camera->id.jpeg " .
                    "-hide_banner -v 0) >> /dev/null 2>&1", $output, $retval);
        
                if (!$retval)
                {
                    $img = file_get_contents(getenv('WORK_DIR') . "/adm/public/ela/images/cameras_snapshots/camera$camera->id.jpeg");
                    $base = base64_encode($img);
                    parent::$db->query("UPDATE cameras SET base64_image = " . parent::$db->quote($base) . " WHERE `id` = $camera->id");
                }
            }
        }
    }


    static public function deleteRtspSnapshots(int $recorder_id)
    {
        $sql = parent::$db->query("SELECT cameras.id FROM cameras
                                       INNER JOIN recorders ON recorders.id = cameras.recorder_id
                                       WHERE cameras.recorder_id = $recorder_id
                                       AND cameras.active = 1");

        while ($camera = $sql->fetch(PDO::FETCH_OBJ))
            exec("rm -f " . getenv('WORK_DIR') . "/adm/public/ela/images/cameras_snapshots/camera$camera->id.jpeg");
    }


    static private function getValidRtspLink(int $camera_id)
    {
        $sql = parent::$db->query("SELECT cameras.link, recorders.ip_address, recorders.login, recorders.password
                                   FROM cameras
                                   INNER JOIN recorders ON recorders.id = cameras.recorder_id
                                   WHERE cameras.id = $camera_id");
        
        $rtsp = $sql->fetch(PDO::FETCH_OBJ);

        $decryptedPassword = self::passwordDecrypt($rtsp->password, getenv('SECRET_PASSWORD_KEY'));    
        $rtspTemplates = array('$login', '$password', '$ip_address');
        $rtspValues = array($rtsp->login, $decryptedPassword, $rtsp->ip_address);
        return str_replace($rtspTemplates, $rtspValues, $rtsp->link);
    }


    static private function passwordDecrypt(string $encryptedPassword, string $key) 
    {
        $method = 'aes-256-cbc';
        $ivSize = openssl_cipher_iv_length($method);
        $encryptedPassword = base64_decode($encryptedPassword);
        $iv = substr($encryptedPassword, 0, $ivSize);
        $encrypted = substr($encryptedPassword, $ivSize);
        return openssl_decrypt($encrypted, $method, $key, 0, $iv);
    }


    static private function addPath($camera_id)
    {
        if (!self::ifPathExists($camera_id))
            {
                $data = array("source" => self::getValidRtspLink($camera_id));
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
            }
    }

    static private function ifPathExists($camera_id)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:9997/v3/config/paths/get/camera'.$camera_id);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $res = curl_exec($ch);
        if (curl_errno($ch)) echo 'Error:' . curl_error($ch);
        else 
        {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http_code != 200) $result = false;
            else $result = true;
        }
        curl_close($ch);
        return $result;
    }
}