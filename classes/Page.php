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
        } else { //Если manual выбрано

            parent::$db->exec("UPDATE boiler_manual SET `set_value` = $value
                                       WHERE `id_object` = $idObject");

        }

    }

}