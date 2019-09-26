<?php
$zk = include __DIR__.'/../common.php';

use Zoox\DoubleBarrier;

$root = '/examples/dbarrier';
$barrier = new DoubleBarrier($root, 2);
$barrier->setZookeeper($zk);

$barrier->leave(function($path) {
    echo 'slave leave'.PHP_EOL;
});

$barrier->enter(function($path) {
    echo 'slave enter'.PHP_EOL;
});


while (true) {
    sleep(1);
}