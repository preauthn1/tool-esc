<?php

namespace app\common\repository;

use app\admin\model\LevelTeamModel;

use think\facade\Cache;

class LevelTeamRepository extends Repository
{
    protected $model;
    protected $redis;

    public function __construct()
    {

        //绑定模型
        $this->model = new LevelTeamModel();
        $this->redis = Cache::store('redis');
    }

    /**
     * 状态常量
     */
    const STATUS_DISABLE = 0; // 停用
    const STATUS_ENABLE = 1;  // 正常

    /**
     * 状态映射
     */
    public static $statusMap = [
        self::STATUS_DISABLE => '停用',
        self::STATUS_ENABLE => '正常'
    ];

    public function getLevelTeamConfig($user_id, $level_id)
    {
        $redis_key = config("redis_cache_key.team_level_config").'_'.$level_id.'_'.$user_id;
        return $this->redis->remember($redis_key, function () use ($user_id, $level_id) {
            // 获取团队广告位配置
            $team_level = (new LevelTeamRepository)->findWhere([
                ['user_id', '=', $user_id],
                ['level_id', '=', $level_id],
                ['status', '=', LevelTeamRepository::STATUS_ENABLE],
            ]);
            if (!$team_level) {
                return [];
            }
            return $team_level;
        }, 30);
    }

    public function updateLevelTeamConfig($user_id, $level_id, $data): void
    {
        $level_team = $this->getLevelTeamConfig($user_id, $level_id);
        if ($level_team) {
            $level_team->save($data);
            $this->clearCache($user_id, $level_id);
        }
    }

    public function clearCache($user_id, $level_id): void
    {
        $redis = Cache::store('redis');
        $redis_key = config("redis_cache_key.team_level_config").'_'.$level_id.'_'.$user_id;
        $redis->delete($redis_key);
    }
}
