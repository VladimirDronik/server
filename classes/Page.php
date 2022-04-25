<?php


class Page extends System {

    public function setIntPages($fulldata) {

        $data_array = json_decode($fulldata);

        $type = $data_array->items[0]->type;
        $mode = $data_array->items[0]->mode;
        $value = $data_array->items[0]->value;
        $idPage = $data_array->items[0]->idPage;


        if ($type == 'BoilerAuto') {
            if ($mode == 'add') {

                $temperatures = explode(":",$value);

                parent::$db->query("INSERT INTO boiler_auto (`id`, `t_out`, `t_water`, `id_intpage`)
                                          VALUES (null,  $temperatures[0], $temperatures[1], $idPage)");


            } elseif ($mode == 'del') {

                parent::$db->query("DELETE FROM boiler_auto WHERE id_intpage = $idPage AND t_out = $value");


            }
        } else { //Если manual выбрано

            parent::$db->exec("UPDATE boiler_manual SET `set_value` = $value
                                       WHERE `id` = $idPage");
            
        }

    }

}