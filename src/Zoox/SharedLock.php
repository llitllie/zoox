<?php

declare(strict_types=1);

namespace Zoox;

class SharedLock extends Base
{
    private $path;
    private $readCallback;
    private $writeCallback;
    private $readNode;
    private $writeNode;

    public const TYPE_READ = 1;
    public const TYPE_WRITE = 2;

    public function __construct(string $path)
    {
        //every object should have an uuid here
        $this->path = $path;
    }

    public function read(callable $callback): void
    {
        if ($this->readNode) {
            throw new ZooxExceptioin('already request a read lock');
        }
        $this->readCallback = $callback;

        if (!$this->isExist($this->path)) {
            $this->makePath($this->path);
        }
        $acls = [
            [
                'perms' => \Zookeeper::PERM_ALL,
                'scheme' => 'world',
                'id' => 'anyone',
            ],
        ];

        $path = $this->path.'/'.self::TYPE_READ.'-';
        $this->readNode = $this->getZookeeper()->create($path, '', $acls, \Zookeeper::EPHEMERAL | \Zookeeper::SEQUENCE);
        $this->readNode = \str_replace($this->path.'/', '', $this->readNode);

        $this->readCallback(\Zookeeper::CREATED_EVENT, \Zookeeper::CONNECTED_STATE, $this->path);
    }

    public function readCallback(int $eventType, int $state, string $name): void
    {
        $children = $this->getZookeeper()->getChildren($this->path);
        if (empty($children)) {
            throw new ZooxExceptioin('no child in path '.$this->path);
        }
        $min = null;
        $read = true;
        [$type, $sequence] = \explode('-', $this->readNode);
        foreach ($children as $child) {
            [$childType, $childSequence] = \explode('-', $child);
            if ($sequence > $childSequence) {
                if (self::TYPE_WRITE === (int) $childType) {
                    $min = $child;
                    $read = false;
                    break;
                }
            }
        }
        if ($read) {
            \call_user_func_array($this->readCallback, [$this->path]);
            //delete myself to accelerate
            $this->deletePath($this->path.'/'.$this->readNode);
            $this->readNode = null;
        } else {
            //wait
            $this->getZookeeper()->exists($this->path.'/'.$min, [$this, 'readCallback']);
        }
    }

    public function write(callable $callback): void
    {
        if ($this->writeNode) {
            throw new ZooxExceptioin('already requst a write lock');
        }
        $this->writeCallback = $callback;

        if (!$this->isExist($this->path)) {
            $this->makePath($this->path);
        }

        $acls = [
            [
                'perms' => \Zookeeper::PERM_ALL,
                'scheme' => 'world',
                'id' => 'anyone',
            ],
        ];

        $path = $this->path.'/'.self::TYPE_WRITE.'-';
        $this->writeNode = $this->getZookeeper()->create($path, '', $acls, \Zookeeper::EPHEMERAL | \Zookeeper::SEQUENCE);
        $this->writeNode = \str_replace($this->path.'/', '', $this->writeNode);

        $this->writeCallback(\Zookeeper::CREATED_EVENT, \Zookeeper::CONNECTED_STATE, $this->path);
    }

    public function writeCallback(int $eventType, int $state, string $name): void
    {
        $children = $this->getZookeeper()->getChildren($this->path);
        if (empty($children)) {
            throw new ZooxExceptioin('no child in path '.$this->path);
        }
        $min = null;
        $write = true;
        [$type, $sequence] = \explode('-', $this->writeNode);
        foreach ($children as $child) {
            [$childType, $childSequence] = \explode('-', $child);
            if ($sequence > $childSequence) {
                $write = false;
                $min = $child;
                break;
            }
        }
        if ($write) {
            //get lock
            \call_user_func_array($this->writeCallback, [$this->path]);
            $this->deletePath($this->path.'/'.$this->writeNode);
            $this->writeNode = null;
        } else {
            $this->getZookeeper()->exists($this->path.'/'.$min, [$this, 'writeCallback']);
        }
    }
}
