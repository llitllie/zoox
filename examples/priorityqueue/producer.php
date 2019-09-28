<?php
$zk = include __DIR__.'/../common.php';

use Zoox\PriorityQueue;

$root = '/examples/queue';
$producer = new PriorityQueue($root);
$producer->setZookeeper($zk);

$i = 0;
while ($i < 10) {
    $priority = random_int(1, 10);
    $producer->send((string) $i, $priority);
    $i++;
}

while (true) {
    sleep(1);
}