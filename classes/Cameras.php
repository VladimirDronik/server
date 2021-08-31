<?php

/**
Класс работы с изображением от камер видеонаблюдения
 */
class Cameras
{
    /**
     * Извлекает из БД все доступные камеры и отдает название и превью в сокет
     */
    public function getAllCameras()
    {
        $sql = parent::$db->query("SELECT cameras.id, cameras.name, cameras.image, rooms.name AS roomName FROM cameras INNER JOIN rooms 
            ON rooms.id = cameras.room WHERE cameras.active=1 ORDER BY cameras.sort");
        while ($cameras = $sql->fetch(PDO::FETCH_OBJ)) {


            $cameras_array = array('id'=>(int)$cameras->id, 'name'=>$cameras->name,
                'room'=>$cameras->roomName, 'image'=>$cameras->image);
            $camsarr[] = $cameras_array;

        }

        return $json = json_encode(array('status'=>'allCamerasLoad', 'cameras'=>$camsarr));
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

        return $json = json_encode(array('status'=>'cameraLinkLoad', 'link'=>$camera->link, 'type'=>$camera->type));
    }
}