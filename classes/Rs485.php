<?php

class Rs485 extends System
{
    public $debug = false;
    public $fd = null;
    private $sttyModes = array();
    private $device = null;

    public function deviceInit($device, $baudrate, $parity, $char, $sbits, $flow)
    {
        $this->device = $device;
        if (!$this->confBaudrate($baudrate)) echo 'Error: Invalid baudrate' . PHP_EOL;
        if (!$this->confParity($parity)) echo 'Error: Invalid parity' . PHP_EOL;
        if (!$this->confCharacterLength($char)) echo 'Error: Invalid number of data bits' . PHP_EOL;
        if (!$this->confStopBits($sbits)) echo 'Error: Invalid number of stop bits' . PHP_EOL;
        if (!$this->confFlowControl($flow)) echo 'Error: Invalid flow control' . PHP_EOL;

        $this->confOtherSettings();

        $modes = implode(' ', $this->sttyModes);
        $sttyResult = exec("stty -F $device $modes");
        if ($sttyResult === false) echo 'stty command failed' . PHP_EOL;
    }

    private function confBaudrate($baudrate)
    {
        $validBauds = array
        (
            110    => 110,
            150    => 150,
            300    => 300,
            600    => 600,
            1200   => 1200,
            2400   => 2400,
            4800   => 4800,
            9600   => 9600,
            19200  => 19200,
            38400  => 38400,
            57600  => 57600,
            115200 => 115200
        );
        if (isset($validBauds[$baudrate])) {
            array_push($this->sttyModes, $baudrate); 
            return true;
        }
        else return false;
    }

    private function confParity($parity)
    {
        $args = array
        (
            "none" => "-parenb",
            "odd"  => "parenb parodd",
            "even" => "parenb -parodd",
        );
        
        if (isset($args[$parity])) {
            array_push ($this->sttyModes, $args[$parity]);
            return true;
        }
        else return false;
    }

    private function confCharacterLength($int)
    {
        $int = (int) $int;
        if ($int < 5) $int = 5;
        elseif ($int > 8) $int = 8;
        array_push ($this->sttyModes, "cs" . $int);
        return true;
    }

    private function confStopBits($length)
    {
        if ($length == 1 || $length == 2) {
            array_push ($this->sttyModes, (($length == 1) ? "-" : "") . "cstopb");
            return true;
        }
        else return false;
    }

    private function confFlowControl($mode)
    {
        $modes = array
        (
            "none"     => "clocal -crtscts -ixon -ixoff",
            "rts/cts"  => "-clocal crtscts -ixon -ixoff",
            "xon/xoff" => "-clocal -crtscts ixon ixoff"
        );

        if (isset($modes[$mode])) {
            array_push ($this->sttyModes, $modes[$mode]);
            return true;
        }
        else return false;
    }

    private function confOtherSettings()
    {
        $otherSettings = array
        (
            "-icanon", // disable enable special characters: erase, kill, werase, rprnt
            "min 0", // with -icanon, set N characters minimum for a completed read
            "ignbrk", // enable ignore break characters
            "-brkint", // disable breaks cause an interrupt signal
            "-icrnl", // disable translate carriage return to newline
            "-imaxbel", // disable beep and do not flush a full input buffer on a character
            "-opost", // disable postprocess output
            "-onlcr", // disable translate newline to carriage return-newline
            "-isig", // disable interrupt, quit, and suspend special characters
            "-iexten", // disable non-POSIX special characters
            "-echo", // disable echo input characters
            "-echoe", // disable echo erase characters as backspace-space-backspace
            "-echok", // disable echo a newline after a kill character
            "-echoctl", // disable same as [-]ctlecho
            "-echoke", // disable kill all line by obeying the echoprt and echoe settings
            "-noflsh" // disable flushing after interrupt and quit special characters
        );

        $this->sttyModes = array_merge($this->sttyModes, $otherSettings);
    }

    public function deviceOpen()
    {
        $this->fd = fopen($this->device, 'w+b');
        // return $this->fd;
    }

    public function deviceClose()
    {
        fclose($this->fd);

    }

    public function deviceStatus()
    {
        return $this->fd;
    }

