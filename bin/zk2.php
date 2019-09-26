<?php
include __DIR__.'/../vendor/autoload.php';

$host = getenv("ZOOKEEPER_CONNECTION");
$host = empty($host) ? "192.168.33.1:2181" : $host;
$path = "/event/001";
$acl = array(
    array(
        'perms' => \Zookeeper::PERM_ALL,
        'scheme' => 'world',
        'id' => 'anyone'
    )
);

$zk = new \Zookeeper($host);

$zk->set($path, "update1");
//$zk->delete($path);