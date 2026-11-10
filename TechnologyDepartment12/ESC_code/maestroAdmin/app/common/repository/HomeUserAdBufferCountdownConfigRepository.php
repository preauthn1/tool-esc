<?php

namespace app\common\repository;

use app\common\model\HomeUserAdBufferCountdownConfigModel;

use think\facade\Cache;

class HomeUserAdBufferCountdownConfigRepository extends Repository
{
    protected $model;
    protected $redis;

    public function __construct()
    {

        //绑定模型
        $this->model = new HomeUserAdBufferCountdownConfigModel();
        $this->redis = Cache::store('redis');
    }


    public function getUserAdBufferCountdownConfig($user_id)
    {
        $redis_key = config("redis_cache_key.user_ad_buffer_countdown_config").'_'.$user_id;
        return $this->redis->remember($redis_key, function () use ($user_id) {
            return $this->findWhere([
                ['user_id', '=', $user_id],
            ]);
        }, 30);
    }
}
