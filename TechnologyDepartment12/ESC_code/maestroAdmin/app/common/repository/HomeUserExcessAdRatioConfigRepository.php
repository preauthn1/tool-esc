<?php

namespace app\common\repository;

use app\common\model\HomeUserExcessAdRatioConfigModel;

use think\facade\Cache;

class HomeUserExcessAdRatioConfigRepository extends Repository
{
    protected $model;
    protected $redis;

    public function __construct()
    {

        //绑定模型
        $this->model = new HomeUserExcessAdRatioConfigModel();
        $this->redis = Cache::store('redis');
    }


    public function getUserExcessAdRatioConfig($user_id, $level_id)
    {
        $redis_key = config("redis_cache_key.user_excess_ad_ration").'_'.$level_id.'_'.$user_id;
        if ($this->redis->has($redis_key)) {
            return $this->redis->get($redis_key);
        }
        return $this->redis->remember($redis_key, function () use ($user_id, $level_id) {
            return $this->findWhere([
                ['user_id', '=',  $user_id],
                ['level_id', '=', $level_id],
            ]);
        }, 30);
    }
}
