<?php


class Page extends System {

    public function setIntPages($fulldata) {

        $data_array = json_decode($fulldata);

        $type = $data_array->items[0]->type;
        $mode = $data_array->items[0]->mode;
        $value = $data_array->items[0]->value;
        $idPage = $data_array->items[0]->idPage;
        $idObject = 0;

        //Определяем id объекта для страницы
        $sql = "SELECT id_object FROM elements INNER JOIN internalPages ON internalPages.idElement = elements.id
                WHERE internalPages.id = $idPage";

        $queryElements = parent::$db->query($sql);

        if ($queryElements->rowCount() != 0) {
            $element = $queryElements->fetch(PDO::FETCH_OBJ);
            $idObject = $element->id_object;
        }

        if ($type == 'BoilerAuto') {
            if ($mode == 'add') {

                $temperatures = explode(":",$value);

                parent::$db->query("INSERT INTO boiler_auto (`id`, `t_out`, `t_water`, `id_object`)
                                          VALUES (null,  $temperatures[0], $temperatures[1], $idObject)");


            } elseif ($mode == 'del') {

                parent::$db->query("DELETE FROM boiler_auto WHERE id_object = $idObject AND t_out = $value");


            }
        } elseif ($type == 'BoilerManual') { //Если manual выбрано

            parent::$db->exec("UPDATE boiler_manual SET `set_value` = $value
                                       WHERE `id_object` = $idObject");

        } else {
            parent::$db->exec("UPDATE boiler SET `target_water_temp` = $value
                                       WHERE `id_object` = $idObject");
        }

    }


    //Если пользователь изменил что-то на страницах меню
    public function changePageItem($idElement, $elementStatus){

        //TODO: убрать это, когда в приложении сделают нормальную передачу id нажатого элемента
        $idElement = $idElement-1;

        //Находим элемент страницы в таблице элементов
        $sql = "SELECT * FROM elements
                WHERE id = $idElement";


        $queryElements = parent::$db->query($sql);

        if ($queryElements->rowCount() != 0) {
            $element = $queryElements->fetch(PDO::FETCH_OBJ);

            //Определяем тип объекта по id
            $object = new Objects();
            $object->select($element->id_object);

            if ($element->type == 'switch') {

                if($object->type == 'boiler') {
                  $this->setModeBoiler($element, $elementStatus);
                }

            }
        }
    }

    /**
     * Активируем изменения, если что-то у элемента, к которому привязан объект типа boiler
     * @param $element
     * @param $elementStatus
     */
    private function setModeBoiler($element, $elementStatus) {

        // Для реализации смены режимов работы котла
        // определяем хэндл элемента, его состояние и меняем состояние у объекта.
        // Также находим связанный хэндл (если есть) для того, чтобы поменять его
        if ($element->handle == 'automode') {

            $reverseHandle = 'manualmode';

            if ($elementStatus == 'on')
            {
                $mode = 'auto';
                $reverseStatus = 'off';
            }
            else {
                $mode = 'manual';
                $reverseStatus = 'on';
            }


        } elseif ($element->handle == 'manualmode') {

            $reverseHandle = 'automode';

            if ($elementStatus == 'on')
            {
                $mode = 'manual';
                $reverseStatus = 'on';
            }
            else {
                $mode = 'auto';
                $reverseStatus = 'off';
            }

        }

        Boiler::setMode($element->id_object, $mode);

        //Меняем состояние элемента
        $this->setElementStatus($element, $elementStatus);

        //Ищем реверсный элемент и меняем у него состояние
        $reverseElement = $this->findElementByHandle($element->page, $reverseHandle);
        $this->setElementStatus($reverseElement, $reverseStatus);

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
    public function setElementStatus($element, $status) {

        //Устанавливаем статус для элемента, если хендл у него automode или manualmode (элемент котла)
        if (($element->handle == 'automode') || ($element->handle == 'manualmode')) {

            $objjson = json_decode($element->value);
            $settings = $objjson[0]->{'settings'};

            $newValue = json_encode( array("status" => $status, "settings" => $settings));

            parent::$db->exec("UPDATE elements SET `value` = '[".$newValue."]'
                                       WHERE `id` = $element->id");
        }

    }

    /**
     * Ищем элемент по его хендлу
     */
    private function findElementByHandle($page, $hanle) {

        $sql = "SELECT * FROM elements
                WHERE handle = $hanle AND page = $page";

        $queryElements = parent::$db->query($sql);

        if ($queryElements->rowCount() != 0) {
            return $queryElements->fetch(PDO::FETCH_OBJ);

        }
    }

}