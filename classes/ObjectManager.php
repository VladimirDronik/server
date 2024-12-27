<?php

class ObjectManager extends System
{
    public function __construct($objectId)
    {
        if(isset($objectId))
        {
            $sql = parent::$db->query(
                "SELECT `id`, `type`, `name`, `status` FROM `objects` WHERE `id` = $objectId"
            );
            if($sql->rowCount() > 0)
            {
                $this->object = $sql->fetch(PDO::FETCH_OBJ);
                $this->device = $this->getDevicebyType();
            }
        }
    }

    private function getDevicebyType()
    {
        if ($this->object->type == 'sensor') return $this->sensorBuilder();
        if ($this->object->type == 'regulator') return $this->regulatorBuilder();
        if ($this->object->type == 'tape') return $this->tapeBuilder();
        if ($this->object->type == 'meter') return $this->meterBuilder();
    }

    private function sensorBuilder()
    {
        $sql = parent::$db->query(
            "SELECT `name`, `value` FROM `sensors` WHERE `object_id` = {$this->object->id}"
        );
        if ($sql->rowCount() > 0)
        {
            while ($row = $sql->fetch(PDO::FETCH_ASSOC)) $props[$row['name']] = $row['value'];
            $this->device = (object)$props;
        }

        $sql = parent::$db->query(
            "SELECT `id`, `param`, `name`, `get_param`, `value`, `accuracy`,
            `units`, `graph`, `min_range`, `max_range`, `min_alarm`, `max_alarm`, `timestamp`
            FROM `sensors_params` WHERE `object_id` = {$this->object->id}"
        );
        if ($sql->rowCount() > 0)
        {
            while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
                $row['last_value'] = $row['value'];
                $param[$row['id']] = $row;
            }
            $this->device->params = $param;
        }
        return $this->device;
    }

    private function regulatorBuilder()
    {
        $sql = parent::$db->query(
            "SELECT `sensor_param_id`,`setpoint`, `hysteresis`, `lower_method`, `higher_method`,
            `fallback_method`, `min_setpoint`, `max_setpoint` FROM `regulators` WHERE `object_id` = {$this->object->id}"
        );
        if($sql->rowCount() > 0) return $sql->fetch(PDO::FETCH_OBJ);
    }

    private function tapeBuilder()
    {
        $sql = parent::$db->query(
            "SELECT `type`, `h` AS 'hue', `s` AS 'saturation', `v` AS 'value',
            `w` AS 'brightness', `cct`, `channel`, `controller_id` AS 'controller'
            FROM `tapes` WHERE `id_object` = {$this->object->id}"
        );
        if($sql->rowCount() > 0) return $sql->fetch(PDO::FETCH_OBJ);
    }
    
    private function daliBuilder()
    {
        $sql = parent::$db->query(
            "SELECT `id_object`, `address`, `is_group`, `dali_gateway`, `brightness`,
            `cct`, `status` FROM `dali_devices` WHERE `id_object` = {$this->object->id}"
        );
        if($sql->rowCount() > 0) return $sql->fetch(PDO::FETCH_OBJ);
    }

    private function meterBuilder()
    {
        $sql = parent::$db->query(
            "SELECT * FROM `meters` WHERE `object_id` = {$this->object->id}"
        );
        if($sql->rowCount() > 0) return $sql->fetch(PDO::FETCH_OBJ);
    }

    public function setStatus($status)
    {
        parent::$db->exec("UPDATE `objects` SET `status` = '$status' WHERE `id` = {$this->object->id}");
        $this->object->status = $status;
        return true;
    }

    public static function getObjectIdByMethod($methodId)
    {
        return parent::$db->query("SELECT `id_object` FROM `methods` WHERE `id` = $methodId")
            ->fetchColumn();
    }

    public static function getAllMeters()
    {
        $sql = parent::$db->query(
            "SELECT `id` FROM `objects` WHERE `type` = 'meter'"
        );
        if($sql->rowCount() > 0) return $sql->fetchAll(PDO::FETCH_COLUMN, 0);
        else return null;
    }

    public static function getAll(string $objectType)
    {
        $sql = parent::$db->query(
            "SELECT `id` FROM `objects` WHERE `type` = '$objectType'"
        );
        if($sql->rowCount() > 0) return $sql->fetchAll(PDO::FETCH_COLUMN, 0);
        else return [];
    }
}

