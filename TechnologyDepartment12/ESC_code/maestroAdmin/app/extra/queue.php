<?php

use think\facade\Env;

return [
    'connector' => 'redis',
    'expire'     => 60,
    'default'    => 'default',
    'host'       => Env::get("queue.host"),
    'port'       => Env::get("queue.port"),
    'password'   => Env::get("queue.password"),
    'select'     => Env::get("queue.select"),
    'timeout'    => 0,
    'persistent' => false
];

