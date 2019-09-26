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
        if (false === $this->isExist($this->path)) {
            return true;
        }

        return $this->deletePath($this->path);
    }

    public function isOpen(): bool
    {
        if (false === $this->isExist($this->path)) {
            return true;
        }

        return false;
    }

    public function pass(callable $callable)
    {
        $this->callback = $callable;
        if ($this->isOpen()) {
            return \call_user_func_array($this->callback, [$this->path]);
        }
        $this->getZookeeper()->exists($this->path, [$this, 'callback']);
    }

    public function callback(int $eventType, int $state, string $name): void
    {
        if (\Zookeeper::DELETED_EVENT === $eventType) {
            \call_user_func_array($this->callback, [$this->path]);
        } else {
            $this->getZookeeper()->exists($this->path, [$this, 'callback']);
        }
    }
}
