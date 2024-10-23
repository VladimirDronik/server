<?php

// class Onviz
// {
    function arrayFormat($item)
    {
        $result = dechex($item);
        if (strlen($result) < 2) $result = '0' . $result;
        return $result;
    }

    function getProtocol(string $command, $object, $value = null)
    {
        

        $headByte = ['55'];

        $commands = [
            'open' => [
                'bytes' => ['03', '01'],
                'value' => false,
                'isResponse' => true
            ],
            'close' => [
                'bytes' => ['03', '02'],
                'value' => false,
                'isResponse' => true
            ],
            'stop' => [
                'bytes' => ['03', '03'],
                'value' => false,
                'isResponse' => true
            ],
            'setPercent' => [
                'bytes' => ['03', '04'],
                'value' => true,
                'isResponse' => true
            ],
            'getPercent' => [
                'bytes' => ['01', '02', '01'],
                'value' => false,
                'isResponse' => true
            ],
            'setAddress' => [
                'bytes' => ['02', '00', '02'],
                'value' => true,
                'isResponse' => true
            ]
        ];

        if ($command == 'setAddress')
        {
            if ($object->type == 'roller')
            {
                $address[] = 'fe';
                $group[] = 'fe';
            }
            if ($object->type == 'curtain')
            {
                $address[] = '00';
                $group[] = '00';
            }
            $value = [$object->address, $object->group];
            $value = array_map('self::arrayFormat', $value);
        }
        else
        {
            if (strlen(dechex($object->address)) < 2) $address[] = '0' . $object->address;
            else $address[] = dechex($object->address);

            if (strlen(dechex($object->group)) < 2) $group[] = '0' . $object->group;
            else $group[] = dechex($object->group);
        }

        if ($command == 'getPercent') $targetByte = 6;

        if (isset($value))
        {
            if (!is_array($value)) {
                $value = dechex($value);
                if (strlen($value) < 2) $value = '0' . $value;
                $value = [$value];
            }
            $cmd = $commands[$command]['bytes'];
            $cmd = array_merge($cmd, $value);
        }
        else $cmd = $commands[$command]['bytes'];

        $packageArray = array_merge($headByte, $address, $group, $cmd);

        $verifyCode = array_map('arrayFormat', unpack('C*', Modbus::crc16(pack ('c*', ...array_map('hexdec', $packageArray)))));

        $packageArray = array_merge($packageArray, $verifyCode);

        $protocol['cmd'] = implode('', $packageArray);
        $protocol['isResponse'] = $commands[$command]['isResponse'];

        if (isset($targetByte)) $protocol['targetByte'] = $targetByte;

        return $protocol;
    }
// }