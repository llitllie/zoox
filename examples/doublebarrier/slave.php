<?php
$zk = include __DIR__.'/../common.php';

use Zoox\DoubleBarrier;

$root = '/examples/dbarrier';
$barrier = new DoubleBarrier($root, 2);
$barrier->setZookeeper($zk);


$barrier->enter(function($path) {
    //usleep(600000);
    echo 'slave enter: '.time().PHP_EOL;
});

$barrier->leave(function($path) {
    echo 'slave leave: '.time().PHP_EOL;
});


while (true) {
    sleep(1);
}