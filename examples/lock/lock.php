<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Lock;

$root = '/examples/lock';
$master = new Lock($root);
$master->setZookeeper($zk);


$master->acquire();
echo time()." : master aquired".PHP_EOL;
sleep(5);
$master->release();
echo time()." : master release".PHP_EOL;