<?php

class ModbusRtu
{
    public $debug = false;
    public $fd = null;
    private $sttyModes = array();
    private $device = null;

    public function deviceInit($device, $baudrate, $parity, $char, $sbits, $flow)
    {
        $this->device = $device;
        if (!$this->confBaudrate($baudrate)) {
            echo 'Error: Invalid baudrate' . PHP_EOL;
            exit(1);
        }
        if (!$this->confParity($parity)) {
            echo 'Error: Invalid parity' . PHP_EOL;
            exit(1);
        }
        if (!$this->confCharacterLength($char)) {
            echo 'Error: Invalid number of data bits' . PHP_EOL;
            exit(1);
        }
        if (!$this->confStopBits($sbits)) {
            echo 'Error: Invalid number of stop bits' . PHP_EOL;
            exit(1);
        }
        if (!$this->confFlowControl($flow)) {
            echo 'Error: Invalid flow control' . PHP_EOL;
            exit(1);
        }

        $this->confOtherSettings();

        $modes = implode(' ', $this->sttyModes);
        $sttyResult = exec("stty -F $device $modes");
        if ($sttyResult === false) {
            echo 'stty command failed' . PHP_EOL;
            exit(1);
        }
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

    public function send($rtuPacket)
    {
        if ($this->debug) echo 'RTU Binary to sent (in hex):   ' . unpack('H*', $rtuPacket)[1] . PHP_EOL;
        fwrite($this->fd, $rtuPacket);

        fflush($this->fd);

        $binaryData = '';
        $start = microtime(true);
        do {
            // Give a modbus device time to respond. 
            // This is crucial for some serial devices and delay needs to be even longer (100ms) 
            //or you will experience read errors or invalid CRCs
            usleep(100000);
            $binaryData = fread($this->fd, 255);
        } while ($binaryData === '' && (microtime(true) - $start) < 3);

        if ($this->debug) {
            if ($binaryData) {
                $end = (microtime(true) - $start) * 1000;
                echo 'Response in: ' . $end . ' ms' . PHP_EOL;
                echo 'RTU Binary received (in hex):   ' . unpack('H*', $binaryData)[1] . PHP_EOL;
            }
            else echo "No response from device" . PHP_EOL;
        }
        
        return $binaryData;
    }

}