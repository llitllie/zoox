<?php
$zk = include __DIR__.'/../common.php';

use Zoox\SharedLock;

$root = '/examples/lock';
$reader = new SharedLock($root);
$reader->setZookeeper($zk);

$reader->read(function ($path) {
    echo time()." : read start ".PHP_EOL;
    sleep(5);
    echo time()." : read end ".PHP_EOL;
});

while (true) {
    usleep(500);
}