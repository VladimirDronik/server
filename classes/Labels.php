<?php

/**
 * Класс работы с устройствами типа "label"
 */
class Labels extends System
{
    public static function setValue($value, $typeValue, $idObject) {

         //Выбираем отображение, которому нужно отправить значение
         $sql = parent::$db->query("SELECT id_view AS id FROM labels WHERE type_value = '$typeValue' AND id_object = $idObject");
         if($sql->rowCount() > 0) {
            $viewItems = $sql->fetchAll(PDO::FETCH_OBJ);

            foreach ($viewItems AS $item) {
                //Записываем значение отображения
               $query = system::$db->prepare("UPDATE view_items SET value = '$value' WHERE id = $item->id");
               $query->execute();   
            }
        }

        $view = new Views();
        $view->updateItem($item->id);
    }

}