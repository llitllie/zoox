<?php
$zk = include __DIR__.'/../common.php';

use Zoox\DoubleBarrier;

$root = '/examples/dbarrier';
$master = new DoubleBarrier($root, 2);
$master->setZookeeper($zk);


$master->enter(function($path) {
    echo 'master enter'.PHP_EOL;
});

$master->leave(function($path) {
    echo 'master leave'.PHP_EOL;
});

/*
$slave = new DoubleBarrier($root, 2);
$slave->setZookeeper($zk);

$slave->enter(function($path) {
    echo 'slave enter'.PHP_EOL;
});

$slave->leave(function($path) {
    echo 'slave leave'.PHP_EOL;
});/**/

while (true) {
    sleep(1);
}