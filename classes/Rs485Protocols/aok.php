<?php

// class Aok
// {
    function getProtocol(string $command, $object, $value = null)
    {
        $headByte = ['9a'];

        $commands = [
            'open' => [
                'bytes' => ['0a', 'dd'],
                'value' => false,
                'isResponse' => false
            ],
            'close' => [
                'bytes' => ['0a', 'ee'],
                'value' => false,
                'isResponse' => false
            ],
            'stop' => [
                'bytes' => ['0a', 'cc'],
                'value' => false,
                'isResponse' => false
            ],
            'setPercent' => [
                'bytes' => ['dd'],
                'value' => true,
                'isResponse' => false
            ],
            'getStatus' => [
                'bytes' => ['cc', '00'],
                'value' => false,
                'isResponse' => true
            ],
            'setAddress' => [
                'bytes' => ['0a', 'aa'],
                'value' => false,
                'isResponse' => false
            ]
        ];

        $channelBytes = [
            1 => ['01', '00'],
            2 => ['02', '00'],
            3 => ['04', '00'],
            4 => ['08', '00'],
            5 => ['10', '00'],
            6 => ['20', '00'],
            7 => ['40', '00'],
            8 => ['80', '00'],
            9 => ['00', '01'],
            10 => ['00', '02'],
            11 => ['00', '04'],
            12 => ['00', '08'],
            13 => ['00', '10'],
            14 => ['00', '20'],
            15 => ['00', '40'],
            16 => ['00', '80']
        ];

        $hex_addr = dechex($object->address);
        if (strlen($hex_addr) < 2) $address[] = '0' . $hex_addr;
        else $address[] = $hex_addr;
        
        if ($command == 'getPercent') 
        {
            $command = 'getStatus';
            $targetByte = 7;
        }

        if ($commands[$command]['value'])
        {
            $value = dechex($value);
            if (strlen($value) < 2) $value = '0' . $value;
            $cmd = $commands[$command]['bytes'];
            array_push($cmd, $value);
        }
        else $cmd = $commands[$command]['bytes'];

        $packageArray = array_merge($headByte, $address, $channelBytes[$object->group], $cmd);
        
        $verifyCode[] = dechex(
            hexdec($packageArray[1])^
            hexdec($packageArray[2])^
            hexdec($packageArray[3])^
            hexdec($packageArray[4])^
            hexdec($packageArray[5])
        );
        $packageArray = array_merge($packageArray, $verifyCode);

        $protocol['cmd'] = implode('', $packageArray);
        $protocol['isResponse'] = $commands[$command]['isResponse'];

        if ($commands[$command]['isResponse'])
        {
            $protocol['targetByte'] = $targetByte;
        }

        return $protocol;
    }
// }
