<?php

/**
 * Класс для отправки push уведомлений пользователям
 */
class Message
{
    /**
     * Отправка сообщения всем зарегистрированным ползователям из таблицы devusers
     *
     * @param string $type - тип сообщения 1 - важные, 2 - все
     * @param string $title - название сообщения
     * @param string $message - тело сообщения
     */
    public static function send(string $type, string $message)
    {

        $sql = system::$db->query("SELECT dev_id, telegram_id, push_id, phone_number,
                                        telegram_send, push_send, sms_send
                          FROM `devusers` 
                          WHERE telegram_send = $type OR push_send = $type OR sms_send = $type");

        $devusers = $sql->fetchAll(PDO::FETCH_OBJ);

        foreach ($devusers AS $device) {

            if (($device->telegram_send) && ($device->telegram_id))
                passthru("(php -f ../libs/send_telegram.php {$device->telegram_id} $message $type & )  >> /dev/null 2>&1");

            if (($device->push_send) && ($device->push_id))
                passthru("(php -f ../libs/send_push.php {$device->push_id} TouchOn $message & ) >> /dev/null 2>&1");

            if(($device->sms_send) && ($device->phone_number));
                passthru("(php -f ../libs/send_sms.php {$device->phone_number} $message & ) >> /dev/null 2>&1");


        }

    }
}