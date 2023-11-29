<?php

use Beanstalk\Client;
use ModbusTcpClient\Packet\RtuConverter;
use ModbusTcpClient\Packet\ModbusFunction\ReadCoilsRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadInputDiscretesRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteSingleCoilRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteSingleRegisterRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadInputRegistersRequest;
use ModbusTcpClient\Utils\Endian;

class ModbusQueue extends System {

    static private $bus;
    static private $beanstalk;
    
    /**
     * Получаем настройки для шины
     */
    public function __construct($idBus) {
        $sql = parent::$db->query("SELECT `modbus_buses`.`name` AS busname,
                                          `modbus_buses`.`type` AS bustype,
                                          `modbus_buses`.`baudrate` AS baudrate,
                                          `modbus_buses`.`parity` AS parity,
                                          `modbus_buses`.`stopbits` AS stopbits,
                                          `modbus_buses`.`ip` AS ip,
                                          `modbus_buses`.`port` AS port
                                   FROM `modbus_buses`
                                   WHERE `modbus_buses`.`id`= $idBus");
        
        self::$bus = $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Создаем клиент для очереди и вызываем функцию подключения к шине ModBus
     */
    public function runClient() { 

        self::$beanstalk = $beanstalk = new Client();

        try {
            $beanstalk->connect();
            $beanstalk->watch(self::$bus->busname);

            self::busConnection();
        }
        catch (Throwable $ex) {
            echo "Произошла ошибка:" . PHP_EOL;
            echo $ex . PHP_EOL;
        }
        finally {
            // When exiting i.e. on critical error conditions
            // you may also want to disconnect the consumer.
            $beanstalk->disconnect();
        }
    }

    /**
     * Подключаемся к шине Modbus
     */
    private static function busConnection() {
        
        try {
            $modbus = new ModbusRtu ();
            $modbus->deviceInit('/dev/ttyUSB0', self::$bus->baudrate, self::$bus->parity, 8, self::$bus->stopbits, 'none');
            $modbus->deviceOpen();
            $modbus->debug = true;
            
            self::jobProcess();
        }    
        catch (Throwable $ex) {
            echo "Произошла ошибка:" . PHP_EOL;
            echo $ex . PHP_EOL;
        }
        finally {
            $modbus->deviceClose();
        }
    }
    
    /**
     * Обрабатываем поступающие в очередь задачи
     */
    private static function jobProcess() {
        
        $beanstalk = self::$beanstalk;

        while (true) {
            
            $job = $beanstalk->reserve(); // Block until job is available.
            $body = json_decode($job['body']);
            $result = '';
        
            if ($body->function_code == 1)
                $tcpPacket = new ReadCoilsRequest($body->starting_address, $body->quantity, $body->slave_address);
            // if ($body->function_code == 2)
                // $tcpPacket = new ReadInputDiscretesRequest($body->starting_address, $body->quantity, $body->slave_address);
            if ($body->function_code == 3)
                $tcpPacket = new ReadHoldingRegistersRequest($body->starting_address, $body->quantity, $body->slave_address);
            if ($body->function_code == 4)
                $tcpPacket = new ReadInputRegistersRequest($body->starting_address, $body->quantity, $body->slave_address);
            if ($body->function_code == 5)
                $tcpPacket = new WriteSingleCoilRequest($body->starting_address, $body->value, $body->slave_address);
            if ($body->function_code == 6)
                $tcpPacket = new WriteSingleRegisterRequest($body->starting_address, $body->value, $body->slave_address);
                
            $rtuPacket = RtuConverter::toRtu($tcpPacket);
            $binaryData = $modbus->send($rtuPacket);
                
            if ($binaryData) {

                    // Обработка ответа при чтении Coil регистров
                    if ($body->function_code == 1) {
                        $response = RtuConverter::fromRtu($binaryData)->getCoils();
                        $result = (int)$response[0];
                        echo $body->title . ': ' . $result . PHP_EOL;
                        echo PHP_EOL;
                    }

                    // Обработка ответа при чтении Holding регистров
                    if ($body->function_code == 3) {
                        $response = RtuConverter::fromRtu($binaryData)->withStartAddress($body->starting_address);
                        if ($body->format == 'float') $result = $response->getDoubleWordAt($body->starting_address)->getFloat(Endian::BIG_ENDIAN);
                        if ($body->format == 'uint32') $result = $response->getDoubleWordAt($body->starting_address)->getUInt32(Endian::BIG_ENDIAN);
                        if ($body->format == 'int32') $result = $response->getDoubleWordAt($body->starting_address)->getInt32(Endian::BIG_ENDIAN);
                        if ($body->format == 'uint16') $result = $response->getWordAt($body->starting_address)->getUInt16();
                        if ($body->format == 'int16') $result = $response->getWordAt($body->starting_address)->getInt16();
                        if ($body->format == 'f8.8') $result = $response->getWordAt($body->starting_address)->getUInt16() / 256;
                        echo $body->title . ': ' . $result . $body->units . PHP_EOL;
                        echo PHP_EOL;
                    }

                    // Обработка ответа при чтении Input регистров
                    if ($body->function_code == 4) {
                        $response = RtuConverter::fromRtu($binaryData)->withStartAddress($body->starting_address);
                        if ($body->format == 'string') {
                            $i = 0;
                            while ($response->getAsciiStringAt($body->starting_address+$i, 1)) {
                                $result .= $response->getAsciiStringAt($body->starting_address+$i, 1);
                                $i++;
                            }
                        }
                        if ($body->format == 'uint32') $result = $response->getDoubleWordAt($body->starting_address)->getUInt32(Endian::BIG_ENDIAN);
                        if ($body->format == 'uint16') $result = $response->getWordAt($body->starting_address)->getUInt16();
                        if ($body->format == 'int16') $result = $response->getWordAt($body->starting_address)->getInt16();
                        echo 'Value: ' . $result . PHP_EOL;
                        echo PHP_EOL;
                    }

                    // Обработка ответа при записи одного Coil регистра
                    if ($body->function_code == 5) {
                        $response = RtuConverter::fromRtu($binaryData);
                        echo $body->title . ': ' . $body->value . PHP_EOL;
                        echo PHP_EOL;
                    }

                    // Обработка ответа при записи одного Holding/Input регистра
                    if ($body->function_code == 6) {
                        $response = RtuConverter::fromRtu($binaryData);
                        echo $body->title . ': ' . $body->value . PHP_EOL;
                        echo PHP_EOL;
                    }

                }
                
                $beanstalk->delete($job['id']);

            }

    }

}