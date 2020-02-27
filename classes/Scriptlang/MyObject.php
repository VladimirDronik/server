<?php

/**
 * Класс для работы с реле в пользовательских скриптах
 */
class MyObject extends Objects
{

    /**
    * Получение состояния объекта по его ИД
    *
    * @param int $idObject id объекта, который привязан к реле
    * @return string ON|OFF state
    */
   public static function getState($idObject)
   {
       $object = new Objects();
       $object->select($idObject);
       return $object->status;
   }

    /**
     * Установка состояния для объекта + состояние для его отображения + управление физ.портом
     *
     * @param int $idObject ИД объекта реле
     * @param string $state состояние ON|OFF
     */
   public static function setState($idObject, $state)
   {
       $object = new Objects();
       $object->select($idObject);
       $object->setStatus($state);
   }


}