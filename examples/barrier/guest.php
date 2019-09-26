<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Barrier;

$root = '/examples/barrier';
$barrier  = new Barrier($root);
$barrier->setZookeeper($zk);

$barrier->pass(function($path) {
    echo "barrier ".$path." was removed".PHP_EOL;
    //exit();
});

while (true) {
    sleep(1);
}
/*
Swoole\Timer::tick(100, function () {
    //\zookeeper_dispatch();
});
Swoole\Event::wait();
*/