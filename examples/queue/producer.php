<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Queue;

$root = '/examples/queue';
$producer = new Queue($root);
$producer->setZookeeper($zk);

$i = 0;
while ($i < 100) {
    $producer->enqueue((string) $i);
    $i++;
}

while (true) {
    sleep(1);
}