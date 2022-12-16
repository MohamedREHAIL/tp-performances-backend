<?php

namespace App\Common;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;

class Cache
{
private static Cache $instance;
private AdapterInterface $cache;
    public function __construct()
    {
        $client = RedisAdapter::createConnection('redis://redis');
        $this->cache = new RedisTagAwareAdapter($client,'hotel_', 0);
    }
    public static function get():AdapterInterface
{
    self::$instance=new Cache();
    return self::$instance->cache;
}

}