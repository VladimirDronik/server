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
        $writeFunctionCodesArray = [5, 6, 15, 16];
        while (true)
        {
            $job = $beanstalk->reserve(); // Block until job is available.
            $task = json_decode($job['body']);
            // TODO: Добавить обработку задачи для приводов штор с RS-485
            $packet = modbusFunction($task);

            $slaverId = "Slaver ID: " . $task->slave_address . " (0x" . dechex($task->slave_address).")";
            $registerAddress = "Register: " . $task->starting_address . " (0x" . dechex($task->starting_address).")";
            $functionCode = "Function code: " . "0x" . dechex($task->function_code);
            
            
            $logString = "[Modbus queue]    Register ID $task->register_id. Task: $slaverId, $registerAddress, $functionCode";
            if (in_array($task->function_code, $writeFunctionCodesArray)) 
            {
                $value = "Value: " . $task->value . "(0x" . dechex($task->value).")";
                $logString .= ", " . $value;
            }
            $logString .= PHP_EOL;

            $logString  = (new datetime())->format('Y-m-d H:i:s.v') . "  " . $logString;
            System::addStringToLogFile($logString);
            
            $binaryData = self::$modbus->send($packet);
            // Modbus::processResponse($binaryData);
            if ($binaryData) 
            {
                $activity = 1;
                $response = modbusFunction($task, true, $binaryData);
                // if (isset($response))
                // {
                // if (in_array($task->function_code, $writeFunctionCodesArray)) $response = null;
                // }
                
            }
            else
            {
                $activity = 0;
                $response = null;
            }
            // var_dump ($response);
            Modbus::setValue($task->register_id, $response);
            Modbus::setSlaverActivity($task->slaver_id, $activity);
            // System::addStringToLogFile(PHP_EOL);
            $beanstalk->delete($job['id']);
        }
    }

}