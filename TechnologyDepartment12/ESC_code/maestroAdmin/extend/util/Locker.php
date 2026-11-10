<?php

namespace util;

use app\lib\exception\LockException;
use think\facade\Cache;
use think\helper\Str;

class Locker {

    protected static $instance;
    private $redis;
    private $key;
    private $relese;

    public function __construct($key = null) {
        $this->redis = Cache::store('redis')->handler();
        $this->key = $key ?? md5(Str::random(15));
        $this->relese = false;
    }

    public function lock($timeout = 3) {
        $redis = $this->redis;
        $key = $this->key;
        if ($redis->get($key)) {
            throw new LockException();
        }

        $redis->set($key, 1, $timeout);
    }

    public function unLock() {
        $this->redis->delete($this->key);
    }


    public function __destruct() {
        if ($this->relese) {
            $this->unLock();
        }
    }

}

