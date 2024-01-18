<?php

use Beanstalk\Client;

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
        $beanstalk = self::$beanstalk;

        while (true)
        {
            $job = $beanstalk->reserve(); // Block until job is available.
            $task = json_decode($job['body']);
            // TODO: Добавить обработку задачи для приводов штор с RS-485
            $packet = modbusFunction($task);
            $binaryData = self::$modbus->send($packet);
            if ($binaryData) 
            {
                $response = modbusFunction($task, true, $binaryData);
                if (isset($response))
                {
                    if ($task->function_code == 5) 
                    {
                       $response = $task->value;
                    }
                    elseif ($task->function_code == 6) 
                    {
                        $response = $task->value;
                    }
                    // elseif ($task->function_code == 1) 
                    // {
                    //     if ($response) $response = 'true';
                    //     else $response = 'false';
                    // }
                    else if ($response == 0) $response = "0";
                }
                $activity = 1;
            }
            else
            {
                $response = NULL;
                $activity = 0;
            }

            Modbus::setValue($task->register_id, $response);
            Modbus::setSlaverActivity($task->slaver_id, $activity);
            $beanstalk->delete($job['id']);
        }
    }

}