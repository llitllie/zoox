<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Battery;

$root = '/examples/battery';
$battery  = new Battery($root);
$battery->setZookeeper($zk);

$battery->connect();


while (true) {
    sleep(1);
}