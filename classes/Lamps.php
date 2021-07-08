<?php

/**
 * Класс работы с устройствами типа "лампа"
 */
class Lamps extends Device
{
    private static $idObject; // id объекта
    private static $methods;

    function __construct($idObject)
    {
        self::$idObject = $idObject;

        //Выбираем все системные методы
        $sql = parent::$db->query("SELECT id, `name` FROM methods WHERE id_object = $idObject AND is_system=1");
        if($sql->rowCount() > 0) {
            $methods = $sql->fetchAll(PDO::FETCH_OBJ);

            foreach ($methods AS $method) {

                $methodsArray[$method->name] = $method->id;
            }
        }

        self::$methods = $methodsArray;
    }


    /**
     * Функция включения лампы
     */
    public function on()
    {
        Action::runAction(self::$methods['Включить лампу'], 'script', self::$idObject);
    }

    /**
     * Функция выключения лампы
     */
    public function off()
    {
        Action::runAction(self::$methods['Выключить лампу'], 'script', self::$idObject);
    }

    /**
     * Функция переключния лампы
     */
    public function sw()
    {
        Action::runAction(self::$methods['Смена состояния лампы'], 'script', self::$idObject);
    }




}