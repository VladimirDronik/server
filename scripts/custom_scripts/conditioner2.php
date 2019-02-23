<?php

/**
 * Скрипт включения или выключения виртуального кондиционера
 */


require_once '../../include.php';



//Отправляем данные монитору демостенда
//demostand::send('{"events":{"window": "off", "door": "off", "gostin": "on", "elect_on":"on"}, "info": {"home_lock_status": "on"}, "status": ["light_off", "elect_on", "normal_warm", "house_unlocked"]}');

