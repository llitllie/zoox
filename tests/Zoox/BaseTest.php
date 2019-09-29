<?php

declare(strict_types=1);

namespace Tests\Zoox;

use PHPUnit\Framework\TestCase;
use Zoox\Base;

class BaseTest extends TestCase
{
    public function testZookeeper(): void
    {
        $root = '/test';
        $node = $root.'/001';
        $zk = new \Zookeeper(\getenv('ZOOKEEPER_CONNECTION'));
        $base = new Base();
        $base->setZookeeper($zk);
        $this->assertEquals($zk, $base->getZookeeper());
        if ($base->getZookeeper()->exists($root)) {
            $this->assertTrue($base->deletePath($root));
        }
        $this->assertTrue($base->makePath($node));
        $this->assertTrue($base->makeNode($root.'/002', ''));
        $this->assertTrue($base->deletePath($node));
        $this->assertTrue($base->deletePath('/examples/transaction'));
    }
}
