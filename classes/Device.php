<?php

/**
 * Class Device - класс устройств
 */


class Device extends System
{
    const ALICE_AC_FAN_MODES_MAPPING = [
        'auto' => 'auto',
        'turbo' => 'turbo',
        'silent' => 'quiet',
        'low' => 'low',
        'medium' => 'medium',
        'high' => 'high',
        1 => 'one',
        2 => 'two',
        3 => 'three',
        4 => 'four',
        5 => 'five',
        6 => 'six'
    ];

    const ALICE_AC_MODES_MAPPING = [
        'auto' => 'auto',
        'cool' => 'cool',
        'heat' => 'heat',
        'dry' => 'dry',
        'fan' => 'fan_only'
    ];

    const ALICE_UNITS_MAPPING = [
        'celsius' => 'unit.temperature.celsius',
        'percent' => 'unit.percent',
        'ppm' => 'unit.ppm',
        'atm' => 'unit.pressure.atm',
        'pascal' => 'unit.pressure.pascal',
        'bar' => 'unit.pressure.bar',
        'mmhg' => 'unit.pressure.mmhg',
        'lux' => 'unit.illumination.lux',
        'ampere' => 'unit.ampere',
        'kilowatt_hour' => 'unit.kilowatt_hour',
        'cubic_meter' => 'unit.cubic_meter',
        'gigacalorie' => 'unit.gigacalorie',
        'mcg_m3'  => 'unit.density.mcg_m3',
        'watt' => 'unit.watt',
        'kelvin' => 'unit.temperature.kelvin',
        'volt' => 'unit.volt',
        NULL => 'meter'
    ];

    private $devicesCapabilitiesOn_off = [
        'type' => 'devices.capabilities.on_off',
        'parameters' => [
            'instance' => 'on'
        ],
        'retrievable' => true,
        'reportable' => true
    ];


    /**
     * Определение на каком контроллере находится устройство
     *
     * */
    static public function getDevice($idObject)
    {
        $sql = parent::$db->query("SELECT `id_device` FROM `ports` WHERE `object` = $idObject");
        return  $sql->fetch(PDO::FETCH_OBJ)->id_device;
    }

    static public function getNumPort($idPort)
    {
        $sql = parent::$db->query("SELECT `num_port` FROM `ports` WHERE `id` = $idPort");
        return  $sql->fetch(PDO::FETCH_OBJ)->num_port;
    }

    /**
     * Определение типа порта для устройств i2c
     */
    static public function getPortType($idObject)
    {

    }

