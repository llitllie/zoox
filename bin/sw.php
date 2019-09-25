<?php

$scheduler = new Swoole\Coroutine\Scheduler;
$scheduler->add(function () {
    Co::sleep(1);
    echo "Done.\n";
});
$scheduler->start();