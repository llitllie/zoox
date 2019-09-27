<?php

declare(strict_types=1);

namespace Zoox;

class Coroutine {
    private $enable = 1;
    private $gen;

    public function wait(callable $callback)
    {
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

 }