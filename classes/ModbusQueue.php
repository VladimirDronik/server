<?php

use Beanstalk\Client;
// use PhpMqtt\Client\MqttClient;


class ModbusQueue extends System {

    static private $bus;
    static private $idBus;
    static private $beanstalk;
    static private $modbus;
    
    /**
     * Получаем настройки для шины
     */
    public function __construct($idBus)
    {
        self::$bus = Modbus::getModbusBusSettings($idBus);
        self::$idBus = $idBus;
    }

    /**
     * Создаем клиент для очереди и вызываем функцию подключения к шине ModBus
     */
    public function runClient()
    {
        self::$beanstalk = $beanstalk = new Client();
        $beanstalk->connect();

        // Проверяем не создан ли уже tube
        // $tubesArary = $beanstalk->listTubes();

        // $tubeExists = in_array(self::$bus->busname, $tubesArary);

        // Если не создан создаем и работаем с ним
        // if (!$tubeExists) {
            try {
                $beanstalk->watch(self::$idBus);
                self::busConnection();
            }
            catch (Throwable $ex) {
                echo "Произошла ошибка Beanstalk:" . PHP_EOL;
                echo $ex . PHP_EOL;
            }
            finally {
                // When exiting i.e. on critical error conditions
                // you may also want to disconnect the consumer.
                $beanstalk->disconnect();
            }
        // }
    }

    /**
     * Подключаемся к шине Modbus
     */
    private static function busConnection()
    {
        try {
            self::$modbus = $modbus = new ModbusRtu ();
            $modbus->deviceInit(self::$bus->busdevice, self::$bus->baudrate, self::$bus->parity,
                                self::$bus->length, self::$bus->stopbits, 'none');
            $modbus->deviceOpen();
            $modbus->debug = true;
            
            self::jobProcess();
        }    
        catch (Throwable $ex) {
            echo "Произошла ошибка Modbus:" . PHP_EOL;
            echo $ex . PHP_EOL;
        }
        finally {
            $modbus->deviceClose();
            self::$beanstalk->disconnect();
        }
    }
    
    /**
     * Обрабатываем поступающие в очередь задачи
     */
    private static function jobProcess()
    {
        function arrayFormat($item)
        {
            $result = dechex($item);
            if (strlen($result) < 2) $result = '0' . $result;
            return $result;
        }

        $beanstalk = self::$beanstalk;
        $writeFunctionCodesArray = [5, 6, 15, 16];
        while (true)
        {
            if (!$beanstalk->statsTube(self::$idBus))
            {
                self::$beanstalk->disconnect();
                exit (1);
            }

            $job = $beanstalk->reserve(); // Block until job is available.
            $task = json_decode($job['body']);

            
            if ($task->raw)
            {
                $binRequest = base64_decode($task->raw_data);
                $request = array_map('arrayFormat', unpack('C*', $binRequest));
                $request = implode(" ", $request);

                if ($binaryData = self::$modbus->send($binRequest))
                {
                    $response = unpack('C*', $binaryData);
                    $response  = array_map('arrayFormat', unpack('C*', $binaryData));
                    $response  = implode(" ", $response);
                }
                else $response = null;


            }

            // if ($task->mode == 'modbus_rtu')
            // {
            //     $packet = modbusFunction($task);
                
            //     $binaryData = self::$modbus->send($packet);
    
            //     if ($binaryData) 
            //     {
            //         $activity = 1;
            //         $response = modbusFunction($task, true, $binaryData);
            //     }
            //     else
            //     {
            //         $activity = 0;
            //         $response = null;
            //     }
                
            //     Modbus::setValue($task->register_id, $response);
            //     Modbus::setSlaverActivity($task->slaver_id, $activity);
            // }

            // if ($task->mode == 'rs485_curtains')
            // {
            //     if ($binaryData = self::$modbus->send(base64_decode($task->raw_data)))
            //     {
            //         $activity = 1;
            //         $bytesArray = unpack('C*', $binaryData);
                    
                    
            //         if ($task->command == 'setPercent' || $task->command == 'getPercent')
            //         {   
            //             $percent = $bytesArray[6];
            //             $curtain = new Curtain ($task->object_id);
            //             $curtain->putPercentToDb($percent);
            //             if ($task->command == 'setPercent')
            //             {
            //                 $object = new Objects();
            //                 $object->select($task->object_id);
            //                 if ($percent > 0) $object->setStatus('open', true, false);
            //                 else $object->setStatus('close', true, false);
            //             }       
            //         }
            //     }  
            //     else $activity = 0;

                // if (isset($task->object_id)) Curtain::setRsMotorActivity($task->object_id, $activity);
            // }

            $topic = "modbus/".self::$idBus."/response";
            $payload = [
                'uid' => $task->uid,
                'raw' => $task->raw,
                'request' => $request,
                'response' => $response,
            ];
            Mqtt::publish($topic, $payload);

            $beanstalk->delete($job['id']);
        }
    }

}