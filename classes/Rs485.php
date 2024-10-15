<?php

use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Network\SerialStreamCreator;
use ModbusTcpClient\Packet\RtuConverter;
use ModbusTcpClient\Packet\ModbusFunction\ReadCoilsRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadInputDiscretesRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadInputRegistersRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadInputRegistersResponse;
use ModbusTcpClient\Packet\ModbusFunction\WriteSingleCoilRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteSingleRegisterRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteMultipleCoilsRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteMultipleRegistersRequest;
use ModbusTcpClient\Packet\ResponseFactory;
use ModbusTcpClient\Utils\Packet;
use ModbusTcpClient\Utils\Endian;
use ModbusTcpClient\Utils\Types;

class Rs485 extends System
{
    private $bus;

    function __construct($busId) {
        if (isset($busId)) {
            $sql = parent::$db->query(" SELECT `modbus_buses`.`id`,
                                               `modbus_buses`.`device`,
                                               `modbus_buses`.`type`,
                                               `modbus_buses`.`baudrate`,
                                               `modbus_buses`.`length`,
                                               `modbus_buses`.`parity`,
                                               `modbus_buses`.`stopbits`,
                                               `modbus_buses`.`ip_address`,
                                               `modbus_buses`.`port`
                                        FROM `modbus_buses`
                                        WHERE `modbus_buses`.`id`= $busId");
            
            if ($sql->rowCount() > 0) {
                $this->bus = $sql->fetch(PDO::FETCH_OBJ);
            }
            else {
                echo "Modbus шина с ID $busId не найдена" . PHP_EOL;
                exit;
            }
        }
        else {
            echo "[Error] Не определен ID шины" . PHP_EOL;
            exit;
        }
    }

    public function setSerialParams() {
        return [ 
            $this->setCharacterSize(), // set character size
            $this->bus->baudrate, // set baud rate
            $this->setStopBits(), // set stop bits
            $this->setParity(), // set parity
            '-icanon', // disable enable special characters: erase, kill, werase, rprnt
            'min 0', // with -icanon, set N characters minimum for a completed read
            'ignbrk', // enable ignore break characters
            '-brkint', // disable breaks cause an interrupt signal
            '-icrnl', // disable translate carriage return to newline
            '-imaxbel', // disable beep and do not flush a full input buffer on a character
            '-opost', // disable postprocess output
            '-onlcr', // disable translate newline to carriage return-newline
            '-isig', // disable interrupt, quit, and suspend special characters
            '-iexten', // disable non-POSIX special characters
            '-echo', // disable echo input characters
            '-echoe', // disable echo erase characters as backspace-space-backspace
            '-echok', // disable echo a newline after a kill character
            '-echoctl', // disable same as [-]ctlecho
            '-echoke', // disable kill all line by obeying the echoprt and echoe settings
            '-noflsh', // disable flushing after interrupt and quit special characters
            '-ixon', // disable XON/XOFF flow control
            '-crtscts', // disable RTS/CTS handshaking
        ];
    }

    private function setParity() {
        $args = [
            "none" => "-parenb",
            "odd"  => "parenb parodd",
            "even" => "parenb -parodd"
        ];
        return $args[$this->bus->parity];
    }

    private function setCharacterSize() {
        return "cs" . $this->bus->length;
    }

    private function setStopBits() {
        return (($this->bus->stopbits == 1) ? "-" : "") . "cstopb";
    }

    private function sendRawPacket($rawPacket, $connection)
    {
        fwrite($connection->connect()->getStream(), $rawPacket);
    }

    private function recieveRawPacket($connection)
    {
        usleep($connection->getDelayRead());
        return fread($connection->getStream(), 256);
    }

    public function sendRaw(string $cmd, bool $needResponse = true, $targetByte = null, int $priority = null)
    {
        Mqtt::connectRs485();
        $uid = uniqid();
        $rawData = pack ('c*', ...array_map('hexdec', str_split(str_replace(' ', '', $cmd), 2)));
        $task = [
            'uid' => $uid,
            'protocol' => 'raw',
            'raw_data' => base64_encode($rawData),
            'needResponse' => $needResponse
        ];

        if (isset($targetByte)) $task['targetByte'] = $targetByte;

        if (!isset($priority)) $priority = 50;
        BeanstalkQueue::putTask($this->bus->id, $task, $priority);

        $response = Mqtt::subscribeRs485("rs485/{$this->bus->id}/response", $uid);

        if (isset($response) && !$response['error'])
        {
            if (isset($targetByte)) return $response['response'];
            return $response['raw_response'];
        }
        else return null;
    }

    public function queue()
    {
        function arrayFormat($item) {
            $result = dechex($item);
            if (strlen($result) < 2) $result = '0' . $result;
            return $result;
        }

        $queue = BeanstalkQueue::startQueue($this->bus->id);

        while (true) {
            $job = $queue->reserve(); // Block until job is available.
            $task = json_decode($job['body']);
            $connection = $this->busConnection();
            $packet = $this->getPacket($task);
            $request = unpack('H*', $packet)[1];

            try {
                echo PHP_EOL . 'Packet to sent (in hex):   ' . $request . PHP_EOL;

                if ($this->bus->type == 'rtu' && $task->protocol == 'raw') $this->sendRawPacket($packet, $connection);
                else {
                    $connection->connect()->send($packet);
                    // var_dump($connection);
                }

                if (!$task->needResponse) {
                    $rawResponse = "Response is not needed";
                    $response = null;
                    $error = false;
                }
                else {
                    $start = microtime(true);
                    if ($this->bus->type == 'rtu' && $task->protocol == 'raw')
                        $binaryData = $this->recieveRawPacket($connection);
                    else $binaryData = $connection->receive();

                    if ($binaryData) {
                        $end = (microtime(true) - $start) * 1000;
                        echo 'Response in: ' . $end . ' ms' . PHP_EOL;
                        echo 'Binary received (in hex):   ' . unpack('H*', $binaryData)[1] . PHP_EOL;
                        if ($this->bus->type == 'tcp') $binaryData = substr((string)$binaryData, 6);
                        $rawResponse = array_map('arrayFormat', unpack('C*', $binaryData));
                        $error = false;
                        if (isset($task->targetByte)) $response = $rawResponse[$task->targetByte];
                        if ($task->protocol == 'modbus') $response = $this->getResponse($binaryData, $task);
                        if (isset($task->scale) && $task->function_code < 15) $response *= $task->scale;
                        if (isset($response)) echo 'Response: ' . $response . PHP_EOL;
                    }
                    else {
                        echo 'No response from device' . PHP_EOL;
                        $rawResponse = null;
                        $response = null;
                        $error = true;
                        $errorCode = "No response from device";
                    }
                }
                
                $topic = "rs485/{$this->bus->id}/response";
                $payload = [
                    'uid' => $task->uid,
                    'error' => $error,
                    'protocol' => $task->protocol,
                    'request' => $request,
                    'raw_response' => $rawResponse,
                ];
                if (isset($response)) $payload += ['response' => $response,];
                if ($error) $payload += ['error_code' => $errorCode,];
                Mqtt::publish($topic, $payload);
            }
            catch (Exception $exception) {
                echo 'An exception occurred' . PHP_EOL;
                echo $exception->getMessage() . PHP_EOL;
                echo $exception->getTraceAsString() . PHP_EOL;
            }
            finally {
                $connection->close();
                $queue->delete($job['id']);
            }
        }
    }

    private function busConnection() {
        if ($this->bus->type == 'rtu') {
            $sttyModes = $this->setSerialParams();
            return BinaryStreamConnection::getBuilder()
                ->setUri($this->bus->device)
                ->setProtocol('serial')
                ->setCreateStreamCallback(static function (BinaryStreamConnection $conn) use ($sttyModes) {
                    $streamCreator = new SerialStreamCreator(['sttyModes' => $sttyModes]);
                    return $streamCreator->createStream($conn);
                })
                ->setIsCompleteCallback(static function ($binaryData, $streamIndex): bool {
                    return Packet::isCompleteLengthRTU($binaryData);
                })
                // delay this is crucial for some serial devices and delay needs to be long as 100ms (depending on the quantity)
                // or you will experience read errors ("stream_select interrupted") or invalid CRCs
                ->setDelayRead(250_000) // 100 milliseconds, serial devices may need delay between sending and received
                ->build();
        }
        if ($this->bus->type == 'tcp') {
            return BinaryStreamConnection::getBuilder()
                ->setPort($this->bus->port)
                ->setHost($this->bus->ip_address)
                ->build();
        }
    }

    private function getPacket($task) {
        if ($task->protocol == 'raw') {
            $rawPacket = base64_decode($task->raw_data);
            if ($this->bus->type == 'rtu') return $rawPacket;
            if ($this->bus->type == 'tcp') {
                $rawPacket = substr($rawPacket, 0, -2);
                return random_bytes(2) . "\x00\x00" . pack('n', strlen($rawPacket)) . $rawPacket;
            }
        }

        if ($task->protocol == 'modbus') {
            switch ($task->function_code) {
                case 1:
                    $modbusPacket = new ReadCoilsRequest($task->starting_address, $task->quantity, $task->slave_address);
                    break;
                case 2:
                    $modbusPacket = new ReadInputDiscretesRequest($task->starting_address, $task->quantity, $task->slave_address);
                    break;
                case 3:
                    $modbusPacket = new ReadHoldingRegistersRequest($task->starting_address, $task->quantity, $task->slave_address);
                    break;
                case 4:
                    $modbusPacket = new ReadInputRegistersRequest($task->starting_address, $task->quantity, $task->slave_address);
                    break;
                case 5:
                    $modbusPacket = new WriteSingleCoilRequest($task->starting_address, $task->value, $task->slave_address);
                    break;
                case 6:
                    $modbusPacket = new WriteSingleRegisterRequest($task->starting_address, $task->value, $task->slave_address);
                    break;
                case 15:
                    $modbusPacket = new WriteMultipleCoilsRequest($task->starting_address, $task->value, $task->slave_address);
                    break;
                case 16:
                    foreach ($task->value as &$value) 
                    {
                        if (isset($task->scale)) $value /= $task->scale;
                        switch ($task->format) {
                            case 's8':
                            case 'u8':
                                $value = Types::toByte($value);
                                break;
                            case 's16':
                                $value = Types::toInt16($value);
                                break;
                            case 'u16':
                                $value = Types::toUint16($value);
                                break;
                            case 's32':
                                $value = Types::toInt32($value);
                                break;
                            case 'u32':
                                $value = Types::toUint32($value);
                                break;
                            case 'double':
                                $value = Types::toDouble($value);
                                break;
                        }
                    }
                    $modbusPacket = new WriteMultipleRegistersRequest($task->starting_address, $task->value, $task->slave_address);
                    break;
            }

            if ($this->bus->type == 'tcp') return $modbusPacket;
            if ($this->bus->type == 'rtu') return RtuConverter::toRtu($modbusPacket);
        }
    }

    public function getResponse($binaryData, $task) {
        Endian::$defaultEndian = Endian::BIG_ENDIAN;
        if ($this->bus->type == 'tcp') $response = ResponseFactory::parseResponseOrThrow($binaryData);
        if ($this->bus->type == 'rtu') $response = RtuConverter::fromRtu($binaryData);

        switch ($task->function_code) {
            case 1:
            case 2:
                return $response->getCoils()[0] ? '1' : '0';
                break;
            case 3:
            case 4:
                $result = $response->getWordAt(0);
                if ($task->format == 'double') return $result->getDouble();
                if ($task->format == 'u64') return $result->getUInt64();
                if ($task->format == 's64') return $result->getInt64();
                if ($task->format == 'float') return $result->getFloat();
                if ($task->format == 'u32') return $result->getUInt32();
                if ($task->format == 's32') return $result->getInt32();
                if ($task->format == 'u16') return $result->getUInt16();
                if ($task->format == 's16') return $result->getInt16();
                if ($task->format == 'f8.8') return Boiler::convertToF88($result->getUInt16());
                if ($task->format == 'raw') return unpack('H*', mb_strcut($binaryData, 3, $task->quantity*2))[1];
                break;
            case 5:
                return $response->isCoil() ? '1' : '0';
                break;
            case 6:
                $result = $response->getWord();
                if ($task->format == 'double') return $result->getDouble();
                if ($task->format == 'u64') return $result->getUInt64();
                if ($task->format == 's64') return $result->getInt64();
                if ($task->format == 'float') return $result->getFloat();
                if ($task->format == 'u32') return $result->getUInt32();
                if ($task->format == 's32') return $result->getInt32();
                if ($task->format == 'u16') return $result->getUInt16();
                if ($task->format == 's16') return $result->getInt16();
                if ($task->format == 'f8.8') return Boiler::convertToF88($result->getUInt16());
                break;
            case 15:
                return $response->getCoilCount();
                break;
            case 16:
                return $response->getRegistersCount();
                break;
        }
    }

    /**
     * Получение списка шин RS485 из БД
     */
    public static function getBuses() {
        $sql = parent::$db->query(" SELECT `modbus_buses`.`id` AS 'bus_id'
                                    FROM `modbus_buses`");
        if($sql->rowCount() > 0) {
            $buses = $sql->fetchAll(PDO::FETCH_OBJ);
            foreach ($buses AS $bus) $busesArray[] = $bus->bus_id;
            return $busesArray;
        }
    }
}