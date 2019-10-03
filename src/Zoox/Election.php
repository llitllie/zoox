<?php

declare(strict_types=1);

namespace Zoox;

class Election extends Base
{
    private $path;
    private $callback;
    private $isLeader = false;
    private $znode;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function register()
    {
        if (!$this->isExist($this->path)) {
            $this->makePath($this->path);
        }
        $acls = [
            [
                "perms" => \Zookeeper::PERM_ALL,
                "scheme" => "world",
                "id" => "anyone"
            ]
        ];

        $this->znode = $this->getZookeeper()->create($this->path."/e-", "", $acls, \Zookeeper::EPHEMERAL | \Zookeeper::SEQUENCE);
        $this->znode = \str_replace($this->path."/", "", $this->znode);

        $previous = $this->watchPrevious();
        if ($previous === $this->znode) {
            $this->isLeader = true;
        }

    }

    public function watchPrevious(): string
    {
        $children = $this->getZookeeper()->getChildren($this->path);
        $previous = $this->znode;
        if (!empty($children)) {
            \sort($children);
            $size = \count($children);
            if ($size > 1) {
                for ($i = 1; $i < $size; $i++) {
                    if ($this->znode === $children[$i]) {
                        $previous =  $children[$i-1];
                        $this->getZookeeper()->exists($this->path."/".$previous, [$this, 'watchCallback']);
                    }
                }
            }
        }
        return $previous;
    }

    public function watchCallback(int $eventType, int $state, string $name): void
    {
        $previous = $this->watchPrevious();
        if ($previous === $this->znode) {
            $this->isLeader = true;
        } else {
            $this->isLeader = false;
        }
    }

    public function isLead(): bool
    {
        return $this->isLeader;
    }

    public function unregister(): void
    {
        $path = $this->path."/".$this->znode;
        if ($this->isExists($path)) {
            $this->deletePath($path);
        }
        $this->znode = null;
        $this->isLeader = false;
    }

    public function __desctruct()
    {
        $this->unregister();
    }
}