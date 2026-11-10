<?php

namespace util;

class RedisSyncPool
{
    private $pool = [];
    private $config;
    private $db;
    private $maxConnections;

    public function __construct($config, $db = 0, $maxConnections = 10)
    {
        $this->config = $config;
        $this->db = $db;
        $this->maxConnections = $maxConnections;

        // 初始化连接池
        for ($i = 0; $i < $this->maxConnections; $i++) {
            $this->pool[] = $this->createConnection();
        }
    }

    private function createConnection()
    {
        $redis = new \Redis();
        try {
            $redis->connect($this->config['host'], $this->config['port'], 3);
            if (!empty($this->config['password'])) {
                $redis->auth($this->config['password']);
            }
            $redis->select($this->db);
            return $redis;
        } catch (\Exception $e) {
            throw new \RuntimeException("Redis connection failed: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        if (empty($this->pool)) {
            return $this->createConnection(); // 可选：扩容
        }

        $redis = array_pop($this->pool);

        if (!$this->isConnected($redis)) {
            $redis = $this->reconnect();
        }

        return $redis;
    }

    public function releaseConnection($redis)
    {
        $this->pool[] = $redis;
    }

    private function isConnected(\Redis $redis): bool
    {
        try {
            return $redis->ping() === '+PONG';
        } catch (\Exception $e) {
            return false;
        }
    }

    private function reconnect(): \Redis
    {
        return $this->createConnection();
    }

    public function get($key, $default = null)
    {
        $redis = $this->getConnection();
        try {
            $result = $redis->get($key);
            return $result === false ? $default : $result;
        } finally {
            $this->releaseConnection($redis);

        }
    }

    public function set($key, $value, $ttl = null)
    {
        $redis = $this->getConnection();
        try {
            return $redis->set($key, $value, $ttl);
        } finally {
            $this->releaseConnection($redis);
        }
    }

    public function del($key)
    {
        $redis = $this->getConnection();
        try {
            return $redis->del($key);
        } finally {
            $this->releaseConnection($redis);
        }
    }

    public function rpush($key, $value)
    {
        $redis = $this->getConnection();
        try {
            return $redis->rpush($key, $value);
        } finally {
            $this->releaseConnection($redis);
        }
    }

    public function lpop($key)
    {
        $redis = $this->getConnection();
        try {
            return $redis->lpop($key);
        } finally {
            $this->releaseConnection($redis);
        }
    }

    public function __destruct()
    {
        // 可选：关闭连接
    }
}
