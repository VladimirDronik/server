<?php
/**
 * Скрипт запускает действия в зависимости от того, в каком режиме находится датчик движения
 */

require_once '../include.php';

$motionsensor = new Motionsensor($argv[1]);
execAction($motionsensor);


////////////////////////////////
function execAction($motionsensor)
{
    // var_dump ($motionsensor->method_guard);
    //Если включен режим охраны
    if((System::readSetting('guard_mode') == 'true') && $motionsensor->method_guard)
        Action::runAction($motionsensor->method_guard, 'device', $motionsensor->id_object, null, false);

    //Если включен режим эко
    if((System::readSetting('eco_mode') == 'true') && $motionsensor->method_eco)
        Action::runAction($motionsensor->method_eco, 'device', $motionsensor->id_object, null, false);

    //Если включен режим дневной, ночной, утро, вечер
    if($motionsensor->method_normal || $motionsensor->method_night ||
        $motionsensor->method_evening || $motionsensor->method_morning)
    {
        if (System::readSetting('light_mode') == 'day')
            $actString = "$motionsensor->method_normal";
        else
            $actString = '$motionsensor->method_'.System::readSetting('light_mode');
            Action::runAction($actString, 'device', $motionsensor->id_object, null, false);
    }

    //Если у датчика есть режим сработки по светостату, то проверяем значения
    if($motionsensor->method_light) 
    {
        $sql = System::$db->query("SELECT `id_object` FROM lightstats WHERE id = " . $motionsensor->lightstat);
        $lightstatObjectId = $sql->fetch(PDO::FETCH_OBJ);
        $lightstatValue = Lightstats::getValueFromDB($lightstatObjectId->id_object);

        if($motionsensor->equality == '<')
        {
            if($motionsensor->lightvalue > $lightstatValue)
                Action::runAction($motionsensor-> method_light, 'device', $motionsensor->id_object, null, false);
        } 
        else 
        {
            if($motionsensor->lightvalue < $lightstatValue)
                Action::runAction($motionsensor-> method_light, 'device', $motionsensor->id_object, null, false);
        }
    }
}



