<?php

declare(strict_types=1);

namespace Zoox;

class Lock extends Base
{
    private $path;
    private $callback;
    private $znode;

    public function __construct(string $path)
    {
        //every object should have an uuid here
        $this->path = $path;
    }

    public function acquire(int $timeout = 0)
    {
        $start = \time();
        while ($this->isExist($this->path)) {
            if (($timeout > 0) && ((\time() - $start) > $timeout)) {
                return false;
            }
            \usleep(\random_int(600, 1000));
        }
        $result = false;
        while (!$result) {
            if (($timeout > 0) && ((\time() - $start) > $timeout)) {
                return false;
            }

            try {
                $znode = $this->makePath($this->path);
                $result = true;
            } catch (\Exception $e) {
                //keep try
                \usleep(\random_int(600, 1000));
            }
        }

        return $result;
    }

    public function release(): void
    {
        $this->deletePath($this->path);
    }

    public function process(callable $callback): void
    {
        $this->callback = $callback;

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
        $this->znode = $this->getZookeeper()->create($this->path.'/', '', $acls, \Zookeeper::EPHEMERAL | \Zookeeper::SEQUENCE);
        $this->znode = \str_replace($this->path.'/', '', $this->znode);
        //manually trigger once
        $this->callback(\Zookeeper::DELETED_EVENT, \Zookeeper::CONNECTED_STATE, $this->path);
    }

    public function callback(int $eventType, int $state, string $name): void
    {
        $children = $this->getZookeeper()->getChildren($this->path);
        if (!empty($children)) {
            $min = null;
            foreach ($children as $child) {
                if (null === $min) {
                    $min = $child;
                } else {
                    $min = ($min > $child) ? $child : $min;
                }
            }
            $znode = $this->path.'/'.$min;

            if ($min === $this->znode) {
                //get lock
                \call_user_func_array($this->callback, [$this->path]);
                //when exit, zk auto delete the lock but it will take more time, to get this event quickly, do it manually
                $this->deletePath($znode);
            } else {
                $this->getZookeeper()->exists($znode, [$this, 'callback']);
            }
        } else {
            throw new ZooxExceptioin('no child in lock');
        }
    }
}
