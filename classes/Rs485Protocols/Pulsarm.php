<?php

class Pulsarm
{
    public static function getChannelPackage($devAddr, $chAddr)
    {
        $p = array_merge(
            self::splitAddress($devAddr),
            ['01'],
            ['0e'],
            Utils::getBytesArray(
                Utils::setBit(0, $chAddr),
                LITTLE_ENDIAN,
                4
            ),
            ['77', '77']
        );

        $verifyCode = array_map(
            array('Utils', 'arrayFormat'),
            unpack(
                'C*',
                Modbus::crc16(pack ('c*', ...array_map('hexdec', $p)))
            )
        );

        $p = array_merge($p, $verifyCode);
        $p = implode(' ', $p);
        return $p;
    }

    public static function getParamPackage($devAddr, $chAddr)
    {
        $p = array_merge(
            self::splitAddress($devAddr),
            ['0a'],
            ['0c'],
            Utils::getBytesArray(
                $chAddr,
                LITTLE_ENDIAN,
                2
            ),
            ['77', '77']
        );

        $verifyCode = array_map(
            array('Utils', 'arrayFormat'),
            unpack(
                'C*',
                Modbus::crc16(pack ('c*', ...array_map('hexdec', $p)))
            )
        );

        $p = array_merge($p, $verifyCode);
        $p = implode(' ', $p);
        return $p;
    }

    public static function getPayload(array $response)
    {
        $payload = array_splice($response, 6);
        $payload = array_splice($payload, 0, -4);
        return array_reverse($payload);
    }

    private static function splitAddress($devAddr)
    {
        $symbols = str_split($devAddr);
        $bytes = [];
        while($symbols) {
            $byte = implode('', array_splice($symbols, -2, 2));
            $bytes[] = $byte;
        }
        return array_reverse($bytes);
        // return str_split(str_pad($devAddr, 8, '0', STR_PAD_LEFT), 2);
    }
}

