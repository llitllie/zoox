<?php
$zk = include __DIR__.'/../common.php';

use Zoox\DoubleBarrier;

$root = '/examples/dbarrier';
$barrier = new DoubleBarrier($root, 2);
$barrier->setZookeeper($zk);


$barrier->enter(function($path) {
    sleep(1);
    echo 'slave enter'.PHP_EOL;
});

$barrier->leave(function($path) {
    echo 'slave leave'.PHP_EOL;
});


while (true) {
    sleep(1);
}