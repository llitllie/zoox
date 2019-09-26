<?php

declare(strict_types=1);

namespace Zoox;

class DoubleBarrier extends Base
{
    private $path;
    private $size = 1;
    private $readyCallback;
    private $leaveCallback;
    private $znode = 0;

    public function __construct(string $path, int $size)
    {
        $this->path = $path;
        $this->size = $size;
    }

    public function enter(callable $readyCallback): void
    {
        $this->readyCallback = $readyCallback;

        if (!$this->isExist($this->path)) {
            $this->makePath($this->path);
        }
        /*
        if ($this->isExist($this->path.'/ready')) {
            \call_user_func_array($this->readyCallback, [$this->path]);
            if($this->leaveCallback) $this->_leave();
            return;
        }*/
        $state = $this->getZookeeper()->exists($this->path.'/ready', [$this, 'readyCallback']);
        $acls = [
            [
                'perms' => \Zookeeper::PERM_ALL,
                'scheme' => 'world',
                'id' => 'anyone',
            ],
        ];
        //TODO use sequence instead of hostname/process id
        $this->znode = $this->getZookeeper()->create($this->path.'/', '', $acls, \Zookeeper::EPHEMERAL | \Zookeeper::SEQUENCE);
        $this->znode = \str_replace($this->path.'/', '', $this->znode);
        $children = $this->getZookeeper()->getChildren($this->path);
        if ($children) {
            if (\count($children) === $this->size) {
                $this->makePath($this->path.'/ready');
            }
        }
    }

    public function readyCallback(int $eventType, int $state, string $name): void
    {
        if (\Zookeeper::CREATED_EVENT === $eventType) {
            \call_user_func_array($this->readyCallback, [$this->path]);
            $this->_leave();
        } else {
            $this->getZookeeper()->exists($this->path.'/ready', [$this, 'readyCallback']);
        }
    }

    private function _leave(): void
    {
        if ($this->isExist($this->path.'/ready')) {
            $children = $this->getZookeeper()->getChildren($this->path);
            if ($children) {
                if (\count($children) <= 2) {
                    $this->deletePath($this->path.'/ready');
                }
                $max = (int) ($this->znode);
                foreach ($children as $r) {
                    $znode = (int) $r;
                    $max = ($max > $znode) ? $max : $znode;
                }
                if ($max === (int) ($this->znode)) {
                    \call_user_func_array($this->leaveCallback, [$this->path]);
                    $this->deletePath($this->path.'/'.$this->znode);
                }
            } else {
                $this->deletePath($this->path.'/ready');
                \call_user_func_array($this->leaveCallback, [$this->path]);
            }
        }
    }

    public function leave(callable $leaveCallback): void
    {
        $this->leaveCallback = $leaveCallback;
        //leaveCallback will be triggered after ready(nearby readyCallback)
        //if listen to children change, must calculate the node number itself
        //$children = $this->getZookeeper()->getChildren($this->path, [$this, 'leaveCallback']);
    }
}
