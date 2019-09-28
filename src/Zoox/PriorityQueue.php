<?php

declare(strict_types=1);

namespace Zoox;

class PriorityQueue extends Queue
{
    public const PRIORITY_MAX = 100;
    public const PRIORITY_MIN = 0;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function send(string $data, int $priority): ?string
    {
        if ($priority < self::PRIORITY_MIN || $priority > self::PRIORITY_MAX) {
            throw new ZooxException('priority must between '.self::PRIORITY_MIN.' and '.self::PRIORITY_MAX);
        }
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
        $priority = \sprintf('%02d', $priority);
        $znode = $this->path.'/'.$priority.'-';

        return $this->getZookeeper()->create($znode, $data, $acls, \Zookeeper::EPHEMERAL | \Zookeeper::SEQUENCE);
    }
}
