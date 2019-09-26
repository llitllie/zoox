<?php
include __DIR__.'/../vendor/autoload.php';

$host = getenv("ZOOKEEPER_CONNECTION");
$host = empty($host) ? "192.168.33.1:2181" : $host;
$path = "/event";
$zk = new \Zookeeper($host, function ($eventType, $state, $name){
    echo "zk1 initialize".PHP_EOL;
    var_dump(func_get_args());
});

$stat = $zk->getChildren($path, function ($eventType, $state, $name){
    echo "zk2 setWatcher".PHP_EOL;
    var_dump(func_get_args());
    //itn's an one-time event, regiest again once it's called
});
var_dump($stat);
$stat = $zk->getChildren($path, function ($eventType, $state, $name){
    echo "zk3 setWatcher".PHP_EOL;
    var_dump(func_get_args());
    //itn's an one-time event, regiest again once it's called
});
var_dump($stat);

while(true) {
    sleep(1);
}