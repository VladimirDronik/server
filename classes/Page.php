<?php


class Page extends System {

    public function setIntPages($fulldata)
    {
        $data_array = json_decode($fulldata);

        if (isset($data_array->items[0]->type)) $type = $data_array->items[0]->type;
        if (isset($data_array->items[0]->mode)) $mode = $data_array->items[0]->mode;
        if (isset($data_array->items[0]->value)) $value = $data_array->items[0]->value;
        if (isset($data_array->items[0]->idPage)) $idPage = $data_array->items[0]->idPage;
        $idObject = 0;

        //Определяем id объекта для страницы
        $sql = "SELECT id_object FROM elements INNER JOIN internalPages ON internalPages.idElement = elements.id
                WHERE internalPages.id = $idPage";

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
        // else 
        // {
        //     parent::$db->exec("UPDATE boiler SET `target_water_temp` = $value WHERE `id_object` = $idObject");
        // }

        if ($type == 'BoilerManual')
        {
            $boiler = new Boiler($idObject);
            $boiler->setParam('ch_setpoint_temp', $value);
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

	        if	($element->id_object != null)
	        {
		        //Определяем тип объекта по id
		        $object = new Objects();
		        $object->select($element->id_object);

		        if ($element->type == 'switch')
                {
		            if($object->type == 'boiler') $this->setModeBoiler($element, $elementStatus);
		        }
            }
        }
    }

    /**
     * Активируем изменения, если что-то у элемента, к которому привязан объект типа boiler
     * @param $element
     * @param $elementStatus
     */
    private function setModeBoiler($element, $elementStatus)
    {
        // Для реализации смены режимов работы котла
        // определяем хэндл элемента, его состояние и меняем состояние у объекта.
        // Также находим связанный хэндл (если есть) для того, чтобы поменять его
        if ($element->handle == 'auto_mode')
        {
            if ($elementStatus == 'on') $mode = 'auto';
            else $mode = 'manual';
            $boiler = new Boiler($element->id_object);
            $boiler->setHeatingMode($mode);
        }

        //Меняем состояние элемента
        $this->setElementStatus($element, $elementStatus);

        //Перезагружаем страницу
        $view = new Views();
        $view->sendPage($element->page);
    }


    /**
     * Устанавливаем значение для элемента по его хендлу
     * @param $element int - элемент, у котрого меняем статус
     * @param $handle string - хендл элемента, у которого будем менять статус
     * @param $status string - новый статус для элемента
     */
    public function setElementStatus($element, $status)
    {
        //Устанавливаем статус для элемента, если хендл у него automode или manualmode (элемент котла)
        if ($element->handle == 'auto_mode')
        {
            parent::$db->exec("UPDATE `elements` SET `status` = '$status' WHERE `id` = $element->id");
        }
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
