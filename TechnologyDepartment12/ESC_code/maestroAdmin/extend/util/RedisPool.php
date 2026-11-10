<?php

namespace util;

class RedisPool
{
    private $pool = [];
    private $max;
    private $config;

    public function __construct($max = 5, $config = [])
    {
        $this->config = $config;
        $this->max = $max;
        for ($i = 0; $i < $max; $i++) {
            $this->pool[] = $this->connect();
        }
    }

    public function get()
    {
        $redis = array_pop($this->pool);
        if (!$redis->ping()) {
            $redis = $this->connect();
        }
        return $redis;
    }

    public function put($redis)
    {
        $this->pool[] = $redis;
    }

    public function connect()
    {
        $redis = new \Redis();
        $config = $this->config;
        $redis->pconnect($config['host'], $config['port']);
        if ($config['password']) {
            $redis->auth($config['password']);
        }
        return $redis;
    }
}
