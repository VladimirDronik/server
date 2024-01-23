<?php

require_once '../include.php';

function nbit($number, $n) 
{
    return ($number >> $n) & 1;
}

$sql = System::$db->prepare("SELECT `id` FROM `modbus_registers` WHERE `slaver_id` = $argv[1] AND `alias` = ?");

// Получаем регистр контроля изменений шины DALI
$sql->execute(["dali_bus_changes"]);
$changesAmountRegister = $sql->fetch(PDO::FETCH_OBJ)->id;

// Получаем массив регистров для поиска флагов изменений
$devicesChangesRegisterArray = array();
$sql->execute(["dali_15_0_changes"]);
$devicesChangesRegisterArray[] = $sql->fetch(PDO::FETCH_OBJ)->id;
$sql->execute(["dali_31_16_changes"]);
$devicesChangesRegisterArray[] = $sql->fetch(PDO::FETCH_OBJ)->id;
$sql->execute(["dali_47_32_changes"]);
$devicesChangesRegisterArray[] = $sql->fetch(PDO::FETCH_OBJ)->id;
$sql->execute(["dali_63_48_changes"]);
$devicesChangesRegisterArray[] = $sql->fetch(PDO::FETCH_OBJ)->id;

// Обнуляем счетчик изменений шины DALI
Modbus::putTaskIntoQueue($changesAmountRegister, 'write', 0, 0);
$initialChangesAmount = Modbus::getRegisterValue($changesAmountRegister);
echo "Начальное значение изменений: " . $initialChangesAmount . PHP_EOL;

// Запускаем непрерывный опрос регистра контроля изменений шины DALI
Modbus::pollingCtl($changesAmountRegister, true, 0);

while (true)
{
    usleep (500000);
    // Получаем текущее количество изменений на шине DALI
    $currentChangesAmount = Modbus::getRegisterValueFromDB($changesAmountRegister);
    echo "Текущее значение изменений: " . $currentChangesAmount . PHP_EOL;
    // Получаем количе
    // $newEvents = $currentChangesAmount - $initialChangesAmount;
    // echo "Новых: " . $newEvents . PHP_EOL;
    
    $foundedEvents = 0; // Счетчик найденых флагов изменений
    $mybitseq = null;
    if ($currentChangesAmount > 0)
    {
        foreach ($devicesChangesRegisterArray as $key => $registerId)
        {
            // var_dump ($key, $registerId);
            // Если все изменения обработаны пропускаем опрос остальных регистров
            if ($currentChangesAmount == $foundedEvents) continue;

            $flags = Modbus::getRegisterValue($registerId);
            
            if ($flags !=0)
            {
                echo "Адреса " . $key*16 .  " - " . $key*16+15 . ": Найдены изменения" . PHP_EOL;
                for ($i = 0; $i<=15; $i++)
                {
                    echo "A" . $i+$key*16 . ": " . nbit($flags, $i) . PHP_EOL;
                    if (nbit($flags, $i) == 1) 
                    {
                        $foundedEvents++;
                        // Получаем данные устройства
                        // Статус, Яркость, Цветовая температура
                    }
                }
            }
        }

        
        Modbus::putTaskIntoQueue($changesAmountRegister, 'write', 0, 0);
        $initialChangesAmount = 0;

    }
    



}