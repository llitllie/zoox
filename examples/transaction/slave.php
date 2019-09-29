<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Transaction;

$root = '/examples/transaction';
$slave = new Transaction($root);
$slave->setZookeeper($zk);

$slave->join();

while (true) {
    usleep(500);
}