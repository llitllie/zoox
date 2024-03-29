<?php

declare(strict_types=1);

namespace Zoox;

class DoubleBarrier extends Base
{
    private $isReady = false;
    private $path;
    private $size = 1;
    private $readyCallback;
    private $leaveCallback;
    private $znode = 0;
    private $readyPath;

    public function __construct(string $path, int $size)
    {
        $this->path = $path;
        $this->size = $size;
        $this->readyPath = $this->path.'/ready';
    }

    public function enter(callable $readyCallback): void
    {
        $this->readyCallback = $readyCallback;

        if (!$this->isExist($this->path)) {
            $this->makePath($this->path);
        }
        if ($this->isExist($this->readyPath)) {
            $this->deletePath($this->readyPath);
        }
        $state = $this->getZookeeper()->exists($this->readyPath, [$this, 'readyCallback']);
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
        //var_dump($this->path.'/'.$this->znode);
        $children = $this->getZookeeper()->getChildren($this->path);
        if ($children) {
            if (\count($children) === $this->size) {
                $this->makePath($this->readyPath);
                $this->isReady = true;
            }
        }
    }

    public function readyCallback(int $eventType, int $state, string $name): void
    {
        \call_user_func_array($this->readyCallback, [$this->path]);
    }

    public function leave(callable $leaveCallback): void
    {
        $this->leaveCallback = $leaveCallback;
        //leaveCallback will be triggered after ready(nearby readyCallback)
        //if listen to children change, must calculate the node number itself
        //it's the last one to enter, leave directly, no one can trigger it
        $children = $this->getZookeeper()->getChildren($this->path, [$this, 'leaveCallback']);
        if ($this->isReady) {
            //TODO: not sure way, but need wait for smaller nodes to process enter callback(no need to wait it complete)
            //look like ready notifcation takes 1 second, so when leave notifcation comes right after (>0.6 seconds)
            //maybe it's due to the sleep in process
            \sleep(1);
            $this->deletePath($this->path.'/'.$this->znode);
        }
    }

    public function leaveCallback(int $eventType, int $state, string $name): void
    {
        //TODO every time child nodes changed, it's triggered, includes add ready/remove others, should only listen to child_delete
        $children = $this->getZookeeper()->getChildren($this->path, [$this, 'leaveCallback']);
        if ($this->isExist($this->readyPath)) {
            if ($this->isReady) {
                $children = $this->getZookeeper()->getChildren($this->path);
                //the last one is "/ready" node
                if (1 === \count($children)) {
                    \call_user_func_array($this->leaveCallback, [$this->path]);
                } else {
                    $max = -1;
                    foreach ($children as $r) {
                        $znode = (int) $r;
                        $max = ($max > $znode) ? $max : $znode;
                    }
                    if ($max === (int) ($this->znode)) {
                        $this->deletePath($this->path.'/'.$this->znode);
                    }
                }
            } else {
                //every node will receive /ready node created event, except the last one
                $this->isReady = true;
            }
        }
    }
}
