<?php
/**
 * Скрипт запускает действия в зависимости от того, в каком режиме находится датчик движения
 */

require_once '../include.php';

$cnt_light = 10; //интервал включения освещения, сек.


$script = new Scripts();
$object = new Objects();


    //проверяем в каком состоянии находится датчик сейчас
    $object->select($argv[1]);
    $st = $object->getPortState();
    $status = explode('/',$st);

    //Включаем свет
    if ($status[0]=='ON'){

        $motionsensor = new Motionsensor($argv[1]);

        changeAction($motionsensor,'ON');

        while ($cnt<$cnt_light){

            //Проверяем, может датчик сработал повторно
            $st = $object->getPortState();
            $status = explode('/',$st);

            //Если повторно, то сбрасываем счетчик времени
            if ($status[0]=='ON') $cnt=0;

            sleep(1);

            $cnt++;

        }

        changeAction($motionsensor,'OFF');
    }



    ////////////////////////////////
    function changeAction(Motionsensor $motionsensor, $state)
    {

        //Если включен режим охраны
        if((System::readSetting('guard_mode') == 'true') && $motionsensor->method_guard)
            Action::runAction($motionsensor->method_guard, null, $motionsensor->id, $state);

        //Если включен режим эко
        if((System::readSetting('eco_mode') == 'true') && $motionsensor->method_eco)
            Action::runAction($motionsensor->method_eco, null, $motionsensor->id, $state);

        //Если включен режим дневной, ночной, утро, вечер
        if($motionsensor->method_normal || $motionsensor->method_night ||
            $motionsensor->method_evening || $motionsensor->method_morning)  {

            if (System::readSetting('light_mode') == 'day')
                $actString = "$motionsensor->method_normal";
            else
                $actString = '$motionsensor->method_'.System::readSetting('light_mode');

            Action::runAction($actString, null, $motionsensor->id, $state);

        }

        //Если у датчика есть режим сработки по светостату, то проверяем значения
        if($motionsensor->method_light) {

            $lightstatValue = Lightstats::getValueFromDB($motionsensor->lightstat);

            if($motionsensor->equality == '>') {

                if($motionsensor->lightvalue > $lightstatValue)
                    Action::runAction($motionsensor-> method_light, null, $motionsensor->id, $state);

            } else {

                if($motionsensor->lightvalue < $lightstatValue)
                    Action::runAction($motionsensor-> method_light, null, $argv[1], $state);
            }
        }

    }



