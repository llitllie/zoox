<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Barrier;

$root = '/examples/barrier';
$barrier  = new Barrier($root);
$barrier->setZookeeper($zk);

$barrier->block();
sleep(25);
$barrier->remove();