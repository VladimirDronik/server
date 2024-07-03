<?php
/**
 * Скрипт открытия шторы на процент
 */
require_once '../include.php';
$id = (isset($argv[1]) ? $argv[1] : null);
$percent = (isset($argv[2]) ? $argv[2] : null);
$curtain = new Curtain($id);
if (isset($curtain))
{
    if ($curtain->setPercent($percent)) exit(0);
    else exit(1);
}
else exit(1);