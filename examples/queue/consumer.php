<?php
$zk = include __DIR__.'/../common.php';

use Zoox\Queue;

$root = '/examples/queue';
$consumer = new Queue($root);
$consumer->setZookeeper($zk);

$consumer->listen(function($data) {
    echo time().' : '.$data.PHP_EOL;
});

while (true) {
    /*$data = $consumer->dequeue();
    if (!is_null($data)) {
        echo time().' : '.$data.PHP_EOL;
    } else {
        sleep(1);
    }/**/
    usleep(500);
}