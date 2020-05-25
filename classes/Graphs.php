<?php

/**
 *  Класс работы с графиками
 **/

class Graphs extends System
{

    /**
     * Добавление значения в график счетчиков
     *
     * @param $idCount
     * @param $currentValue
     */
    static function insertToCounts($idCount, $currentValue)
    {
    //Заносим значение счетчика в таблицу с графиками
    parent::$db->query("INSERT INTO graph_counts (`datetime`, `id_count`, `value`)
                                VALUES (CURDATE(), $idCount, $currentValue)
                                ON DUPLICATE KEY UPDATE `id_count` = $idCount, `value` = $currentValue
                                ");
    }

    /**
     * Добавление значения в график термостатов
     *
     * @param $idTermostat
     * @param $termostatValue
     */
    static function insertToTermostats($idTermostat, $termostatValue)
    {
        parent::$db->query("INSERT INTO graph_termostats (`id`, `id_termostat`, `datetime`, `value`)
                            VALUES (null, '$idTermostat',
                            CONCAT(CURRENT_DATE,' ',CURRENT_TIME),'$termostatValue')");
    }

    /**
     * Добавление значения в график светостатов
     *
     * @param $idLightstat
     * @param $lightstatValue
     */
    static function insertToLightstats($idLightstat, $lightstatValue)
    {
        parent::$db->query("INSERT INTO graph_lights (`id`, `id_count`, `datetime`, `value`)
                            VALUES (null, '$idLightstat',
                            CONCAT(CURRENT_DATE,' ',CURRENT_TIME),'$lightstatValue')");
    }


    /**
     * Удаление старых данных из таблицы графиков
     *
     * @param $table
     */
    static function deleteOldValues($table)
    {
        $days = parent::readSetting('graphdate');
        parent::$db->query("DELETE FROM $table WHERE `datetime` <= (now() - INTERVAL $days DAY)");
    }
}