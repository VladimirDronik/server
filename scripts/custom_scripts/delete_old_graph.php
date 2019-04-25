<?php
/**
 * Удаляет старые данные в таблице графиков. Данный скрипт вызывается по расписанию как системный
 */

    require_once '../../include.php';

    Thermostats::delete_old_values();