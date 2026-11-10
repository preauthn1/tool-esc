<?php

namespace util;

class RedisPconnectPool
{
    private $db;
    private $config;

    public function __construct($config = [], $db = 0)
    {
        $this->config = $config;
        $this->db = $db;
    }

    public function connect()
    {
        $redis = new \Redis();
        $config = $this->config;
        $redis->connect($config['host'], $config['port'], 3);
        if ($config['password']) {
            $redis->auth($config['password']);
        }
        $redis->select($this->db);
        return $redis;
    }
}
