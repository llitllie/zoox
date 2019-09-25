<?php

declare(strict_types=1);

namespace Zoox;

class Base
{
    private $zk;

    public function setZookeeper(\Zookeeper $zk): void
    {
        $this->zk = $zk;
    }

    public function getZookeeper(): \Zookeeper
    {
        return $this->zk;
    }

    public function isExist(string $path): bool
    {
        if (false === $this->getZookeeper()->exists($path)) {
            return false;
        }

        return true;
    }

    public function makePath(string $path, string $value = ''): bool
    {
        $arrPath = \explode('/', $path);
        if (!empty($arrPath)) {
            $arrPath = \array_filter($arrPath);
            $subpath = '';
            $flag = true;
            foreach ($arrPath as $p) {
                $subpath .= '/'.$p;
                if (!$this->isExist($subpath)) {
                    if (!$this->makeNode($subpath, $value)) {
                        $flag = false;
                        break;
                    }
                }
            }

            return $flag;
        }

        return false;
    }

    public function makeNode(string $path, string $value, array $acls = [], int $flag = 0): bool
    {
        if (empty($acls)) {
            $acls = [
                [
                    'perms' => \Zookeeper::PERM_ALL,
                    'scheme' => 'world',
                    'id' => 'anyone',
                ],
            ];
        }
        if ($this->getZookeeper()->create($path, $value, $acls, $flag)) {
            return true;
        }

        return false;
    }

    public function deletePath(string $path): bool
    {
        $children = $this->getZookeeper()->getChildren($path);
        if (!empty($children)) {
            foreach ($children as $child) {
                $subpath = $path.'/'.$child;
                $this->deletePath($subpath);
            }
        }

        return $this->getZookeeper()->delete($path);
    }
}
