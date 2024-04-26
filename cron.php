<?php
/**
 * Основной скрипт программы, вызывается по расписанию из крона
 * в crontab прописать скрипт в таком виде "cron.php 5" - будет извлекаться, к примеру, запись из БД, которая должна
 * выполняться каждые 5 минут. По аналогии можно задать любой временной интервал периодичности.
 */


require_once 'include.php';

$script = new Scripts();
$script->cron($argv[1]); //Ищем в БД скрипт, который подходит по периоду к вызвываемому и запускаем.

$modbusRtuBuses = Modbus::getModbusRtuBuses();
foreach ($modbusRtuBuses AS $bus_id) 
{
    $output=null;
    exec("ps aux | grep '[m]odbus_queue.php $bus_id'", $output);
    if ($output)
    {
        $modbusRegisters = Modbus::getRegistersToPoll ($argv[1], $bus_id);
        foreach ((array)$modbusRegisters AS $registerId) 
        {
            if ($registerId) 
            {
                $queryString = "SELECT `modbus_slavers`.`active`
                                FROM `modbus_slavers`
                                JOIN `modbus_registers` ON `modbus_slavers`.`id` = `modbus_registers`.`slaver_id`
                                WHERE `modbus_registers`.`id`= $registerId";
                $sql = System::$db->query($queryString);
                $isSlaverActive = $sql->fetch(PDO::FETCH_OBJ)->active;
                if ($isSlaverActive) Modbus::putTaskIntoQueue($registerId, 'read', 99);
            }
        }
    }
}
