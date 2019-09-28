<?php
$zk = include __DIR__.'/../common.php';

use Zoox\DoubleBarrier;

$root = '/examples/dbarrier';
$master = new DoubleBarrier($root, 2);
$master->setZookeeper($zk);


$master->enter(function($path) {
    echo 'master enter: '.time().PHP_EOL;
    //sleep(sync) block everything, so it's better to use this in different process
    sleep(3);
});

$master->leave(function($path) {
    echo 'master leave: '.time().PHP_EOL;
});

/* doesn't make sense, use coroutine
$slave = new DoubleBarrier($root, 2);
$slave->setZookeeper($zk);

$slave->enter(function($path) {
    echo 'slave enter: '.time().PHP_EOL;
});

$slave->leave(function($path) {
    echo 'slave leave: '.time().PHP_EOL;
});/**/

while (true) {
    sleep(1);
}