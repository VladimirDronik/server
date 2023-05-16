<?php

/**
 * Class Conditioner позволяет работать с кондиционерами, подключенными через modbus по ИК 
 **/

class Conditioner extends Device
{

    private static $idObject; // id объекта кондиционера 


     function __construct($idObject)
    {
        self::$idObject = $idObject;
    }

     public function setValue($value, $state, $oper, $fan)
    {
        //Ищем в таблице нужный код для команды
        $sql = parent::$db->query('SELECT `code` FROM `` WHERE temp ='.$value.' AND  ';
        $conditioner = $sql->fetch(PDO::FETCH_OBJ);
        return $conditioner->code;

        //Отправляем команду скрипту кондиционера

        //Заносим текущее состояние кондиционера в таблицу
            parent::$db->query("UPDATE conditioner SET 
                                `temp` = $value, `state` = $state, `oper`= $oper, `fan` = $fan   
                                WHERE id_object =".self::$idObject);
        
       //Меняем состояние объекта
        if ($value>0)
            $object->setStatus('ON');
            else
                $object->setStatus('OFF');
    }
    
}

