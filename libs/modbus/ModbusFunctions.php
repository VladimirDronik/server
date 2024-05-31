<?php

use ModbusTcpClient\Packet\RtuConverter;
use ModbusTcpClient\Packet\ModbusFunction\ReadCoilsRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadInputDiscretesRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteSingleCoilRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteSingleRegisterRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadInputRegistersRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteMultipleRegistersRequest;
use ModbusTcpClient\Utils\Types;
use ModbusTcpClient\Utils\Endian;

/**
 * Вызываем необходимый метод в зависимости от отправленного кода
 */
function modbusFunction($task, bool $response = false, $binaryData = null)
{
    
    if ($task->function_code == 1)
    {
        if ($response) return readCoilsResponse($task, $binaryData);
        else return readCoilsRequest($task);
    }

    if ($task->function_code == 2)
    {
        if ($response) return readInputDiscretesResponse($task, $binaryData);
        else return readInputDiscretesRequest($task);
    }
        
    if ($task->function_code == 3)
    {
        if ($response) return readHoldingRegistersResponse($task, $binaryData);
        else return readHoldingRegistersRequest($task);
    }

    if ($task->function_code == 4)
    {
        if ($response) return readInputRegistersResponse($task, $binaryData);
        else return readInputRegistersRequest($task);
    }

    if ($task->function_code == 5)
    {
        if ($response) return writeSingleCoilResponse($task, $binaryData);
        else return writeSingleCoilRequest($task);
    }

    if ($task->function_code == 6)
    {
        if ($response) return writeSingleRegisterResponse($task, $binaryData);
        else return writeSingleRegisterRequest($task);
    }

    if ($task->function_code == 16)
    {
        if ($response) return writeMultipleRegistersResponse($task, $binaryData);
        else return writeMultipleRegistersRequest($task);
    }
}

    /**
     * Функция чтения одного Coil регистра - 01
     */
    function readCoilsRequest($task)
    {
        $tcpPacket = new ReadCoilsRequest($task->starting_address, $task->quantity, $task->slave_address);
        $rtuPacket = RtuConverter::toRtu($tcpPacket);
        return $rtuPacket;
    }

    /**
     * Обработка ответа чтения одного Coil регистра
     */
    function readCoilsResponse($task, $binaryData)
    {
        $response = RtuConverter::fromRtu($binaryData)->getCoils();
        $result = (bool)$response[0];
        if ($result) $result = 'true';
        else $result = 'false';
        echo (new datetime())->format('Y-m-d H:i:s.v') . "   " . $task->title . ': ' . $result . PHP_EOL;
        echo PHP_EOL;
        return $result;
    }

    /**
     * Функция чтения одного Discrete Input регистра - 02
     */
    function readInputDiscretesRequest($task)
    {
        $tcpPacket = new ReadInputDiscretesRequest($task->starting_address, $task->quantity, $task->slave_address);
        $rtuPacket = RtuConverter::toRtu($tcpPacket);
        return $rtuPacket;
    }

    /**
     * Обработка ответа чтения одного Discrete Input регистра
     */
    function readInputDiscretesResponse($task, $binaryData)
    {
        if ($task->format == 'double') $result = $response->getQuadWordAt($task->starting_address)->getDouble();
        if ($task->format == 'u64') $result = $response->getQuadWordAt($task->starting_address)->getUInt64();
        if ($task->format == 's64') $result = $response->getQuadWordAt($task->starting_address)->getInt64();
        if ($task->format == 'float') $result = $response->getDoubleWordAt($task->starting_address)->getFloat(Endian::BIG_ENDIAN);
        if ($task->format == 'u32') $result = $response->getDoubleWordAt($task->starting_address)->getUInt32(Endian::BIG_ENDIAN);
        if ($task->format == 's32') $result = $response->getDoubleWordAt($task->starting_address)->getInt32(Endian::BIG_ENDIAN);
        if ($task->format == 'u16') $result = $response->getWordAt($task->starting_address)->getUInt16();
        if ($task->format == 's16') $result = $response->getWordAt($task->starting_address)->getInt16();
        if ($task->format == 'raw') $result = unpack('H*', mb_strcut($binaryData, 3, mb_strlen($binaryData, '8bit')-4))[1];

        if ($task->scale) $result = $result * $task->scale;

        if (isset($result)) 
        {
            if (is_null($result)) $result = 0;
            echo (new datetime())->format('Y-m-d H:i:s.v') . "   " . $task->title . ': ' . $result . " " . $task->units . PHP_EOL;
            echo PHP_EOL;
            return strval($result);
        }
        else return null;
    }

    /**
     * Функция чтения одного Holding регистра - 03
     */
    function readHoldingRegistersRequest($task)
    {
        $tcpPacket = new ReadHoldingRegistersRequest($task->starting_address, $task->quantity, $task->slave_address);
        $rtuPacket = RtuConverter::toRtu($tcpPacket);
        return $rtuPacket;
    }

    /**
     * Обработка ответа чтения одного Holding регистра
     */
    function readHoldingRegistersResponse($task, $binaryData)
    {
        Endian::$defaultEndian = Endian::BIG_ENDIAN;

        $response = RtuConverter::fromRtu($binaryData)->withStartAddress($task->starting_address);
        // var_dump ($response);
        if (is_a($response, 'ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersResponse'))
        {
            if ($task->format == 'double') $result = $response->getQuadWordAt($task->starting_address)->getDouble();
            if ($task->format == 'u64') $result = $response->getQuadWordAt($task->starting_address)->getUInt64();
            if ($task->format == 's64') $result = $response->getQuadWordAt($task->starting_address)->getInt64();
            if ($task->format == 'float') $result = $response->getDoubleWordAt($task->starting_address)->getFloat();
            if ($task->format == 'u32') $result = $response->getDoubleWordAt($task->starting_address)->getUInt32();
            if ($task->format == 's32') $result = $response->getDoubleWordAt($task->starting_address)->getInt32();
            if ($task->format == 'u16') $result = $response->getWordAt($task->starting_address)->getUInt16();
            if ($task->format == 's16') $result = $response->getWordAt($task->starting_address)->getInt16();
            // if ($task->format == 'f8.8') $result = $response->getWordAt($task->starting_address)->getUInt16() / 256;
            if ($task->scale) $result = $result * $task->scale;
            if ($task->format == 'raw') $result = unpack('H*', mb_strcut($binaryData, 3, $task->quantity*2))[1];
            echo (new datetime())->format('Y-m-d H:i:s.v') . "   " . $task->title . ': ' . $result . " " . $task->units . PHP_EOL;
            echo PHP_EOL;
            return strval($result);
        }

    }

    /**
     * Функция чтения одного Input регистра - 04
     */
    function readInputRegistersRequest($task)
    {
        $tcpPacket = new ReadInputRegistersRequest($task->starting_address, $task->quantity, $task->slave_address);
        $rtuPacket = RtuConverter::toRtu($tcpPacket);
        return $rtuPacket;
    }

    /**
     * Обработка ответа чтения одного Input регистра
     */
    function readInputRegistersResponse($task, $binaryData)
    {
        $response = RtuConverter::fromRtu($binaryData)->withStartAddress($task->starting_address);
        if ($task->format == 'string')
        {
            $i = 0;
            $result = null;
            while ($response->getAsciiStringAt($task->starting_address+$i, 1))
            {
                $result .= $response->getAsciiStringAt($task->starting_address+$i, 1);
                $i++;
            }
        }
        if ($task->format == 'double') $result = $response->getQuadWordAt($task->starting_address)->getDouble();
        if ($task->format == 'u64') $result = $response->getQuadWordAt($task->starting_address)->getUInt64();
        if ($task->format == 's64') $result = $response->getQuadWordAt($task->starting_address)->getInt64();
        if ($task->format == 'float') $result = $response->getDoubleWordAt($task->starting_address)->getFloat(Endian::BIG_ENDIAN);
        if ($task->format == 'u32') $result = $response->getDoubleWordAt($task->starting_address)->getUInt32(Endian::BIG_ENDIAN);
        if ($task->format == 's32') $result = $response->getDoubleWordAt($task->starting_address)->getInt32(Endian::BIG_ENDIAN);
        if ($task->format == 'u16') $result = $response->getWordAt($task->starting_address)->getUInt16();
        if ($task->format == 's16') $result = $response->getWordAt($task->starting_address)->getInt16();
        // if ($task->format == 'f8.8') $result = $response->getWordAt($task->starting_address)->getUInt16() / 256;
        if ($task->scale) $result = $result * $task->scale;

        if ($task->format == 'raw') $result = unpack('H*', mb_strcut($binaryData, 3, mb_strlen($binaryData, '8bit')-4))[1];
        
        echo (new datetime())->format('Y-m-d H:i:s.v') . "   " . $task->title . ': ' . $result . " " . $task->units . PHP_EOL;
        echo PHP_EOL;
        return strval($result);
    }

    /**
     * Функция записи одного Coil регистра - 05
     */
    function writeSingleCoilRequest($task)
    {
        $tcpPacket = new WriteSingleCoilRequest($task->starting_address, $task->value, $task->slave_address);
        $rtuPacket = RtuConverter::toRtu($tcpPacket);
        return $rtuPacket;
    }

    /**
     * Обработка ответа записи одного Coil регистра
     */
    function writeSingleCoilResponse($task, $binaryData)
    {
        $response = RtuConverter::fromRtu($binaryData);
        if ($task->value) $result = 'true';
        else $result = 'false';
        echo (new datetime())->format('Y-m-d H:i:s.v') . "   " . $task->title . ' изменено на: ' . $result . PHP_EOL;
        echo PHP_EOL;
        return $result;
    }

    /**
     * Функция записи одного регистра - 06
     */
    function writeSingleRegisterRequest($task)
    {
        if ($task->scale) $task->value = $task->value / $task->scale;
        $tcpPacket = new WriteSingleRegisterRequest($task->starting_address, $task->value, $task->slave_address);
        $rtuPacket = RtuConverter::toRtu($tcpPacket);
        return $rtuPacket;
    }

    /**
     * Обработка ответа записи одного регистра
     */
    function writeSingleRegisterResponse($task, $binaryData)
    {
        $response = RtuConverter::fromRtu($binaryData);
        echo (new datetime())->format('Y-m-d H:i:s.v') . "   " . $task->title . ': ' . $task->value . PHP_EOL;
        echo PHP_EOL;
        // return $response;
    }

    /**
     * Функция записи нескольких регистров - 16
     */
    function writeMultipleRegistersRequest($task)
    {
        Endian::$defaultEndian = Endian::BIG_ENDIAN;
        foreach ($task->value as &$value) 
        {
            if ($task->format == 's8' || $task->format == 'u8') $value = Types::toByte($value);
            if ($task->format == 's16') $value = Types::toInt16($value);
            if ($task->format == 'u16') $value = Types::toUint16($value);
            if ($task->format == 's32') $value = Types::toInt32($value);
            if ($task->format == 'u32') $value = Types::toUint32($value);
        }
       
        $tcpPacket = new WriteMultipleRegistersRequest($task->starting_address, $task->value, $task->slave_address);
        $rtuPacket = RtuConverter::toRtu($tcpPacket);
        return $rtuPacket;
    }

    /**
     * Обработка ответа записи одного регистра
     */
    function writeMultipleRegistersResponse($task, $binaryData)
    {
        $response = RtuConverter::fromRtu($binaryData);
        echo (new datetime())->format('Y-m-d H:i:s.v') . "   " . $task->title . ': ' . $task->value . PHP_EOL;
        echo PHP_EOL;
        return $response;
    }