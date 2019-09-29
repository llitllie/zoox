<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Lock;

$root = '/examples/lock';
$master = new Lock($root);
$master->setZookeeper($zk);


echo time()." : master acquire".PHP_EOL;
$master->process(function($path) {
    echo time().' : master '.PHP_EOL;
    sleep(5);
});

while (true) {
    usleep(500);
}