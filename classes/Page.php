<?php

class Page extends System {

    public function setIntPages($fulldata)
    {
        $data_array = json_decode($fulldata);

        if (isset($data_array->items[0]->type)) $type = $data_array->items[0]->type;
        if (isset($data_array->items[0]->mode)) $mode = $data_array->items[0]->mode;
        if (isset($data_array->items[0]->value)) $value = $data_array->items[0]->value;
        if (isset($data_array->items[0]->idPage)) $idPage = $data_array->items[0]->idPage;
        $idObject = null;


        //Определяем id объекта для страницы
        $sql = "SELECT `id_object`, `handle`
                FROM `elements`
                INNER JOIN `internalPages`
                ON `internalPages`.`idElement` = `elements`.`id`
                WHERE `internalPages`.`id` = $idPage";

        $queryElements = parent::$db->query($sql);

        if ($queryElements->rowCount() != 0)
        {
            $element = $queryElements->fetch(PDO::FETCH_OBJ);
            $idObject = $element->id_object;
        }

        if ($type == 'BoilerAuto')
        {
            if ($mode == 'add') 
            {
                $temperatures = explode(":",$value);
                parent::$db->query("INSERT INTO boiler_auto (`id`, `t_out`, `t_water`, `id_object`)
                                          VALUES (null,  $temperatures[0], $temperatures[1], $idObject)");
            } 
            elseif ($mode == 'del')
            {
                parent::$db->query("DELETE FROM boiler_auto WHERE id_object = $idObject AND t_out = $value");
            }
        }

        if ($type == 'BoilerManual')
        {
            $boiler = new Boiler($idObject);
            $boiler->setParam($element->handle, $value);
        }
    }

    //Если пользователь изменил что-то на страницах меню
    public function changePageItem($idElement, $elementStatus)
    {
        //Находим элемент страницы в таблице элементов
        $sql = "SELECT * FROM `elements` WHERE `id` = $idElement";
        $queryElements = parent::$db->query($sql);

        if ($queryElements->rowCount() > 0)
        {
            $element = $queryElements->fetch(PDO::FETCH_OBJ);

		    if ($element->handle == 'weather_compensation')
            {
                $boiler = new Boiler($element->id_object);
                if ($elementStatus == 'on') $boiler->setMode('wc');
                else $boiler->setMode('manual');
            }
                
            //Меняем состояние элемента
            $this->setElementStatus($element, $elementStatus);

            //Перезагружаем страницу
            $view = new Views();
            $view->sendPage($element->page);
        }
    }

    /**
     * Устанавливаем значение для элемента по его хендлу
     * @param $element int - элемент, у котрого меняем статус
     * @param $status string - новый статус для элемента
     */
    public function setElementStatus($element, $status)
    {
        parent::$db->exec("UPDATE `elements` SET `status` = '$status' WHERE `id` = $element->id");
    }

    /**
     * Ищем элемент по его хендлу
     */
    private function findElementByHandle($page, $idObject, $hanle)
    {
        $sql = "SELECT * FROM `elements` 
                WHERE `handle` = '$hanle'
                AND `id_object` = $idObject
                AND `page` = $page";
        $queryElements = parent::$db->query($sql);

        if ($queryElements->rowCount() != 0)
        {
            return $queryElements->fetch(PDO::FETCH_OBJ);
        }
    }
}
