<?php

declare(strict_types=1);

namespace Zoox;


class Barrier extends Base
{
    private $path;
    private $callback;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function block(): bool
    {
        if (!$this->isExist($this->path)) {
            return $this->makePath($this->path);
        }
        return true;
    }

    public function remove(): bool
    {
        if ($this->isExist($this->path) === false) {
            return true;
        }
        return $this->deletePath($this->path);
    }

    public function isOpen(): bool
    {
        if ($this->isExist($this->path) === false) {
            return true;
        }
        return false;
    }

    public function process(callable $callable)
    {
        $this->callback = $callable;
        if ($this->isOpen()) {
            return \call_user_func_array($this->callback , [$this->path]);
        } else {
            $this->getZookeeper()->exists($this->path, [$this, 'callback']);
        }
    }

    public function callback(int $eventType, int $state, string $name)
    {
        if ($eventType === \Zookeeper::DELETED_EVENT) {
            \call_user_func_array($this->callback , [$this->path]);
        } else {
            $this->getZookeeper()->exists($this->path, [$this, 'callback']);
        }
    }
}