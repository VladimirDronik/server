<?php
/**
 * Created by PhpStorm.
 * User: kinord
 * Date: 23.04.18
 * Time: 20:45
 */



require_once '../../include.php';


$script = new Scripts();

$script->set(3,0, 1); // Устанавливаем 3 порту значение 0
$script->play_sound(); // Проиграть звук