    public function send($rtuPacket, $needResponse = true)
    {
        echo 'RTU Binary to sent (in hex):   ' . unpack('H*', $rtuPacket)[1] . PHP_EOL;
        fwrite($this->fd, $rtuPacket);
        fflush($this->fd);
        if ($needResponse)
        {
            $binaryData = '';
            $start = microtime(true);
            do 
            {
                // Give a modbus device time to respond. 
                // This is crucial for some serial devices and delay needs to be even longer (100ms) 
                //or you will experience read errors or invalid CRCs
                // usleep(150000);
                usleep(500000);
                // usleep(500000);
                $binaryData = fread($this->fd, 255);
            } 
            while ($binaryData === '' && (microtime(true) - $start) < 3);
            $end = (microtime(true) - $start) * 1000;
            if ($binaryData)
            {
                echo 'Response in: ' . $end . ' ms' . PHP_EOL;
                echo 'RTU Binary received (in hex):   ' . unpack('H*', $binaryData)[1] . PHP_EOL;
                echo PHP_EOL;
                return $binaryData;
            }
            else
            {
                echo "No response from device" . PHP_EOL;
                echo PHP_EOL;
                return false;
            }
        }
        else 
        {
            echo 'Response is not needed' . PHP_EOL;
            echo PHP_EOL;
            return true;
        }
    }

    public function rawCmd(string $cmd, int $busId, bool $needResponse = true, $targetByte = null, int $priority = null)
    {
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
        BeanstalkQueue::putTask($busId, $task, $priority);

        $response = Mqtt::subscribe("rs485/$busId/response", $uid);

        if ($response && !$response['error'])
        {
            if (isset($targetByte)) return $response['response'];
            return $response['raw_response'];
        }
        else return null;
    }


    public static function queue(int $busId)
    {
        function arrayFormat($item)
        {
            $result = dechex($item);
            if (strlen($result) < 2) $result = '0' . $result;
            return $result;
        }

        $queue = BeanstalkQueue::startQueue($busId);
        $bus = self::busConnection($busId);
        
        $writeFunctionCodesArray = [5, 6, 15, 16];

        while (true)
        {
            $job = $queue->reserve(); // Block until job is available.
            $task = json_decode($job['body']);
            
            if ($task->protocol == 'raw') $binRequest = base64_decode($task->raw_data);
            if ($task->protocol == 'rtu') $binRequest = modbusFunction($task);

            $request = array_map('arrayFormat', unpack('C*', $binRequest));
            $request = implode(" ", $request);

            $binaryData = $bus->send($binRequest, $task->needResponse);

            if ($task->needResponse)
            {
                if ($binaryData)
                {
                    $rawResponse = unpack('C*', $binaryData);
                    $rawResponse = array_map('arrayFormat', unpack('C*', $binaryData));
                    // $rawResponse = implode(" ", $rawResponse);
                    $error = false;
                    if (isset($task->targetByte)) $response = $rawResponse[$task->targetByte];
                    if ($task->protocol == 'rtu') $response = modbusFunction($task, true, $binaryData);
                }
                else 
                {
                    $rawResponse = null;
                    $response = null;
                    $error = true;
                    $errorCode = "No response from device";
                }
            }
            else
            {
                $rawResponse = "Response is not needed";
                $response = null;
                $error = false;
                usleep(100000);
            }

            $topic = "rs485/$busId/response";

            $queue->delete($job['id']);
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
    }

    public static function busConnection(int $busId)
    {
            $bus = self::getRs485BusSettings($busId);
            if ($bus)
            {
                $modbus = new Rs485();
                $modbus->deviceInit($bus->busdevice, $bus->baudrate, $bus->parity,
                                    $bus->length, $bus->stopbits, 'none');
                $modbus->deviceOpen();
                $modbus->debug = true;
                return $modbus;
            }
            else return false;
    }

     /**
     * Получение настроек шины из БД
     */
    public static function getRs485BusSettings(int $idBus)
    {
        $sql = parent::$db->query(" SELECT `modbus_buses`.`device` AS 'busdevice',
                                           `modbus_buses`.`type` AS 'bustype',
                                           `modbus_buses`.`baudrate` AS 'baudrate',
                                           `modbus_buses`.`length` AS 'length',
                                           `modbus_buses`.`parity` AS 'parity',
                                           `modbus_buses`.`stopbits` AS 'stopbits',
                                           `modbus_buses`.`ip_address` AS 'ip',
                                           `modbus_buses`.`port` AS 'port'
                                    FROM `modbus_buses`
                                    WHERE `modbus_buses`.`id`= $idBus");
        
        if ($sql->rowCount() > 0) return $sql->fetch(PDO::FETCH_OBJ);
        else
        {
            echo "Modbus шина с ID $modbusRegisterId не найдена" . PHP_EOL;
            exit;
        }
    }

}