<?php

/**
 * Перед запуском добавить тип кондиционера:
 * name - производитель, модель или другой идентификатор (одно слово, без пробелов)
 * device - всегда 'wb-mir' (без '')
 * temperature - '{"min":16,"max":31}' (без '', в соответствии с диапазоном температур кондиционера)
 * mode - {"cool":null,"heat":null}
 * fan - '{"auto":null,"1":null,"2":null,"3":null}' (без '', в соответствии со скоростями вентилятора кондиционера)
 * hdir - NULL
 * vdir - NULL
 * 
 * Запуск:
 * Аргументами передать id типа кондиционера (arg1) и id модбас устройства wb-mir (arg2)
 * php ac_ir_codes.php <arg1> <arg2>
 * 
 * После добавления кодов необходимо добавить записи в objects и conditioners.
 */


require_once '../include.php';

if (!isset($argv[1]) || !isset($argv[2])) {
    echo PHP_EOL;
    echo "[ERR] Отсутствуют необходимые аргументы" . PHP_EOL;
    echo "      php ac_ir_codes.php <arg1> <arg2>" . PHP_EOL;
    echo "      arg1 - id типа кондиционера (табл. conditioners_types)" . PHP_EOL;
    echo "      arg2 - id модбас устройства wb-mir (табл. modbus_slavers)" . PHP_EOL;
    echo PHP_EOL;
    exit;
}

$ac_type_id = $argv[1];
$wb_mir_id = $argv[2];
$timeout = 5;

define("BLOCK_1_REG", Modbus::getRegisterIdByAlias($wb_mir_id, 'wb_mir_block_1'));
define("BLOCK_2_REG", Modbus::getRegisterIdByAlias($wb_mir_id, 'wb_mir_block_2'));
define("BLOCK_3_REG", Modbus::getRegisterIdByAlias($wb_mir_id, 'wb_mir_block_3'));
define("BLOCK_4_REG", Modbus::getRegisterIdByAlias($wb_mir_id, 'wb_mir_block_4'));
define("BLOCK_5_REG", Modbus::getRegisterIdByAlias($wb_mir_id, 'wb_mir_block_5'));
define("GET_CODE_REG", Modbus::getRegisterIdByAlias($wb_mir_id, 'wb_mir_get_code'));


function get_combinations($arrays) {
	$result = array(array());
	foreach ($arrays as $property => $property_values) {
		$tmp = array();
		foreach ($result as $result_item) {
			foreach ($property_values as $property_value) {
				$tmp[] = array_merge($result_item, array($property => $property_value));
			}
		}
		$result = $tmp;
	}
	return $result;
}

function clear_ram() {
    $r1 = Modbus::sendModbus(BLOCK_1_REG, 'write', array_fill(0, 102, 0));
    $r2 = Modbus::sendModbus(BLOCK_2_REG, 'write', array_fill(0, 102, 0));
    $r3 = Modbus::sendModbus(BLOCK_3_REG, 'write', array_fill(0, 102, 0));
    $r4 = Modbus::sendModbus(BLOCK_4_REG, 'write', array_fill(0, 102, 0));
    $r5 = Modbus::sendModbus(BLOCK_5_REG, 'write', array_fill(0, 102, 0));
    if (isset($r1) && isset($r2) && isset($r3) && isset($r4) && isset($r5))
        return true;
    else
        return false;
}

function read_ram() {
    $bank_1 = Modbus::sendModbus(BLOCK_1_REG, 'read');
    $bank_2 = Modbus::sendModbus(BLOCK_2_REG, 'read');
    $bank_3 = Modbus::sendModbus(BLOCK_3_REG, 'read');
    $bank_4 = Modbus::sendModbus(BLOCK_4_REG, 'read');
    $bank_5 = Modbus::sendModbus(BLOCK_5_REG, 'read');
    return array_merge($bank_1, $bank_2, $bank_3, $bank_4, $bank_5);
}

