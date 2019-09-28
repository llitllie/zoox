<?php

declare(strict_types=1);

namespace Zoox;

class Coroutine
{
    private $enable = true;
    private $gen;

    public function wait(callable $callback): void
    {
        $callable = function () use ($callback) {
            while (true) {
                $data = yield;
                if ($this->enable) {
                    \call_user_func_array($callback, [$data]);
                }
            }
        };
        $this->gen = $callable();
    }

    public function notify($data = null): void
    {
        $this->gen->send($data);
    }

    public function disable(): void
    {
        $this->enable = false;
    }
}
