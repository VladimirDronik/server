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
    private $w;

    function __construct($idObject)
    {
       //Определяем адрес контроллера ленты и порт, к которому подключена
       $sql = parent::$db->query("SELECT tapes.id AS id, tapes.type AS type, ip_address AS address, port, tapes.status, h, s, v, w FROM tapes 
                                    INNER JOIN ports ON ports.object=tapes.id_object
                                    INNER JOIN devices ON devices.id = ports.id_device 
                                    WHERE tapes.id_object = $idObject");

        if($sql->rowCount() > 0) {

            $tape = $sql->fetch(PDO::FETCH_OBJ);

            $this->idObject = $idObject;
            $this->port = $tape->port;
            $this->address = $tape->address;
            $this->type = $tape->type;
            $this->status = $tape->status;
            $this->h = $tape->h;
            $this->s = $tape->s;
            $this->v = $tape->v;
            $this->w = $tape->w;
        }
    }

    /**
     * Установка статуса для ленты вкл/выкл
     */
    function setStatus(int $color, int $shade, int $bright, int $wbright, string $status) {
               
    
          // если значения цвета и яркости не пришли от приложения, значит берем предыдущие значения, которые были в БД
          // иначе берем новые значения, которые пришли от приложения
        if ($color == 0 && $bright == 0 && $shade == 0) {
           $color = $this->h; 
           $bright = $this->v; 
           $shade = $this->s;
        } 
        
        //Если значение яркости белого не пришло, значит берем предыдущее значение из БД
        if ($wbright == 0) {
            $wbright = $this->w;
        }

        parent::$db->query("UPDATE tapes 
                            SET status = '$status', h = $color, s = $shade, v = $bright, w = $wbright 
                            WHERE id_object = $this->idObject");

        $this->setValue($color, $shade, $bright, $wbright, $status);

    }

    /**
     * Установка значения для ленты RGB
     */
    function setValue(int $color, int $shade, int $bright, int $wbright, string $status) {
        $modbus = new PhpSerialModbus;

        if ($this->port == 0 ) $pt = '/dev/ttyUSB0';
        else $pt = '/dev/ttyUSB1'; 

        $modbus->deviceInit($pt, 9600, 'none', 8, 1, 'none');
        $modbus->deviceOpen();
        $modbus->debug = true;

        if ($bright <= 1) $cmd = "0000";
        else $cmd = "FF00";

        if ($wbright <= 1) $wcmd = "0000";
        else $wcmd = "FF00";

        //обработка включения ленты с выбором цвета и яркости
        if ($status == "on") {
            if ($this->type == 'RGB' || $this->type == 'RGBW') {
                //Цвет и яркость для цветной ленты
                $modbus->sendQuery($this->address, 6, "07DE", dechex($color));
                $modbus->sendQuery($this->address, 6, "07DF", dechex($shade));
                $modbus->sendQuery($this->address, 6, "07E0", dechex($bright));
                $modbus->sendQuery($this->address, 5, "0009", $cmd);
            }
            if ($this->type == 'RGBW') {
                $modbus->sendQuery($this->address, 6, "07D3", dechex($wbright));
                $modbus->sendQuery($this->address, 5, "0003", $wcmd);
            } 
            elseif ($this->type == 'W') {
                //яркость для белой ленты
                $modbus->sendQuery($this->address, 6, "07D3", dechex($wbright));
                $modbus->sendQuery($this->address, 5, "0003", $wcmd);
            } 

        

        }  else {
            //обработка выключения ленты
            if ($this->type == 'RGB' || $this->type == 'RGBW') {
                $modbus->sendQuery($this->address, 5, "0009", "0000");
                $modbus->sendQuery($this->address, 5, "0003", "0000");
            } elseif ($this->type == 'w') {
                $modbus->sendQuery($this->address, 5, "0003", "0000");
            }  
    }

    $modbus->deviceClose();
    
    }
}