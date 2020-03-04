<?php
/**
 * Скрипт запускает любую передаваемую как параметр  функцию из стостава ядра как отдельный поток
 */

require_once 'include.php';

$class = $argv[1];
$function = $argv[2];
$data = $argv[3];


if ($class == 'views') {
    $views = new Views();
    $views->$function($data);
}
