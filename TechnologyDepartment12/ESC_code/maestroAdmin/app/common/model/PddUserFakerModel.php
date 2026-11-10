<?php

namespace app\common\model;

use think\facade\Cache;
use think\Model;

class PddUserFakerModel extends Model
{
    // 确定链接表名
    protected $name = 'pdd_user_faker';

    public static function getListAll()
    {
        $redis = Cache::store('redis');
        $redis_key = config("redis_cache_key.pdd_user_faker");
        if ($redis->has($redis_key)) {
            return $redis->get($redis_key);
        }
        $fake_vip_list = PddUserFakerModel::order('sort ASC')->select();
        $out_list = [];
        foreach ($fake_vip_list as $value) {
            $value['level_id'] = "VIP" . $value['level_id'];
            $out_list[] = $value;
        }
        $redis->set($redis_key, $out_list);
        return $out_list;
    }
}
