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

     public function setValue($temperature, $state, $oper, $fan)
    {

        $object = new Objects();
        $object->select(self::$idObject);


        if ($state == 'ON' ) {
            $object->setStatus('ON', true, false);
            $query = 'SELECT `code`, conditioners.wb_mir AS modbus_addr, devices.ip_address AS ip_addr FROM `conditioner_codes` INNER JOIN `conditioner_models` ON conditioner_codes.kind = conditioner_models.kind INNER JOIN `conditioners` ON conditioners.model = conditioner_models.id  
                INNER JOIN devices ON devices.id = conditioners.device_id
                WHERE conditioner_codes.temperature = '.$temperature.' AND conditioner_codes.operationMode = "'.$oper.'" AND conditioner_codes.fanMode = "'.$fan.'" AND conditioners.id_object = '.self::$idObject;
         } else {
            $object->setStatus('OFF', true, false);
            $query = 'SELECT `code`, conditioners.wb_mir AS modbus_addr, devices.ip_address AS ip_addr FROM `conditioner_codes` INNER JOIN `conditioner_models` ON conditioner_codes.kind = conditioner_models.kind INNER JOIN `conditioners` ON conditioners.model = conditioner_models.id  
                INNER JOIN devices ON devices.id = conditioners.device_id
                WHERE conditioner_codes.status = "'.$state.'" AND conditioners.id_object = '.self::$idObject;

         }

        //Ищем в таблице нужный код для команды
        $sql = parent::$db->query($query);


        $conditioner = $sql->fetch(PDO::FETCH_OBJ);

        //Заносим текущее состояние кондиционера в таблицу
            parent::$db->query("UPDATE conditioners SET 
                                `temp` = $temperature, `state` = \"$state\", `operation` = \"$oper\", `fan` = \"$fan\"   
                                WHERE id_object =".self::$idObject);


        //Отправляем команду скрипту кондиционера        
        exec('cd /usr/bin && ./rs_control ir_raw -d wb-mir --ip '.$conditioner->ip_addr.' -u '.$conditioner->modbus_addr.' --ir_signal "'.$conditioner->code.'"');

    }
    
}

