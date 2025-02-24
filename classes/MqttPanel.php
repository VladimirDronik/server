<?php

include_once "../include.php";

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Exceptions\MqttClientException;

class MqttPanel extends System
{
    const MQTT_HOST = 'localhost';
    const MQTT_PORT = 1883;

    static $response = null;
//Функция возвращает id панелей из БД
    public static function getMqttPanelId()
    {
        $sql = parent::$db->query(" SELECT `mqtt_panel`.`id` AS 'panel_id'
                                    FROM `mqtt_panel`");

        if($sql->rowCount() > 0)
        {
            $panel_ids = $sql->fetchAll(PDO::FETCH_OBJ);
            foreach ($panel_ids AS $panel_id) $Panel_idsArray[] = $panel_id->panel_id;
            return $Panel_idsArray;
        }
    }

    public static function subscribe(string $topic)
    {
      $client = new MqttClient(self::MQTT_HOST, self::MQTT_PORT);
      $client->connect(null, true);
      $client->subscribe($topic, function (string $topic, string $message, bool $retained) use ($client) {
        self::$response = json_decode($message, true);
        $client->interrupt();
      }, MqttClient::QOS_AT_MOST_ONCE);
      $client->loop(true);
      $client->disconnect();
      return self::$response;
    }
//Функция подписывается на топик Энистата и при изменении значения target запускает RunAction
    public static function subscribe_anystat(int $panel_id)
    {
      //Формируем топик
      $sql = System::$db->query("SELECT  `mac` FROM `mqtt_panel` WHERE `id` = $panel_id");
      $sql = $sql->fetch(PDO::FETCH_OBJ);
      $mac = $sql->mac;
      $topic = "touchon-panel-". $mac . "/anystat/state";
      try {
        $clientId = 'server-subscribe-anystat';
        $client = new MqttClient(self::MQTT_HOST, self::MQTT_PORT, $clientId);

        pcntl_signal(SIGINT, function (int $signal, $info) use ($client) {
          $client->interrupt();
        });

        $client->connect(null, true);

        $client->subscribe($topic, function (string $topic, string $message, bool $retained) use ($client) {
          $response = json_decode($message, true);

          $anystat_id = $response['object_id'];
          $state = $response['anystat_state'];
          $target_value = $response['target_value'];

          //Получаем параметры из таблицы представлений
          $params = System::$db->query("SELECT  `view_items`.`type`, `view_items`.`on_method`, `view_items`.`off_method` FROM `view_items` WHERE `view_items`.`id_object` = $anystat_id");
          $params = $params->fetch(PDO::FETCH_OBJ);

          if ($params->type != null) {
            $onMethod = $params->on_method;
            $typeObject = $params->type;

            //Если объект - термостат
            if ($typeObject == "termostat"){
              $thermostat = new Thermostats($anystat_id);
              $target_value_old = $thermostat->getProperty('optimal');

              //Если термостат на панели включен
              if ($state == "ON"){

                //Если на панели выставлено новое значение
                if (round($target_value_old) != round($target_value)){
                  print_r("\nSubscribe: \nAnystat ID: $anystat_id\nType anystat: $typeObject\nState: $state\nTarget value: $target_value\n\n");
                  System::addLog('MQTT', 'Панель. Энистат ID: ' . $anystat_id . ' изменил целевое значение на: ' . $target_value, 'scripts');
                  $thermostat->set_temperature($anystat_id, $target_value);
                  Action::runAction($onMethod, NULL, $anystat_id);
                }
              }
            }

            //Если объект - гидростат
            if ($typeObject == "hygrostat"){
              $hygrostat = new Hygrostats($anystat_id);
              $target_value_old = $hygrostat->getProperty('optimal');

              //Если гидростат на панели включен
              if ($state == "ON"){

                //Если на панели выставлено новое значение
                if (round($target_value_old) != round($target_value)){
                  print_r("\nSubscribe: \nAnystat ID: $anystat_id\nType anystat: $typeObject\nState: $state\nTarget value: $target_value\n\n");
                  System::addLog('MQTT', 'Панель. Энистат ID: ' . $anystat_id . ' изменил целевое значение на: ' . $target_value, 'scripts');
                  $hygrostat->set_humiduty($anystat_id, $target_value);
                  Action::runAction($onMethod, NULL, $anystat_id);
                }
              }
            }
          }
//          $client->interrupt();
        }, MqttClient::QOS_AT_MOST_ONCE);
        $client->loop(true);

        // Gracefully terminate the connection to the broker.
        $client->disconnect();
      } catch (MqttClientException $e) {
        System::addLog('MQTT', 'Ошибка подписки на топик:' . $topic, 'scripts');
        //  $logger->error('Subscribing to a topic using QoS 0 failed. An exception occurred.', ['exception' => $e]);
      }
    }


//Функция подписывается на топик Выключателей и при изменении состояния запускает RunAction
    public static function subscribe_switch(int $panel_id)
    {
      //Формируем топик
      $sql = System::$db->query("SELECT  `mac` FROM `mqtt_panel` WHERE `id` = $panel_id");
      $sql = $sql->fetch(PDO::FETCH_OBJ);
      $mac = $sql->mac;
      $topic = "touchon-panel-". $mac . "/switches/state";
      print("Subscribe: \nMAC: $mac\n");
      try {
        $clientId = 'server-subscribe-switches';
        $client = new MqttClient(self::MQTT_HOST, self::MQTT_PORT, $clientId);

        pcntl_signal(SIGINT, function (int $signal, $info) use ($client) {
          $client->interrupt();
        });

        $client->connect(null, true);

        $client->subscribe($topic, function (string $topic, string $message, bool $retained) use ($client) {
          $response = json_decode($message, true);
          $switch_id = $response['object_id'];
print_r("Test. Switch_id: $switch_id\n");
          $state = $response['state'];
print_r("Test. State: $state\n");
          $params = System::$db->query("SELECT  `view_items`.`on_method`, `view_items`.`off_method` FROM `view_items` WHERE `view_items`.`id_object` = $switch_id");
print_r("Test. Params querry\n");
          $params = $params->fetch(PDO::FETCH_OBJ);
print_r("Test. Params: $params->on_method\n");
          if ($params){
            if ($params->on_method) {
               if ($state == "ON"){
                 print("Subscribe: \nSwitch ID: $switch_id State: ON\n\n");
                 System::addLog('MQTT', 'Панель. Выключатель ID: ' . $switch_id . ' перешел в состояние ON', 'scripts');
                 $onMethod = $params->on_method;
                 Action::runAction($onMethod, NULL, $switch_id, NULL, $state);
               }
               if ($state == "OFF"){
                 print("Subscribe: \nSwitch ID: $switch_id State: OFF\n\n");
                 System::addLog('MQTT', 'Панель. Выключатель ID: ' . $switch_id . ' перешел в состояние OFF', 'scripts');
                 $offMethod = $params->off_method;
                 Action::runAction($offMethod, NULL, $switch_id, NULL, $state);
               }
            }
          }
//          $client->interrupt();
        }, MqttClient::QOS_AT_MOST_ONCE);
        $client->loop(true);

        // Gracefully terminate the connection to the broker.
        $client->disconnect();
      } catch (MqttClientException $e) {
          // MqttClientException is the base exception of all exceptions in the library. Catching it will catch all MQTT related exceptions.
          System::addLog('MQTT', 'Ошибка подписки на топик:' . $topic, 'scripts');
           //->error('Subscribing to a topic using QoS 0 failed. An exception occurred.', ['exception' => $e]);
        }
    }



//Функция подписывается на топик Lights и при изменении значения  состояния лампы передает параметры классу dimmer
    public static function subscribe_lights(int $panel_id)
    {
      //Формируем топик
      $sql = System::$db->query("SELECT  `mac` FROM `mqtt_panel` WHERE `id` = $panel_id");
      $sql = $sql->fetch(PDO::FETCH_OBJ);
      $mac = $sql->mac;
      $topic = "touchon-panel-". $mac . "/lights/state";
      try {
        $clientId = 'server-subscribe-lights';
        $client = new MqttClient(self::MQTT_HOST, self::MQTT_PORT, $clientId);

        pcntl_signal(SIGINT, function (int $signal, $info) use ($client) {
          $client->interrupt();
        });

        $client->connect(null, true);

        $client->subscribe($topic, function (string $topic, string $message, bool $retained) use ($client) {
          $lights_params = json_decode($message, true);

          $light_id = $lights_params['object_id'];
          $brightness = $lights_params['br_value'];
          $state = strtolower($lights_params['state']);

          $params = System::$db->query("SELECT  `lamps`.`type` FROM `lamps` WHERE `lamps`.`id_object` = $light_id");
          $params = $params->fetch(PDO::FETCH_OBJ);
          $type = $params->type;

          $newObject = new Objects();
          $newObject->select($light_id);

          $dimmer = new Dimmer($light_id);

          if ($type == "lamp"){
            $newObject->setStatus($state, true, true);
          }
          if ($type == "dimmer"){
            if ($state == 'off') $brightness = 0;
           // else $brightness = $dimmer->getValue();
            if ($brightness == 0) $state = 'off'; //Выключаем диммер
            $dimmer->setValue($brightness);
            $newObject->setStatus($state, true, false);
          }
          print_r("\nSubscribe: \nLight ID: $light_id\nType: $params->type\nBrightness: $brightness\nState: $state\n\n");
//          $client->interrupt();
        }, MqttClient::QOS_AT_MOST_ONCE);
        $client->loop(true);

        // Gracefully terminate the connection to the broker.
        $client->disconnect();
      } catch (MqttClientException $e) {
        System::addLog('MQTT', 'Ошибка подписки на топик:' . $topic, 'scripts');
        //  $logger->error('Subscribing to a topic using QoS 0 failed. An exception occurred.', ['exception' => $e]);
      }
    }

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

//Функция публикует пэйлоад в топик
    public static function publish(string $clientId, string $topic, array $payload, int $qos, bool $retain)
    {
        $client = new MqttClient(self::MQTT_HOST, self::MQTT_PORT, $clientId);
        $client->connect(null, true);
        $client->publish($topic, json_encode($payload), $qos,  $retain);
        $client->disconnect();
     }

//Функция публикует пэйлоад отправленный сервером в топик Энистата
    public static function publish_anystat(int $anystat_id)
    {
      $mac = self::get_mac($anystat_id, 'page1');

      if ($mac){
        //MQTT anystat
        $topic = "touchon-panel-". $mac . "/settings/anystat/state";

        //Получаем параметры из таблицы представлений
        $params = System::$db->query("SELECT  `type`, `on_method`, `off_method` FROM `view_items` WHERE `id_object` = $anystat_id");
        $params = $params->fetch(PDO::FETCH_OBJ);

        if ($params->type != null) {
          $onMethod = $params->on_method;
          $typeObject = $params->type;
          if ($typeObject == "termostat"){
            $thermostat = new Thermostats($anystat_id);
            $thermostat->check();
            $current = $thermostat->getProperty('current');
            $optimal = $thermostat->getProperty('optimal');
            $mode = $thermostat->getProperty('thermostat');
            $unit = "°C";
            $states = System::$db->query("SELECT  `status` FROM `objects` WHERE `id` = $anystat_id");
            $states = $states->fetch(PDO::FETCH_OBJ);
            $state = $states->status;
            if ($mode == 0) {
              if ($state == "ON"){
                $activity = 3;
              } else {
                $activity = 1;
              }
            }
            if ($mode == 1) {
              if ($state == "ON"){
                $activity = 2;
              } else {
                $activity = 1;
              }
            }
          }
          if ($typeObject == "hygrostat"){
            $hygrostat = new Hygrostats($anystat_id);
            $hygrostat->check();
            $current = $hygrostat->getProperty('current');
            $optimal = $hygrostat->getProperty('optimal');
            $mode = $hygrostat->getProperty('type');
            $unit = "%";
            $states = System::$db->query("SELECT  `status` FROM `objects` WHERE `id` = $anystat_id");
            $states = $states->fetch(PDO::FETCH_OBJ);
            $state = $states->status;
            if ($mode == 0) {
              if ($state == "ON"){
                $activity = 5;
              } else {
                $activity = 1;
              }
            }
            if ($mode == 1) {
              if ($state == "ON"){
                $activity = 4;
              } else {
                $activity = 1;
              }
            }
          }

          print("Publish:\nCurrent: $current\nTarget: $optimal\nMode anystat: $mode\nState: $state\n");
          $clientId = 'server-publish-anystat';
          $payload = [
            "id" => "anystat",
            "object_id" => $anystat_id,
            "activity"  => $activity,
            "anystat_state"  =>  "ON",
            "target_value"  => round($optimal),
            "current_value"  => round($current),
            "unit"  => $unit
          ];
          self::publish($clientId, $topic, $payload, 0, true);
        }
      }
    }

    //Функция публикует пэйлоад отправленный сервером в топик Выключателей
    public static function publish_switches($object_id, $method_params)
    {
      $mac = self::get_mac($object_id, 'page2');
      if ($mac){
        $topic = "touchon-panel-". $mac . "/settings/switches/state";
        $state = strtoupper($method_params);
        print("Publish: \nSwitch ID: $object_id State: $method_params\n\n");
        $clientId = 'server-publish-switches';
        $payload = [
          "object_id" => $object_id,
          "state"  =>  $state
        ];
        self::publish($clientId, $topic, $payload, 0, true);
      } else{
        print_r("Mac не найден или SW не из панели\n\n");
//      }
//Проверяем, является ли выключатель лампой
        $mac = self::get_mac($object_id, 'page3');
        $params = System::$db->query("SELECT  `lamps`.`type` FROM `lamps` WHERE `lamps`.`id_object` = $object_id");
        $params = $params->fetch(PDO::FETCH_OBJ);
        $type = $params->type;
        if (($mac)&&($type == "lamp")){
          $topic = "touchon-panel-". $mac . "/settings/lights/state";
          $state = strtoupper($method_params);
          print("Publish: \nLight ID: $object_id State: $method_params\n\n");
          $clientId = 'server-publish-lights';
          $payload = [
            "object_id" => $object_id,
            "state"  =>  $state
          ];
          self::publish($clientId, $topic, $payload, 0, true);
        } else{
          print_r("Mac не найден или Lights не из панели\n\n");
        }
      }

    }


//Функция публикует пэйлоад отправленный сервером в топик Lights
    public static function publish_lights($object_id, $br_value, $state)
    {
      $mac = self::get_mac($object_id, 'page3');
      if ($mac){
        $topic = "touchon-panel-". $mac . "/settings/lights/state";
        $state = strtoupper($state);
        print("Publish: \nLight ID: $object_id BR value: $br_value State: $state\n\n");
        $clientId = 'server-publish-switches';
        $payload = [
          "object_id" => $object_id,
          "br_value" => $br_value,
          "state"  =>  $state
        ];
        self::publish($clientId, $topic, $payload, 0, true);
      } else{
        print_r("Mac не найден или Light не из панели\n\n");
      }
    }


/*
  Функция для получения mac
*/
   static private function get_mac($object_id, $page)
   {
     $sql = System::$db->query("SELECT `mqtt_panel`.`mac`, `mqtt_panel`.`$page` FROM `mqtt_panel`");

     while ($pages = $sql->fetch(PDO::FETCH_OBJ)) {
       $mac = $pages->mac;
       $p_a = $pages->$page;
       $p_a = json_decode($p_a, true);
       $key = in_array($object_id, $p_a);
       if ($key){
         return $mac;
      // } else {
         //return false;
      //   print("Mac не найден\n");
       }
     }
     return false;
   }
//Получаем значения сенсоров для отправки на панель
    static private function get_value(int $id, string $src)
    {
       switch ($src) {
                case 'Thermostats' :
                  $thermostat = new Thermostats($id);
                  $value = [$thermostat->getProperty('current') => "°C"];
                  break;

                case 'Hygrostats':
                  $hygrostat = new Hygrostats($id);
                  $value = [$hygrostat->getProperty('current') => "%"];
                  break;

                case 'Pressurestat' :
                  $pressurestat = new Pressurestat($id);
                  $value = [$pressurestat->getProperty('current') => "mm"];
                  break;

                case 'CarbDioxide':
                  $carbdioxide = new CarbDioxide($id);
                  $value = [$carbdioxide->getProperty('current') => "ppm"];
                  break;

                case 'Lightstats':
                  $lightstats = new Lightstats($id);
                  $value = [$lightstats->getProperty('current') => "lux"];
                  break;
       }  return $value;

    }

/*
*    Функция собирает данные с датчиков и отправляет на панель
*/
    public static function public_sensors(int $panel_id)
    {
      $sql = System::$db->query("SELECT  `mac`, `page0` FROM `mqtt_panel` WHERE `id` = $panel_id");
      $sql = $sql->fetch(PDO::FETCH_OBJ);
      $mac = $sql->mac;
      $topic = "touchon-panel-". $sql->mac . "/sensor/state";
      $sensors = json_decode($sql->page0, true);
      $clientId = 'server-publish-sensors';
      $payload = [
        "id" => "sensors",
        "sensor1_value" => key(self::get_value($sensors["sensor1_id"], $sensors["sensor1_src"])),
        "sensor1_unit"  => self::get_value($sensors["sensor1_id"], $sensors["sensor1_src"])[key(self::get_value($sensors["sensor1_id"], $sensors["sensor1_src"]))],
        "sensor2_value" => key(self::get_value($sensors["sensor2_id"], $sensors["sensor2_src"])),
        "sensor2_unit"  => self::get_value($sensors["sensor2_id"], $sensors["sensor2_src"])[key(self::get_value($sensors["sensor2_id"], $sensors["sensor2_src"]))],
        "sensor3_value" => key(self::get_value($sensors["sensor3_id"], $sensors["sensor3_src"])),
        "sensor3_unit"  => self::get_value($sensors["sensor3_id"], $sensors["sensor3_src"])[key(self::get_value($sensors["sensor3_id"], $sensors["sensor3_src"]))],
        "sensor4_value" => key(self::get_value($sensors["sensor4_id"], $sensors["sensor4_src"])),
        "sensor4_unit"  => self::get_value($sensors["sensor4_id"], $sensors["sensor4_src"])[key(self::get_value($sensors["sensor4_id"], $sensors["sensor4_src"]))],
        "sensor5_value" => key(self::get_value($sensors["sensor5_id"], $sensors["sensor5_src"])),
        "sensor5_unit"  => self::get_value($sensors["sensor5_id"], $sensors["sensor5_src"])[key(self::get_value($sensors["sensor5_id"], $sensors["sensor5_src"]))],
        "sensor6_value" => key(self::get_value($sensors["sensor6_id"], $sensors["sensor6_src"])),
        "sensor6_unit"  => self::get_value($sensors["sensor6_id"], $sensors["sensor6_src"])[key(self::get_value($sensors["sensor6_id"], $sensors["sensor6_src"]))],
      ];
      self::publish($clientId, $topic, $payload, 0, true);
    }
}

