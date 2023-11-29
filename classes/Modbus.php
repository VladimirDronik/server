<?php


class Modbus extends System {
    
    /**
     * Создание модбас устройства, на основе данных из БД по id
     */
    public static function getModbusDevice($idModbusDevice) {

        $sql = parent::$db->query("SELECT `modbus_slavers`.`id` AS id, `modbus_slavers`.`name` AS name, `modbus_slavers_types`.`type` AS type, 
        `modbus_slavers`.`address` AS address, `modbus_buses`.`name` AS busname, `modbus_buses`.`type` AS bustype,
        `modbus_buses`.`baudrate` AS budrate, `modbus_buses`.`parity` AS parity, `modbus_buses`.`stopbits` AS stopbits,
        `modbus_buses`.`ip` AS ip, `modbus_buses`.`port` AS port 
          FROM `modbus_slavers`  
          INNER JOIN `modbus_slavers_types` ON modbus_slavers.type = modbus_slavers_types.id 
          INNER JOIN `modbus_buses` ON modbus_slavers.bus = modbus_buses.id WHERE `modbus_slavers`.`id`= $idModbusDevice");
        
        $device = $sql->fetch(PDO::FETCH_OBJ);
        return $device;
    }

    /**
     * Получение всех модбас устройств, которые есть на шине по её номеру
     * Возвращает массив модбас устройств, в котором содержится адрес устройства на шине
     */
    public static function getAllDevicesOnBus($busNumber) {
      
         $sql = parent::$db->query("SELECT id, address FROM `modbus_slavers` WHERE `bus`= busNumber");
         if($sql->rowCount() > 0) {
            $devices = $sql->fetchAll(PDO::FETCH_OBJ);

            foreach ($devices AS $device) {

                $modbusArray[$devices->id] = $devices->address;
            }

            return $modbusArray;
        }
    }


    /**
     * Опрос всех RO регистров, которые есть у устройства, для занесения данных в БД
     */
    public static function checkRegistersOnDevice($idDevice) {
       //TODO:: здесь нужно реализовать опрос шины выбранного устройства и реализовать запись в БД, например с помощью метода setValue ниже
    }

    /**
     * Запуск команды на устройстве modbus по его id
     */
    public static function runCommand($idRegister, $value) {
        //Извлекаем данные из таблицы регистров
        $sql = parent::$db->query("SELECT * FROM `modbus_registers` WHERE `id`=$idRegister");
        $register = $sql->fetch(PDO::FETCH_OBJ);

            //Смотрим какой тип регистра, если на запись, то создаем задание в очереди с высоким приоритетом, если на чтение, 
            //то читаем из таблицы и возвращаем результат
            if ($register->access == 'ro') {
                return $register->last_value;
            } elseif ($register->access == 'rw') { //Запись данных в шину модбас
    
                $modbusDevice = self::getModbusDevice($register->slaver_id); //Здесь получаем устройство модбаса, с которым хотим работать со всеми его параметрами
                //TODO:: здесь нужно сделать запись в очередь с высоким приоритетом
            }
        }

    /**
     * Запись значения регистра в БД с регистрами при опросе шины
     */
    public static function setValue($id, $value) {
        $sql = parent::$db->exec("UPDATE `modbus_registers` SET last_value = $value WHERE `id`=$id");
    }



}