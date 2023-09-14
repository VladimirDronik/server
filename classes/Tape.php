<?php


class Tape extends Device
{
    private $idObject;
    private $port;
    private $address;
    private $type;
    private $status;
    private $h;
    private $s;
    private $v;

    function __construct($idObject)
    {
       //Определяем адрес контроллера ленты и порт, к которому подключена
       $sql = parent::$db->query("SELECT tapes.id AS id, tapes.type AS type, ip_address AS address, port, tapes.status, h, s, v FROM tapes 
                                    INNER JOIN devices ON devices.id = tapes.id_controller 
                                    WHERE tapes.id_object = $idObject");

        if($sql->rowCount() > 0) {

            $tape = $sql->fetch(PDO::FETCH_OBJ);

            $this->idObject = $idObject;
            $this->port = $tape->port;
            $this->address = $tape->address;
            $this->type = $tape->type;
            $this->status = $tape->status;
            $this->h = $tape->h;
            $this->v = $tape->v;
        }
    }

    /**
     * Установка статуса для ленты вкл/выкл
     */
    function setStatus(int $color, int $bright, string $status) {
               
        
          // если значения цвета и яркости не пришли от приложения, значит берем предыдущие значения, которые были в БД
          // иначе берем новые значения, которые пришли от приложения
        if ($color==0 && $bright==0) {
           $color = $this->h; 
           $bright = $this->v; 
        } 
        
        parent::$db->query("UPDATE tapes SET status = '$status', h = $color, v = $bright WHERE id_object = $this->idObject");

        $this->setValue($color, $bright, $status);

    }

    /**
     * Установка значения для ленты RGB
     */
    function setValue(int $color, int $bright, string $status) {
        $modbus = new PhpSerialModbus;

        if ($this->port == 0 ) $pt = '/dev/ttyUSB0';
        else $pt = '/dev/ttyUSB1'; 

        $modbus->deviceInit($pt, 9600, 'none', 8, 1, 'none');
        $modbus->deviceOpen();
        $modbus->debug = true;

        //обработка включения ленты
        if ($status == "on") {
            if ($this->type == 'RGB') {
                //Цвет и яркость для цветной ленты
                $modbus->sendQuery($this->address, 6, "07DE", dechex($color));
                $modbus->sendQuery($this->address, 6, "07E0", dechex($bright));
                $modbus->sendQuery($this->address, 5, "0009", "FF00");
            } elseif ($this->type == 'w') {
                //яркость для белой ленты
                $modbus->sendQuery($this->address, 6, "07D3", dechex($bright));
                $modbus->sendQuery($this->address, 5, "0003", "FF00");
            }
        } else {
            //обработка выключения ленты
            if ($this->type == 'RGB') {
            $modbus->sendQuery($this->address, 5, "0009", "0000");
            } elseif ($this->type == 'w') {
            $modbus->sendQuery($this->address, 5, "0003", "0000");
            }

        }
            
        $modbus->deviceClose();

    }
}