<?php

declare(strict_types=1);

namespace Zoox;


class Battery extends Base
{
    private $path;
    private $znode;
    private $size = 1;
    private $callback;

    public function __construct(string $path, int $size = 1)
    {
        $this->path = $path;
        $this->size = $size;
    }

    public function connect()
    {
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
        //TODO use object uuid, can it can call twice in same process
        $this->znode = $this->getZookeeper()->create($this->path.'/', '', $acls, \Zookeeper::EPHEMERAL | \Zookeeper::SEQUENCE);
        $this->znode = \str_replace($this->path.'/', '', $this->znode);
    }

    public function process(callable $callback)
    {
        $this->callback = $callback;
        $this->_process();
    }

    private function _process()
    {
        $children = $this->getZookeeper()->getChildren($this->path);
        if (!empty($children)) {
            if (\count($children) >= $this->size) {
                \call_user_func_array($this->callback, [$this->path]);
            } else {
                $this->getZookeeper()->getChildren($this->path, [$this, 'callback']);
            }
        }
    }

    public function callback(int $eventType, int $state, string $name)
    {
        $this->_process();
    }

    public function __destruct()
    {
        //if consiten child node, remove the path
        //$this->deletePath($this->path);
    }
}