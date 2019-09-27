<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Battery;

$root = '/examples/battery';
$battery  = new Battery($root, 2);
$battery->setZookeeper($zk);

$battery->connect();

$battery->process(function($path) {
    echo "battery ".$path." is ready".PHP_EOL;
    //exit();
});

while (true) {
    sleep(1);
}