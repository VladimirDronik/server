<?php


class Page extends System {

    public function setIntPages($fulldata) {

        $data_array = json_decode($fulldata);

        $type = $data_array->items[0]->type;
        $idObject = $data_array->items[0]->idObject;
        $mode = $data_array->items[0]->mode;
        $value = $data_array->items[0]->value;
        $idPage = $data_array->items[0]->idPage;


        $sql = parent::$db->query("SELECT id FROM internalPages WHERE `idObject` = $idObject");
        $page = $sql->fetch(PDO::FETCH_OBJ);

        if ($type == 'BoilerAuto') {
            if ($mode == 'add') {

                $temperatures = explode(":",$value);

                parent::$db->query("INSERT INTO boiler_values (`id`, `t_out`, `t_water`, `id_intpage`)
                                          VALUES (null,  $temperatures[0], $temperatures[1], $idPage)");

            } elseif ($mode == 'del') {

                parent::$db->query("DELETE FROM boiler_values WHERE id_intpage = $idPage AND t_out = $value )");

            }
        } else {

            parent::$db->exec("UPDATE internalPages SET `set_value` = $value
                                       WHERE `id` = $idPage");
        }

    }

}