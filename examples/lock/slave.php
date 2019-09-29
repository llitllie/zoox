<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Lock;

$root = '/examples/lock';
$slave = new Lock($root);
$slave->setZookeeper($zk);


echo time()." : salve acquire".PHP_EOL;
$slave->process(function($path) {
    echo time().' : slave '.PHP_EOL;
});

while (true) {
    usleep(500);
}