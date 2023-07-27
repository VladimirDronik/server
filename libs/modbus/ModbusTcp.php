<?php

class ModbusTcp
{
    public $debug = false;
    public $_socket = null;
    // public $socket = null;

    // Calculate CRC16 (ModBus)
	public function crc16($data)
	{
		$crc = 0xFFFF;
		for ($i = 0; $i < strlen($data); $i++)
		{
			$crc ^=ord($data[$i]);
     		for ($j = 8; $j !=0; $j--)
			{
				if (($crc & 0x0001) !=0)
				{
					$crc >>= 1;
					$crc ^= 0xA001;
				}
				else
				$crc >>= 1;
			}		
		}
		$highCrc=floor($crc/256);
		$lowCrc=($crc-$highCrc*256);
		return chr($lowCrc).chr($highCrc);
	}

    public function socketCreate ($domain = AF_INET, $type = SOCK_STREAM, $protocol = SOL_TCP)
    {
        $this->_socket = socket_create($domain, $type, $protocol) or die("Could not create socket\n");
        if (!$this->_socket) echo "Unable to create socket: ". socket_strerror(socket_last_error()) . PHP_EOL . "\n";
    }

    public function socketSetOption ($level = SOL_SOCKET, $option = SO_SNDTIMEO, $value = array('sec' => 1, 'usec' => 0))
    {
        if (!socket_set_option($this->_socket, $level, $option, $value))
            echo "Unable to set option on socket: ". socket_strerror(socket_last_error()) . PHP_EOL . "\n";
    }

    public function socketConnect ($host, $port)
    {
        if (!socket_connect($this->_socket, $host, $port))
            echo "Could not connect to socket: ". socket_strerror(socket_last_error()) . PHP_EOL . "\n";
    }

    public function socketWrite ($packet)
    {
        if (!socket_write($this->_socket, $packet, strlen($packet)))
            echo "Could not send data to socket: ". socket_strerror(socket_last_error()) . PHP_EOL . "\n";
        if ($this->debug) print "DEBUG [query sent]: ".$this->bin2hexString($packet)."\n";
    }
    
    public function socketRead ()
    {
        if (!$response = socket_read ($this->_socket, 1024))
            echo "Could not read response: ". socket_strerror(socket_last_error()) . PHP_EOL . "\n";
        if ($this->debug) print "DEBUG [response received]: ".$this->bin2hexString($response)."\n";
        return $response;
    }

    public function socketClose ()
    {
        socket_close($this->_socket);
    }
    
    public function modbusTcpTransactionId()
    {
        $transactionId = random_bytes(2); // Transaction ID
        return $transactionId;
    }

    private function bin2hexString ($binString)
	{
		$hexString=bin2hex($binString);
		$hexString=chunk_split($hexString,2,"\\x");
		$hexString= "\\x" . substr($hexString,0,-2);
		return $hexString;
	}
}

?>