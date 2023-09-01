<?php

/**
 * Created by PhpStorm.
 * User: kinord
 * Date: 08.07.21
 * Time: 17:24
 */
class YandexStation extends Device
{
    private static $yandex_station = null;

    public function __construct($idObject)
    {
        $this->initYandexStation($idObject);
    }

    private function initYandexStation($idObject)
    {
        $sql = parent::$db->query("SELECT speaker_id FROM yandexstations WHERE id_object = $idObject");

        self::$yandex_station = $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Воспроизвести звук на выбранных колонках
     * @param $selectedStations - массив колонок, которым управляем
     *  Пример:
     *         [ id_колонки => громкость_колонки_в_процентах, ... ]
     */
    public function say($message)
    {
        // foreach ($selectedStations as $idStation => $volumeStation) {

        //     $sql = parent::$db->query("SELECT * FROM `yandexstations` WHERE `id`=$idStation AND `active` = 1");

        //     if ($sql->rowCount() > 0) {
        //         $stations = $sql->fetchAll(PDO::FETCH_OBJ);

        //         foreach ($stations as $station) {
        //             $yandexStation = self::init();

        //             if ($volumeStation != '?')
        //                 $volume = $volumeStation;
        //             else
        //                 $volume = $station->volume;

        //             $yandexStation->cmd('Установи громкость 0 процентов', $station->speaker_id);
        //             $yandexStation->cmd('Установи громкость ' . $volume . ' процентов', $station->speaker_id);
        //             $yandexStation->say($message, $station->speaker_id);
        //             $yandexStation->cmd('Установи громкость 0 процентов', $station->speaker_id);
        //             $yandexStation->cmd('Установи громкость ' . $station->volume . ' процентов', $station->speaker_id);
        //         }

        //     }

        // }
        exec ('php '.getenv('WORK_DIR').'/adm/artisan yandexstation:say '.self::$yandex_station->speaker_id.' "'.$message.'"');

    }


    /**
     * Выполнение команды на яндекс станции
     * @param $selectedStations - массив колонок, которым управляем
     * @param $command
     */
    public function cmd($command)
    {
        // foreach ($selectedStations as $idStation => $volumeStation) {

        //     $sql = parent::$db->query("SELECT * FROM `yandexstations` WHERE `id`=$idStation AND `active` = 1");

        //     if ($sql->rowCount() > 0) {
        //         $stations = $sql->fetchAll(PDO::FETCH_OBJ);
        //         foreach ($stations as $station) {
        //             $yandexStation = self::init();

        //             if ($volumeStation != '?')
        //                 $volume = $volumeStation;
        //             else
        //                 $volume = $station->volume;


        //             $yandexStation->cmd('Установи громкость 0 процентов', $station->speaker_id);
        //             $yandexStation->cmd('Установи громкость ' . $volume . ' процентов', $station->speaker_id);
        //             $yandexStation->cmd($command, $station->speaker_id);
        //             $yandexStation->cmd('Установи громкость 0 процентов', $station->speaker_id);
        //             $yandexStation->cmd('Установи громкость ' . $station->volume . ' процентов', $station->speaker_id);
        //         }

        //     }

        // }
        exec ('php '.getenv('WORK_DIR').'/adm/artisan yandexstation:cmd '.self::$yandex_station->speaker_id.' "'.$command.'"');
    }

}