<?php

namespace app\common\model;

use think\Model;

class UserFakeModel extends Model
{
    // 确定链接表名
    protected $name = 'user_fake';

    public static function getListAll()
    {
        $redis = \think\facade\Cache::store('redis');
        $redis_key = config("redis_cache_key.user_fake");
        if ($redis->has($redis_key)) {
            return $redis->get($redis_key);
        }
        $fake_vip_list = UserFakeModel::order('sort ASC')->select();
        $out_list = [];
        foreach ($fake_vip_list as $value) {
            $value['level_id'] = "VIP" . $value['level_id'];
            $out_list[] = $value;
        }
        $redis->set($redis_key, $out_list);
        return $out_list;
    }
}
