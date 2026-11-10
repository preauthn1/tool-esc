<?php

namespace app\common\repository;

use app\common\model\MemberLevelModel;

use think\facade\Cache;

class MemberLevelRepository extends Repository
{
    protected $model;
    protected $redis;

    public function __construct()
    {

        //绑定模型
        $this->model = new MemberLevelModel();
        $this->redis = Cache::store('redis');
    }


    // 0-未解锁 1-已解锁 2-已过期
    const STATUS_LOCKED = 0;
    const STATUS_UNLOCKED = 1;
    const STATUS_EXPIRED = 2;

    public function getUserLevels($user_id)
    {
        return $this->redis->remember('user_levels_'.$user_id, function () use ($user_id) {
            return $this->order(['to_level' => 'desc'])
                ->selectWhere([
                    ['user_id', '=', $user_id],
                    ['level_end_time', '>', time()]
                ]);
        }, 3600);
    }

    public function getUserLevel($user_id, $level_id)
    {
        return $this->redis->remember('user_level_'.$level_id.'_'.$user_id, function () use ($user_id, $level_id) {
            return $this->findWhere([
                ['user_id', '=', $user_id],
                ['to_level', '=', $level_id],
                ['level_end_time', '>', time()]
            ]);
        }, 3600);
    }
}