function anykey() { 
    echo PHP_EOL . 'Считайте код и нажмите любую клавишу для продолжения...'; 
    fgetc(STDIN);
}

$sql = System::$db->query("SHOW TABLES LIKE 'conditioner_codes'");
if(!$sql->fetch(PDO::FETCH_OBJ)) {
    System::$db->query(
        "CREATE TABLE conditioner_codes (
        `id` int NOT NULL AUTO_INCREMENT,
        `ac_type` int,
        `status` varchar(255),
        `temp` int,
        `mode` varchar(255),
        `fan` varchar(255),
        `vdir` varchar(255),
        `hdir` varchar(255),
        `code` text,
        PRIMARY KEY (id)
        )"
    );
}

$sql = System::$db->query(
    "SELECT * FROM `conditioner_types` WHERE `id`= $ac_type_id AND `device` = 'wb-mir'"
);
if($sql->rowCount() > 0) $ac = $sql->fetch(PDO::FETCH_OBJ);
else {
    echo "[ERR] Нет типа, удовлетворяющего условиям" . PHP_EOL;
    exit;
}

$temp = json_decode($ac->temperature);
$temp_arr = range($temp->min, $temp->max);
$mode_arr = array_keys(json_decode($ac->mode, true));
$fan_arr = array_keys(json_decode($ac->fan, true));

$combinations = get_combinations(
	array(
        'mode' => $mode_arr,
        'fan' => $fan_arr,
		'temp' => $temp_arr
	)
);

foreach($combinations as $row) {
    $t = $row['temp'];
    $f = $row['fan'];
    $m = $row['mode'];
    $sql = System::$db->query(
        "SELECT * FROM `conditioner_codes` WHERE `ac_type` = {$ac->id} AND `status` = 'on'
        AND `temp` = $t AND `mode` = '$m' AND `fan` = '$f'"
    );
    if($sql->rowCount() == 0) {
        System::$db->query(
            "INSERT INTO `conditioner_codes` (`ac_type`, `status`, `temp`, `mode`, `fan`)
            VALUES ({$ac->id}, 'on', $t, '$m', '$f')"
        );
    }
}

$sql = System::$db->query(
    "SELECT * FROM `conditioner_codes` WHERE `ac_type` = {$ac->id} AND `status` = 'off'"
);
if($sql->rowCount() == 0) {
    System::$db->query(
        "INSERT INTO `conditioner_codes` (`ac_type`, `status`) VALUES ({$ac->id}, 'off')"
    );
}

Modbus::sendModbus(GET_CODE_REG, 'write', 0);

$sql = System::$db->query(
    "SELECT * FROM `conditioner_codes` WHERE `ac_type` = {$ac->id} AND `code` IS NULL"
);	
while ($cmd = $sql->fetch(PDO::FETCH_OBJ)) {

    if (!clear_ram()) {
        echo "[ERR] Ошибка очистки RAM. Работа скрипта завершена" . PHP_EOL;
        exit;
    }

    echo "Код команды ";
    if ($cmd->status == 'off') echo "OFF";
    else echo "ON / FAN=$cmd->fan / MODE=$cmd->mode / TEMP=$cmd->temp ";
    Modbus::sendModbus(GET_CODE_REG, 'write', 1);

    anykey();
    $code = read_ram();

    if(!empty(array_filter($code))) {
        echo "[OK] Код считан" . PHP_EOL;
        Modbus::sendModbus(GET_CODE_REG, 'write', 0);
        $code = implode(', ', $code);
        System::$db->exec(
            "UPDATE `conditioner_codes` SET `code` = '$code' WHERE `id` = {$cmd->id}"
        );
        echo $code . PHP_EOL;
    }
    else {
        echo "[ERR] Ошибка чтения кода. Перезапустите скрипт" . PHP_EOL;
        Modbus::sendModbus(GET_CODE_REG, 'write', 0);
        exit;
    }
    echo PHP_EOL;
}

echo "[OK] Все коды добавлены" . PHP_EOL;
echo PHP_EOL;