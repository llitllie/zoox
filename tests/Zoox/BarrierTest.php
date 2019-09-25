<?php

declare(strict_types=1);

namespace Tests\Zoox;

use PHPUnit\Framework\TestCase;
use Zoox\Barrier;

class BarrierTest extends TestCase
{
    public function testBarrier(): void
    {
        $root = '/test/barrier';
        $zk = new \Zookeeper(\getenv('ZOOKEEPER_CONNECTION'));

        $barrier = new Barrier($root);
        $barrier->setZookeeper($zk);
        $this->assertTrue($barrier->isOpen());
        $this->assertTrue($barrier->block());
        $this->assertFalse($barrier->isOpen());
        $this->assertTrue($barrier->remove());
        $this->assertTrue($barrier->isOpen());
    }
}
