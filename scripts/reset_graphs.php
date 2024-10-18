<?php
/**
 * Скрипт удаления старых значений графиков
 * Скрипт можно запускать по расписанию, нарпимер
 * очищать графики раз в несколько месяцев или раз в год
 */

require_once '../include.php';


Graphs::deleteOldValues('graph_counts');
Graphs::deleteOldValues('graph_termostats');
Graphs::deleteOldValues('graph_hygrostats');
Graphs::deleteOldValues('graph_lights');
Graphs::deleteOldValues('sensors_graphs');
