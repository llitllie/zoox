<?php
include __DIR__.'/../vendor/autoload.php';

$host = \getenv("ZOOKEEPER_CONNECTION");
$host = empty($host) ? "192.168.33.1:2181" : $host;

return new \Zookeeper($host );