<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Transaction;

$root = '/examples/transaction';
$master = new Transaction($root);
$master->setZookeeper($zk);

$master->join();
//let slave join
sleep(1);

$master->proceed('something');

while (true) {
    usleep(500);
}