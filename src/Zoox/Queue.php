<?php

declare(strict_types=1);

namespace Zoox;

class Queue extends Base
{
    protected $path;
    private $callback;
    private $ready = false;
    private $coroutine;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function enqueue(string $data): ?string
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

        return $this->getZookeeper()->create($this->path.'/', $data, $acls, \Zookeeper::EPHEMERAL | \Zookeeper::SEQUENCE);
    }

    public function dequeue(): ?string
    {
        //better than only listen to created event
        $data = null;
        if (!$this->isExist($this->path)) {
            $this->makePath($this->path);
        }
        $children = $this->getZookeeper()->getChildren($this->path);
        //if no children, sleep, loop
        if (!empty($children)) {
            $min = null;
            foreach ($children as $znode) {
                if (null === $min) {
                    $min = $znode;
                } else {
                    $min = $min > $znode ? $znode : $min;
                }
            }
            try {
                $znode = $this->path.'/'.$min;
                $data = $this->getZookeeper()->get($znode);
                $result = $this->deletePath($znode);
                if (!$result) {
                    $data = null;
                }
            } catch (\Exception $e) {
                //failed to lock the node, try again in loop
                $data = null;
            }
        }

        return $data;
    }

    public function listen(callable $callback): void
    {
        $this->callback = $callback;
        if (!$this->isExist($this->path)) {
            $this->makePath($this->path);
        }
        //manulay trigger it
        $this->callback(\Zookeeper::CREATED_EVENT, \Zookeeper::CONNECTED_STATE, $this->path);
    }

    public function callback(int $eventType, int $state, string $name): void
    {
        $children = $this->getZookeeper()->getChildren($this->path, [$this, 'callback']);
        //if no children, sleep
        if (!empty($children)) {
            $min = null;
            foreach ($children as $znode) {
                if (null === $min) {
                    $min = $znode;
                } else {
                    $min = $min > $znode ? $znode : $min;
                }
            }
            try {
                $znode = $this->path.'/'.$min;
                $data = $this->getZookeeper()->get($znode);
                $result = $this->deletePath($znode);
                if ($result) {
                    //if we don't care the first callback(2th execute before 1st) then just call it directly
                    //\call_user_func_array($this->callback, [$znode.':'.$data]);
                    //when deletePath, it trigger callback again, so it goes back and executed first
                    //below codes make it in order
                    if (!$this->ready) {
                        $this->coroutine = new Coroutine();
                        $this->coroutine->wait(function ($notify) use ($data): void {
                            \call_user_func_array($this->callback, [$data]);
                        });
                    } else {
                        \call_user_func_array($this->callback, [$data]);
                        if ($this->coroutine) {
                            $this->coroutine->notify();
                            $this->coroutine->disable();
                        }
                    }
                }
            } catch (\Exception $e) {
                //failed to lock the node, try again in loop
            }
        }
        $this->ready = true;
    }
}
