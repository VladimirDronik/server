<?php

/**
 * Класс для отправки push уведомлений пользователям
 */
class SendPush
{
    /**
     * Отправка сообщения всем зарегистрированным ползователям из таблицы devusers
     *
     * @param string $title - название сообщения
     * @param string $message - тело сообщения
     */
    public static function sendMessage(string $title, string $message)
    {

        $sql = system::$db->query("SELECT dev_id  AS id
                          FROM `devusers` 
                          WHERE send_message = 1");

        $devusers = $sql->fetchAll(PDO::FETCH_OBJ);

        foreach ($devusers AS $device) {

            passthru("(php -f send_push.php {$device->id} $title $message & ) >> /dev/null 2>&1");
            
        }

    }
}