    /**
     * Проверка устройства на доступность
     *
     * @param $table
     */
    static public function checkAvailible($table)
    {

        $sql = parent::$db->query("SELECT id, 
                                       ip_address AS host,
                                       description,
                                       active
                                       FROM $table
                                  ");

        if($sql->rowCount() > 0) {
            $devices = $sql->fetchAll(PDO::FETCH_OBJ);

            foreach ($devices AS $device) {

                //Если устройство недоступно
                if (!parent::ping($device->host)) {

                    //Если ранее было доступно, то изменяем состояние
                    if ($device->active == 1) {

                        //Меняем статус устройства
                        self::$db->query("UPDATE $table SET `active` = 0
                                             WHERE `id`=$device->id");

                        //Записываем в лог информацию
                        parent::addLog('error', 'Device "'.$device->description.'" ('.$device->host.') is not available', 'controller');

                        //Отправка сообщения пользователю о том, что устройство не доступно
                        Messages::send(1, 'Устройство "'.$device->description.'" ('.$device->host.') недоступно');
                    }

                } else {

                    //Если ранее было недоступно, то меняем состояние
                    if ($device->active == 0) {

                        //Меняем статус устройства
                        self::$db->query("UPDATE $table SET `active` = 1
                                         WHERE `id`=$device->id");

                        //Записываем в лог информацию
                        parent::addLog('Messages', "Device  {$device->description} ({$device->host})  is available", 'controller');

                        //Отправка сообщения пользователю о том, что устройство снова доступно
                        Messages::send(1, 'Устройство "' . $device->description . '" (' . $device->host . ') снова доступно');
                    }
                }
            }
        }
    }

    /**
     * Получение названия типа устройства
     */
    public static function getTypeName($idType)
    {

        $sql = parent::$db->query("SELECT `name` FROM devtypes WHERE `id` = $idType");
        $device = $sql->fetch(PDO::FETCH_OBJ);

        return  $device->name;

    }

    /**
     * Извлекаем из БД все данные об устройствах и отдаем их на коллектор (для Алисы)
     */
    public function getDevicesForCollector()
    {

        $sql = parent::$db->query("SELECT objects.id AS id, alice_devices.name AS name, objects.type AS type, 
                                      rooms.name AS room, objects.status AS status  FROM `alice_devices` 
                                      INNER JOIN objects ON alice_devices.id_object = objects.id 
                                      LEFT JOIN rooms ON alice_devices.room = rooms.id
                                      WHERE alice_devices.active = 1");

        if($sql->rowCount() > 0) {
            $devices = $sql->fetchAll(PDO::FETCH_OBJ);

            foreach ($devices AS $device) {

                $attributes = $this->deviceAttributesToAlice($device->type, $device->id);

                $name = $device->name;
                $deviceId = $device->id;
                $type = $attributes['type'];
                $model = 'to.srv01';
                $manufacturer = 'TouchOn';
                $capabilities = $attributes['capabilities'];
                $properties = $attributes['properties'];
                $room = $device->room;

                $devicesArr[$deviceId] = array(
                    'name' => $name,
                    'type' => $type,
                    'model' => $model,
                    'manufacturer' => $manufacturer,
                    'capabilities' => $capabilities,
                    'properties' => $properties,
                    'room' => $room
                );

            }

        }

        return json_encode(array('mode' => 'get_devices', 'devices' => $devicesArr));
    }


    /**
     * Формирование атрибутов для устройства Алисы в зависимости от типа
     */
    public function deviceAttributesToAlice($type, $objectId)
    {
        switch ($type) {
            case 'lamp':
                $type = 'devices.types.light';
                $capabilities = [ $this->devicesCapabilitiesOn_off ];
                $properties = null;
                break;

            case 'socket':
            case 'relay':
            case 'virtual':
                $type = 'devices.types.socket';
                $capabilities = [ $this->devicesCapabilitiesOn_off ];
                $properties = null;
                break;

            case 'lock':
                $type = 'devices.types.openable';
                $capabilities = [ $this->devicesCapabilitiesOn_off ];
                $properties = null;
                break;

            case 'dimmer':
                $type = 'devices.types.light';
                $capabilities = [
                    $this->devicesCapabilitiesOn_off,
                    [
                        'type' => 'devices.capabilities.range',
                        'parameters' => [
                            'unit' => 'unit.percent',
                            'range' => [
                                'min' => 1,
                                'max' => 100,
                                'precision' => 1
                            ],
                            'instance' => 'brightness'
                        ],
                        'retrievable' => true,
                        'reportable' => true
                    ]
                ];
                $properties = null;
                break;

            case 'tape':
                $tape = new Tape($objectId);
                $type = 'devices.types.light.strip';
                $capabilities = [
                    $this->devicesCapabilitiesOn_off,
                    [
                        'type' => 'devices.capabilities.range',
                        'parameters' => [
                            'unit' => 'unit.percent',
                            'range' => [
                                'min' => 1,
                                'max' => 100,
                                'precision' => 1
                            ],
                            'instance' => 'brightness'
                        ],
                        'retrievable' => true,
                        'reportable' => true
                    ]
                ];
                if ($tape->getType() == 'RGB') {

                    $capabilities[] = [
                        'type' => 'devices.capabilities.color_setting',
                        'retrievable' => true,
                        'reportable' => true,
                        'parameters' => [
                            'color_model' => 'hsv',
                            'instance' => 'hsv'
                        ]
                    ];
                }

                if ($tape->getType() == 'RGB') {

                    $capabilities[] = [
                        'type' => 'devices.capabilities.color_setting',
                        'retrievable' => true,
                        'reportable' => true,
                        'parameters' => [
                                'temperature_k' => [
                                    "min" => 0,
                                    "max" => 100
                                ],
                                'instance' => 'temperature_k'
                            ]
                    ];
                }

                $properties = null;
                break;

                case 'dali':
                    $tape = new Dali($objectId);
                    $type = 'devices.types.light';
                    $capabilities = [
                        $this->devicesCapabilitiesOn_off,
                        [
                            'type' => 'devices.capabilities.range',
                            'parameters' => [
                                'unit' => 'unit.percent',
                                'range' => [
                                    'min' => 1,
                                    'max' => 100,
                                    'precision' => 1
                                ],
                                'instance' => 'brightness'
                            ],
                            'retrievable' => true,
                            'reportable' => true
                        ],
                        [
                            'type' => 'devices.capabilities.color_setting',
                            'retrievable' => true,
                            'reportable' => true,
                            'parameters' => [
                                'temperature_k' => [
                                    "min" => 1000,
                                    "max" => 10000
                                ],
                                'instance' => 'temperature_k'
                            ]
                        ]
                    ];
                    $properties = null;
                    break;

            case 'curtain':
                $curtain = new Curtain($objectId);
                $type = 'devices.types.openable.curtain';
                $capabilities = [ $this->devicesCapabilitiesOn_off ];
                if ($curtain->curtain->place == 'rs485')
                    $capabilities[] = [
                        'type' => 'devices.capabilities.range',
                        'parameters' => [
                            'unit' => 'unit.percent',
                            'range' => [
                                'min' => 0,
                                'max' => 100,
                                'precision' => 1
                            ],
                            'instance' => 'open'
                        ],
                        'retrievable' => true,
                        'reportable' => true
                    ];
                $properties = null;
                break;
            
            case 'conditioner':
                $ac = new Conditioner($objectId);
                $type = 'devices.types.thermostat.ac';
                foreach ($ac->getFans() as $key => $value) {
                    $fanModes[] = [ 'value' => self::ALICE_AC_FAN_MODES_MAPPING[$key] ];
                }
                foreach ($ac->getModes() as $key => $value) {
                    $modes[] = [ 'value' => self::ALICE_AC_MODES_MAPPING[$key] ];
                }
                $capabilities = [
                    $this->devicesCapabilitiesOn_off,
                    [
                        'type' => 'devices.capabilities.range',
                        'parameters' => [
                        'unit' => 'unit.temperature.celsius',
                            'range' => [
                                'min' => $ac->getMinMaxTemps()['min'],
                                'max' => $ac->getMinMaxTemps()['max'],
                                'precision' => 1
                            ],
                            'instance' => 'temperature'
                        ],
                        'retrievable' => true,
                        'reportable' => true
                    ],
                    [
                        'type' => 'devices.capabilities.mode',
                        'parameters' => [
                            'instance' => 'fan_speed',
                            'modes' => $fanModes
                        ],
                        'retrievable' => true,
                        'reportable' => true
                    ],
                    [
                        'type' => 'devices.capabilities.mode',
                        'parameters' => [
                            'instance' => 'thermostat',
                            'modes' => $modes
                        ],
                        'retrievable' => true,
                        'reportable' => true
                    ]
                ];
                $properties = null;
                break;
            
            case 'climate_sensor':
                $type = 'devices.types.sensor.climate';
                $capabilities = null;
                $properties = [
                    [
                        'type' => 'devices.properties.float',
                        'parameters' => [
                            'instance' => 'temperature',
                            'unit' => 'unit.temperature.celsius'
                        ],
                        'retrievable' => true,
                        'reportable' => true
                    ]
                ];
                break;
            
            // case 'termostat':
            //     $type = 'devices.types.thermostat';
            //     $capabilities = [
            //         [
            //             'type' => 'devices.capabilities.range',
            //             'parameters' => [
            //                 'instance' => 'temperature',
            //                 'unit' => 'unit.temperature.celsius'
            //             ],
            //             'retrievable' => true,
            //             'reportable' => true
            //         ]
            //     ];
            //     $properties = null;
            //     break;

            case 'sensor':
                $sensor = new Sensor($objectId);
                $type = 'devices.types.sensor';
                $capabilities = null;
                
                foreach ($sensor->device->params as $param)
                {
                    $properties[] = [
                        'type' => 'devices.properties.float',
                        'retrievable' => true,
                        'reportable' => true,
                        'parameters' => [
                            'instance' => $param['param'],
                            'unit' => self::ALICE_UNITS_MAPPING[$param['units']]
                        ]
                    ];
                }
                break;
            
            case 'regulator':
                $regulator = new Regulator($objectId);
                $sensor = new Sensor(Sensor::getSensorObjectIdByParamId($regulator->device->sensor_param_id));
                $type = 'devices.types.thermostat';

                if ($sensor->device->params[$regulator->device->sensor_param_id]['param'] == 'temperature') $precision = 0.5;
                elseif ($sensor->device->params[$regulator->device->sensor_param_id]['param'] == 'humidity') $precision = 10;
                else $precision = 1;

                $capabilities = [
                    $this->devicesCapabilitiesOn_off,
                    [
                        'type' => 'devices.capabilities.range',
                        'parameters' => [
                        'unit' => self::ALICE_UNITS_MAPPING[$sensor->device->params[$regulator->device->sensor_param_id]['units']],
                            'range' => [
                                'min' => $regulator->device->min_setpoint,
                                'max' => $regulator->device->max_setpoint,
                                'precision' => $precision
                            ],
                            'instance' => $sensor->device->params[$regulator->device->sensor_param_id]['param']
                        ],
                        'retrievable' => true,
                        'reportable' => true
                    ],
                ];
                $properties[] = [
                    'type' => 'devices.properties.float',
                    'retrievable' => true,
                    'reportable' => true,
                    'parameters' => [
                        'instance' => $sensor->device->params[$regulator->device->sensor_param_id]['param'],
                        'unit' => self::ALICE_UNITS_MAPPING[$sensor->device->params[$regulator->device->sensor_param_id]['units']]
                    ]
                ];
        }

        return [
            'type' => $type, 
            'capabilities' => json_encode($capabilities),
            'properties' => json_encode($properties)
        ];
    }

    /**
     * Получение статуса устройства
     * @param $idDevice
     * @return string
     */
    public function getStatus($idDevice)
    {

        $sql = parent::$db->query("SELECT `status`, `type` FROM objects WHERE id = $idDevice");
        $device = $sql->fetch(PDO::FETCH_OBJ);

        if($device->status == 'on')
            $on = 1;
        elseif ($device->status == 'off')
            $on = 0;
        else
            $on = null;

        if (
            ($device->type == 'lamp') || 
            ($device->type == 'relay') || 
            ($device->type == 'socket')
            )
            $status = array('on' => $on);

        if ($device->type == 'dimmer') {
            $dimmer = new Dimmer($idDevice);

            $status = [
                'on' => $on,
                'brightness' => $dimmer->getValue()
            ];
        }

        if ($device->type == 'curtain') {
            if($device->status == 'close') $state = 0;
            else $state = 1;
            $curtain = new Curtain($idDevice);
            $status = [
                'on' => $state,
                'open' => $curtain->curtain->percent
            ];
        }

        if ($device->type == 'tape') {
            $tape = new Tape($idDevice);
            $status = [
                'on' => $on,
                'brightness' => $tape->getBrightness()
            ];
            if ($tape->getType() == 'RGB') {
                $color = $tape->getColor();
                $status['hsv'] = [
                    'h' => $color->h,
                    's' => $color->s,
                    'v' => $color->v
                ];
            }
            if ($tape->getType() == 'CCT') {
                $status['temperature_k'] = $tape->getCct();
            }
        }

        if ($device->type == 'dali') {
            $dali = new Dali($idDevice);
            $status = [
                'on' => $on,
                'brightness' => $dali->getBrightness(),
                'temperature_k' => $dali->getColorTemperature()
            ];
        }

        if ($device->type == 'conditioner') {
            $ac = new Conditioner($idDevice);
            $status = [
                'on' => $on,
                'temperature' => $ac->getAcTemperature(),
                'fan_speed' => self::ALICE_AC_FAN_MODES_MAPPING[$ac->getAcFanSpeed()],
                'thermostat' => self::ALICE_AC_MODES_MAPPING[$ac->getAcMode()]
            ];
        }

        if ($device->type == 'termostat') {
            $sensor = new Thermostats($idDevice);
            $status = [
                'temperature' => $sensor->getCurrentTemperature()
            ];
        }

        if ($device->type == 'sensor') {
            $sensor = new Sensor($idDevice);
            foreach ($sensor->device->params as $param)
                $status[$param['param']] = $param['value'];
        }

        if ($device->type == 'regulator') {
            $regulator = new Regulator($idDevice);
            $sensor = new Sensor(Sensor::getSensorObjectIdByParamId($regulator->device->sensor_param_id));
            $status = [
                'on' => $on,
                $sensor->device->params[$regulator->device->sensor_param_id]['param']
                    => $regulator->device->setpoint,
                $sensor->device->params[$regulator->device->sensor_param_id]['param'].'_prop'
                    => $sensor->device->params[$regulator->device->sensor_param_id]['value']
            ];
        }


        return json_encode(array('mode' => 'get_status', 'status' => $status));
    }

    public static function aliceCallbackState($objectId, $capabilities, $properties) {
        $ydrHost = 'https://server1.touchon.tech';
        $ydrScript = 'ydr.php';

        $sql = parent::$db->query(
            "SELECT `id_object` FROM `alice_devices` WHERE `id_object` = $objectId AND `active` = 1"
        );
        if ($sql->rowCount() > 0) {
            $sql = parent::$db->query(" SELECT `value`
                                        FROM `settings`
                                        WHERE `name` = 'server_id'");
            $serverUid = $sql->fetch(PDO::FETCH_OBJ)->value;
            $queryString = "$ydrHost/$ydrScript?suid=$serverUid&object_id=$objectId";
            echo (new datetime())->format('Y-m-d H:i:s.v') . " [INFO] Yandex Alice callback request for object ID $objectId" . PHP_EOL;
            if (isset($capabilities)) $queryString .= "&capabilities=" . json_encode($capabilities);
            if (isset($properties)) $queryString .= "&properties=" . json_encode($properties, JSON_PRESERVE_ZERO_FRACTION);
            file_get_contents($queryString);
        }
    }
}