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
       $sql = parent::$db->query("SELECT tapes.id AS id, tapes.type AS type, tapes.status, h, s, v, w FROM tapes 
                                    WHERE tapes.id_object = $idObject");

        if($sql->rowCount() > 0) {

            $tape = $sql->fetch(PDO::FETCH_OBJ);

            $this->idObject = $idObject;
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

        //обработка включения ленты с выбором цвета и яркости (если пользователь выбрал цвет в приложении)
        if ($status == "on") {
            if ($this->type == 'RGB' || $this->type == 'RGBW') {
                //Цвет и яркость для цветной ленты
                $method = Objects::getMethodByAlias($this->idObject, "ch123_color");
                Action::runAction($method->id, null, null, $color);

                $method = Objects::getMethodByAlias($this->idObject, "ch123_shade");
                Action::runAction($method->id, null, null, $shade);

                $method = Objects::getMethodByAlias($this->idObject, "ch123_bright");
                Action::runAction($method->id, null, null, $bright);

                $method = Objects::getMethodByAlias($this->idObject, "ch123_enable");
                if ($bright <= 1) {
                    Action::runAction($method->id, null, null, false);
                } else {
                    Action::runAction($method->id, null, null, true);
                }
                
            } 
            //обработка яркости для белой ленты
            if ($this->type == 'RGBW') {    
                
                $method = Objects::getMethodByAlias($this->idObject, "ch4_bright");
                Action::runAction($method->id, null, null, $wbright);

                $method = Objects::getMethodByAlias($this->idObject, "ch4_enable");
                if ($wbright <= 1) {
                    Action::runAction($method->id, null, null, false);
                } else {
                    Action::runAction($method->id, null, null, true);
                }
            } 
            elseif ($this->type == 'W') {
                //яркость для белой ленты, висящей отдельно на 4м порту
                $method = Objects::getMethodByAlias($this->idObject, "ch4_bright");
                Action::runAction($method->id, null, null, $wbright);

                $method = Objects::getMethodByAlias($this->idObject, "ch4_enable");
                if ($wbright <= 1) {
                    Action::runAction($method->id, null, null, false);
                } else {
                    Action::runAction($method->id, null, null, true);
                }
            } 

        

        }  else {
            //обработка выключения ленты
            if ($this->type == 'RGB' || $this->type == 'RGBW') {
                $method = Objects::getMethodByAlias($this->idObject, "ch123_enable");
                Action::runAction($method->id, null, null, false);
                
                $method = Objects::getMethodByAlias($this->idObject, "ch4_enable");
                Action::runAction($method->id, null, null, false);
            } elseif ($this->type == 'w') {
                $method = Objects::getMethodByAlias($this->idObject, "ch4_enable");
                Action::runAction($method->id, null, null, false);
            }  
    }
    
    }
}