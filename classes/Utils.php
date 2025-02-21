<?php

class Utils
{
    public static function getBytesArray($value, $order = BIG_ENDIAN, $bytesAmount = null)
    {
        $bin = decbin($value);
        $bits_array = str_split($bin);
        $bytes_array = [];
        while($bits_array)
        {
            $byte = implode('', array_splice($bits_array, -8, 8));
            $byte = bindec($byte);
            $byte = dechex($byte);
            $byte = str_pad($byte, 2, '0', STR_PAD_LEFT);
            $bytes_array[] = $byte;
        }

        if (null !== $bytesAmount) {
            while (count($bytes_array) != $bytesAmount) {
                $bytes_array[] = '00';
            }
        }

        if ($order) $bytes_array = array_reverse($bytes_array);
        return $bytes_array;
    }

    public static function setBit($value, $bitNumber) {
        return $value | (1 << ($bitNumber-1));
    }

    public static function arrayFormat($item)
    {
        $result = dechex($item);
        if (strlen($result) < 2) $result = '0' . $result;
        return $result;
    }

    public static function formatData(array $data, $format, $scale = null)
    {
        switch($format)
        {
            case 'u16': $fCode = 'n';
                break;

            case 's16': $fCode = 's';
                break;

            case 'u32': $fCode = 'N';
                break;

            case 's32': $fCode = 'l';
                break;

            case 'u64': $fCode = 'J';
                break;
                
            case 's64': $fCode = 'q';
                break;
            
            case 'float': $fCode = 'G';
                break;

            case 'double': $fCode = 'E';
                break;
        }

        $value = implode('', $data);
        $value = hex2bin($value);
        $value = unpack($fCode, $value)[1];
        if (isset($scale)) $value *= 0.01;
        return $value;
    }
}