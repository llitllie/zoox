<?php
$zk = include __DIR__.'/../common.php';

use Zoox\SharedLock;

$root = '/examples/lock';
$writer = new SharedLock($root);
$writer->setZookeeper($zk);

$writer->read(function ($path) {
    echo time()." : read start ".PHP_EOL;
    sleep(5);
    echo time()." : read end ".PHP_EOL;
});

$writer->write(function ($path) {
    echo time()." : write start ".PHP_EOL;
    sleep(5);
    echo time()." : write end ".PHP_EOL;
});

while (true) {
    usleep(500);
}