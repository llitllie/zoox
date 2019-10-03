<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Election;

$root = '/examples/election';
$master = new Election($root);
$master->setZookeeper($zk);

$master->register();
while (true) {
    if ($master->isLead()) {
        echo time().": I'm leader".PHP_EOL;
    } else {
        echo time().": I'm follower".PHP_EOL;
    }
    sleep(1);
}