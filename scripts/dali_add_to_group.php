<?php

require_once '../include.php';

$id = $argv[1];
$group = $argv[2];

if (isset($argv[1]))
{
    $dali = new Dali($id);
    if (isset($argv[2])) 
        if ($dali->addToGroup($argv[2])) {
            echo "Устройство добавлено в группу {$argv[2]}" . PHP_EOL;
            exit (0);
        }
        else {
            echo "Ошибка добавления в группу" . PHP_EOL;
        }
}

exit(1);