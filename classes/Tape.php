<?php


class Tape extends Device
{
    private $port;
    private $address;
    private $type;
    private $status;

    function __construct($idObject)
    {
       //Определяем адрес контроллера ленты и порт, к которому подключена
       $sql = parent::$db->query("SELECT id, type, port, status FROM tape WHERE id_object = $idObject");

        if($sql->rowCount() > 0) {

            $tape = $sql->fetch(PDO::FETCH_OBJ);

            $this->port = $tape->port;
            $this->address = $tape->address;
            $this->type = $this->type;
            $this->status = $this->status;
        }
    }

    /**
     * Установка статуса для ленты вкл/выкл
     */
    function setStatus(string $status) {

    }

    /**
     * Установка значения для ленты RGB
     */
    function setValue(int $color, int $bright) {
        $modbus = new PhpSerialModbus;

        if ($this->port == 1 )  $pt = '/dev/ttyUSB0'; 
        else  $pt = '/dev/ttyUSB1'; 

        $modbus->deviceInit($pt, 9600, 'none', 8, 1, 'none');
        $modbus->deviceOpen();
        $modbus->debug = false;

        if ($this->type == 'RGB') {
            //Цвет и яркость для цветной ленты
            $modbus->sendQuery($this->address, 6, "07DE", $color);
            $modbus->sendQuery($this->address, 6, "07E0", $bright);
        } elseif ($this->type == 'w') {
            //Здесь должна быть яркость для белой ленты. Сделаем режимы RGB, W, RGB+W, 
        }
      
        $modbus->deviceClose();
    }
}