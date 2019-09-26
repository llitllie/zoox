<?php

declare(strict_types=1);

namespace Zoox;

class Watcher
{
    private static $increase = 0;
    private $enable = true;
    private $zk;
    public $id = 0;
    public $path = '';
    public $type = 0;
    public $callback;
    public $data;
    public $repeat = false;

    public const EXISTS_BASE = 5;
    public const EVENT_EXISTS_CREATE = 5;
    public const EVENT_EXISTS_DELETE = 10;
    public const EVENT_EXISTS_CHANGE = 15;
    public const GET_BASE = 7;
    public const EVENT_GET_CREATE = 7;
    public const EVENT_GET_DELETE = 14;
    public const EVENT_GET_CHANGE = 21;
    public const EVENT_CHILD_CREATE = 4;
    public const EVENT_CHILD_DELETE = 8;

    public function __construct(\Zookeeper $zk, string $path, int $type, callbale $callback)
    {
        $this->zk = $zk;
        $this->id = $this->increase++;
        $this->path = $path;
        $this->type = $type;
        $this->callback = $callback;
        $this->data = null;

        $this->register($this->path, $this->type);
    }

    public function setZookeeper(\Zookeeper $zk): void
    {
        $this->zk = $zk;
    }

    public function getZookeeper(): \Zookeeper
    {
        return $this->zk;
    }

    public function stop(): void
    {
        $this->enable = false;
    }

    private function register(string $path, int $type): void
    {
        if (\in_array($type, [self::EVENT_EXISTS_CREATE, self::EVENT_EXISTS_DELETE, self::EVENT_EXISTS_CHANGE])) {
            $this->getZookeeper()->exists($path, [$this, 'process']);
        } elseif (\in_array($type, [self::EVENT_GET_CREATE, self::EVENT_GET_DELETE, self::EVENT_GET_CHANGE])) {
            $this->getZookeeper()->get($path, [$this, 'process']);
        } elseif (\in_array($type, [self::EVENT_CHILD_CREATE, self::EVENT_CHILD_DELETE])) {
            $this->getZookeeper()->getChildren($path, [$this, 'process']);
        }
    }

    public function process(int $eventType, int $state, string $name)
    {
        if (self::EXISTS_BASE === $this->type / $eventType) {
            $event = $eventType * self::EXISTS_BASE;
            if ($event === $this->type) {
                $callback = $this->repeat ? [$this, 'process'] : null;
                $data = $this->getZooKeeper()->exists($name, $callback);
                $result = $this->callback($event, $state, $name, $data);

                return $result;
            }
            $this->getZookeeper()->exists($this->path, [$this, 'process']);
        } elseif (self::GET_BASE === $this->type / $eventType) {
            $event = $eventType * self::GET_BASE;
            if ($event === $this->type) {
                $callback = $this->repeat ? [$this, 'process'] : null;
                $data = $this->getZooKeeper()->get($name, $callback);
                $result = $this->callback($event, $state, $name, $data);

                return $result;
            }
            $this->getZookeeper()->get($this->path, [$this, 'process']);
        } elseif (\Zookeeper::CHILD_EVENT === $eventType) {
            //TODO detecting child changes
            echo 'child event '.PHP_EOL;
        }
    }

    public function callback($eventType, $state, $name, $data)
    {
        if (!$this->enable) {
            //slient
            return -1;
        }
        if (\is_callable($this->callback)) {
            try {
                //if CHANGED_EVENT return value
                //if CREATED_EVENT / DELETE_EVENT return 1/0
                //if CHILD_EVENT return child notify, let client check themselves
                $result = \call_user_func_array($this->callback, [$this, $eventType, $state, $name, $data]);

                return $result;
            } catch (\Exception $e) {
                throw new \Exception('event watcher callback faield: '.$e->getLine().', '.$e->getMessage());
            }
        }
        throw new \Exception('event watcher callback is not callbale');
    }
}
