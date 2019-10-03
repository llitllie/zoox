<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Transaction;

$root = '/examples/transaction';
$slave = new Transaction($root);
$slave->setZookeeper($zk);

$slave->join();

//$slave->execute('something S');

while (true) {
    usleep(500);
}