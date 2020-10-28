<?php

/**
 * Класс для отправки push уведомлений пользователям
 */
class Messages
{

    /**
     * Отправка сообщения всем зарегистрированным ползователям из таблицы devusers
     *
     * @param integer $priority - тип сообщения 1 - важные, 2 - обычные
     * @param string $title - название сообщения
     * @param string $message - тело сообщения
     */
    public static function send(int $priority, string $message)
    {

        global $localsocket;

        $sql = system::$db->query("SELECT dev_id, telegram_id, push_id, phone_number,
                                        telegram_send, push_send, sms_send
                          FROM `devusers` 
                          WHERE telegram_send = $priority OR push_send = $priority OR sms_send = $priority
                          OR telegram_send = 3 OR push_send = 3 OR sms_send = 3
                          ");

        $devusers = $sql->fetchAll(PDO::FETCH_OBJ);

        foreach ($devusers AS $device) {

            if ((($device->telegram_send == $priority) || ($device->telegram_send == 3)) && ($device->telegram_id))
                passthru("(cd ".ROOT_DIR." && php -f libs/send_telegram.php {$device->telegram_id} '$message' $priority & )  >> /dev/null 2>&1");

            if ((($device->push_send == $priority) || ($device->push_send == 3)) && ($device->push_id))
                passthru("(cd ".ROOT_DIR." && php -f libs/send_push.php {$device->push_id} TouchOn '$message' & ) >> /dev/null 2>&1");

            if((($device->sms_send == $priority) || ($device->sms_send == 3)) && ($device->phone_number));
                passthru("(cd ".ROOT_DIR." && php -f libs/send_sms.php {$device->phone_number} '$message' & ) >> /dev/null 2>&1");
        }


        //Добавление сообщения в БД
        $query = system::$db->prepare("INSERT INTO `messages` (`id`, `text`, `priority`, `date`, `is_read`) 
                            VALUES (null, '$message', $priority,'".date("Y-m-d H:i:s")."', 0)");
        $query->execute();
        $idMessage = system::$db->lastInsertId();

        //Отправка сообщения через сокет
        $messageToSocket = '{"status": "singleMessage", "id":"'.$idMessage.'", "text": "'.$message.'", "priority":"'.$priority.'"", 
        "date":"'.date("Y-m-d H:i:s").'","is_read": "0"}';

        // connect to a local tcp-server
        $instance = stream_socket_client($localsocket);
        // send message
        fwrite($instance, json_encode(['user' => 'all', 'message' => $messageToSocket])  . "\n");
    }

    /**
     * Функция отправки сообщений, которые соответсвуют объекту
     * @param int $idObject - объект
     * @param bool $sendMessage - отправлять сообщение или нет
     * @param string $options - через запятую перечисляются номера сообщений, который отправлять, например '1,2'
     */
    public static function sendByObject(int $idObject, $sendMessage = true, $options = null)
    {

        $object = new Objects();
        $object->select($idObject);

        if($sendMessage) {
            $sql = system::$db->query("SELECT `message_1`, `priority_1`, `message_2`, `priority_2` 
                                       FROM `notifications` WHERE `id_object`=$object->id");

            if($sql->rowCount() > 0) {

                $message = $sql->fetch(PDO::FETCH_OBJ);

                $message_1 = $message->message_1;
                $priority_1 = $message->priority_1;
                $message_2 = $message->message_2;
                $priority_2 = $message->priority_2;


                //определяем тип объекта
                if ($object->type == 'motionsensor') { //если датчик движения

                    //Если есть оповещение 1, то отправляем его в любом случае
                    if ($message_1) self::send($priority_1, $message_1);

                    //Если есть оповещение 2 и включен режим охраны, то отправляем оповещение
                    if (($message_2) && (System::readSetting('guard_mode') == 'true')) self::send($priority_2, $message_2);

                } elseif ($options) { //Если указаны опции, какие сообщения выводить

                    $numbersMessages = explode(',',$options);

                    foreach ($numbersMessages AS $numberMessage) {

                        $message = 'message_'.$numberMessage;
                        $priority = 'priority_'.$numberMessage;

                        if ($$message) self::send($$priority, $$message);
                    }

                }
                else
                { // объектом является что-то, что имеет сообщения на on/off

                    if (($message_1) && (mb_strtoupper($object->status) == 'ON')) self::send($priority_1, $message_1);
                    if (($message_2) && (mb_strtoupper($object->status) == 'OFF')) self::send($priority_2, $message_2);
                }


            }
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