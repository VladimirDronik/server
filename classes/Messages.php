<?php

/**
 * Класс для отправки push уведомлений пользователям
 */
class Messages
{

    /**
     * Отправка сообщения всем зарегистрированным ползователям из таблицы devusers
     *
     * @param integer $priority - тип сообщения 1 - важные, 2 - все
     * @param string $title - название сообщения
     * @param string $message - тело сообщения
     */
    public static function send(int $priority, string $message)
    {
        global $localsocket;


        $sql = system::$db->query("SELECT dev_id, telegram_id, push_id, phone_number,
                                        telegram_send, push_send, sms_send
                          FROM `devusers` 
                          WHERE telegram_send = $priority OR telegram_send = $priority OR sms_send = $priority
                          OR telegram_send = 0 OR telegram_send = 0 OR sms_send = 0
                          ");

        $devusers = $sql->fetchAll(PDO::FETCH_OBJ);

        foreach ($devusers AS $device) {

            if ((($device->telegram_send == $priority) || ($device->telegram_send == 3)) && ($device->telegram_id))
                passthru("(php -f ../libs/send_telegram.php {$device->telegram_id} $message $priority & )  >> /dev/null 2>&1");

            if ((($device->push_send == $priority) || ($device->push_send == 3)) && ($device->push_id))
                passthru("(php -f ../libs/send_push.php {$device->push_id} TouchOn $message & ) >> /dev/null 2>&1");

            if((($device->sms_send == $priority) || ($device->sms_send == 3)) && ($device->phone_number));
                passthru("(php -f ../libs/send_sms.php {$device->phone_number} $message & ) >> /dev/null 2>&1");
        }


        //Отправка сообщения через сокет
        $messageToSocket = '{"status": "singleMessage", "text": "'.$message.'", "priority":'.$priority.', 
        "date":'.date("Y-m-d H:i:s").',"is_read": 0}';

        // connect to a local tcp-server
        $instance = stream_socket_client($localsocket);
        // send message
        fwrite($instance, json_encode(['user' => 'all', 'message' => $messageToSocket])  . "\n");
    }

    /**
     * Функция отправки сообщений, которые соответсвуют объекту
     * @param int $idObject - ид объекта
     */
    public static function sendByObject(int $idObject)
    {

        $sql = system::$db->query("SELECT `message_on`, `priority_on`, `message_off`, `priority_off` 
                                   FROM `notifications` WHERE `id_object`=$idObject");

        if($sql->rowCount() > 0) {

            $message = $sql->fetch(PDO::FETCH_OBJ);

            if ($message->message_on) self::send($message->priority_on, $message->message_on);
            if ($message->message_off) self::send($message->priority_off, $message->message_off);
        }

    }


    /**
     * Функция отдет последние 30 сообщений из таблицы
     * @param  int $startPos - стартовая строка выборки данных из таблицы
     * @return string
     */
    public function getMessages($startPos)
    {
        $countRow = 30;

        $sql = system::$db->query("SELECT id, text, priority, date, is_read FROM `messages`  ORDER BY date DESC LIMIT $startPos,$countRow");

        if($sql->rowCount()) {

            while ($message = $sql->fetch(PDO::FETCH_OBJ)) {

                $MessageLog[] = array('id' => $message->id, 'text' => $message->text,
                    'priority' => $message->priority, 'date' => $message->date, 'is_read' => $message->is_read);
            }

            return $json = json_encode(array('status'=>'messagesLoad', 'messages'=>$MessageLog));
        } else
            return $json = json_encode(array('status'=>'messagesLoad', 'messages' => ''));
    }

    public function getCountMessages()
    {
        $sql = system::$db->query("SELECT is_read, COUNT(is_read) AS cnt FROM `messages` GROUP BY is_read ORDER BY is_read LIMIT 30");

        if($sql->rowCount()) {

            while ($messageCount = $sql->fetch(PDO::FETCH_OBJ)) {

                if($messageCount->is_read == 0)
                    $unreadMessages = $messageCount->cnt;
                else
                    $readMessages = $messageCount->cnt;
            }

            return $json = json_encode(array('status'=>'countMessages',
                'counts' => ['unread' => $unreadMessages, 'read' => $readMessages, 'total' => $unreadMessages+$readMessages]));
        }

    }

    /**
     * Удаление всех сообщений
     *
     * @return string
     */
    public function deleteMessages()
    {
        system::$db->query("TRUNCATE messages");
        return $json = json_encode(array('status'=>'messagesDelete', 'status' => 'success'));
    }

    /**
     * Помечает выбранное сообщение прочитанным
     * @param $idMessage
     * @return string
     */
    public function messageRead($idMessage)
    {
        system::$db->query("UPDATE `messages` SET `is_read` = '1' WHERE `messages`.`id` = $idMessage");
        return $json = json_encode(array('status'=>'messagesIsRead', 'status' => 'success'));
    }

}