<?php

class  Coroutine{
    private $enable;
    private $gen;

    public function wait(callable $callback)
    {
        $this->enable = 1;
        $callable = function() use ($callback) {
            while(true) {
                $data = yield;
                if ($this->enable) call_user_func_array($callback, [$data]);
            }
        };
        $this->gen = $callable();
    }

    public function notify($data = NULL) 
    {
        $this->gen->send($data);
    }

    public function stop()
    {
        $this->enable = 0;
    }
 }

 $co= new Coroutine();

 $co->wait(function ($data) {
     var_dump($data);
     echo "wake up1".PHP_EOL;
 });
 $co->notify();
 $co->stop();
 $co->notify();
 $co->wait(function ($data) {
    var_dump($data);
    echo "wake up2".PHP_EOL;
 });
 $co->notify('something');/**/
