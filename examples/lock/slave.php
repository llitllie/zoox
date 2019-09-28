<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Lock;

$root = '/examples/lock';
$slave = new Lock($root);
$slave->setZookeeper($zk);


$slave->aquire();
echo time()." : slave aquired".PHP_EOL;
sleep(5);
$slave->release();
echo time()." : slave release".PHP_EOL;