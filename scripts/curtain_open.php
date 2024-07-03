<?php
/**
 * Скрипт открытия шторы
 */
require_once '../include.php';
$id = (isset($argv[1]) ? $argv[1] : null);
$curtain = new Curtain($id);
if (isset($curtain))
{
    if ($curtain->open()) exit(0);
    else exit(1);
}
else exit(1);