<?php

namespace app\common\repository;

use app\admin\model\LevelModel;
use app\lib\exception\FinanceProductPasswordException;

use think\facade\Cache;

class LevelRepository extends Repository
{
    protected $model;
    protected $redis;

    public function __construct()
    {

        //绑定模型
        $this->model = new LevelModel();
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

    // vip类型  1-体验区 2-未推广代理区 3-已推广代理区 4-精选活动区
    const TYPE_EXPERIENCE = 1; // 体验区
    const TYPE_NORMAL = 2; // 未推广代理区
    const TYPE_PROMOTION = 3; // 已推广代理区
    const TYPE_ACTIVITY = 4; // 活动专区

    public static array $typeMap = [
        self::TYPE_EXPERIENCE => '体验区',
        self::TYPE_NORMAL => '未推广代理区',
        self::TYPE_PROMOTION => '已推广代理区',
        self::TYPE_ACTIVITY => '精选活动区',
    ];


    public function getLevelList()
    {
        $redis_key = config("redis_cache_key.level_list");
        if ($this->redis->has($redis_key)) {
            return $this->redis->get($redis_key);
        }
        return $this->redis->remember($redis_key, function () {
            return $this->order(['sort' => 'asc'])->selectWhere([['is_show', '=', 1]]);
        });
    }

    public function getLevelByLevelID($level_id)
    {
        $redis_key = config("redis_cache_key.level_info") . $level_id;
        return $this->redis->remember($redis_key, function () use ($level_id) {
            return $this->findWhere([['level_id', '=', $level_id], ['is_can_buy', '=', 1]]);
        }, 300);
    }

    public function getLevelByType($type)
    {
        $redis_key = config("redis_cache_key.level_password") . $type;
        return $this->redis->remember($redis_key, function () use ($type) {
            $where[] = ['type', '=', $type];
            $where[] = ['is_can_buy', '=', 1];
            return $this->findWhere($where);
        }, 300);
    }

    public function getLevelByTypes($type)
    {
        $redis_key = config("redis_cache_key.level_types") . $type;
        return $this->redis->remember($redis_key, function () use ($type) {
            $where[] = ['type', '=', $type];
            $where[] = ['is_can_buy', '=', 1];
            return $this->selectWhere($where);
        }, 300);
    }

    /**
     * 校验广告位编码
     * @throws FinanceProductPasswordException
     */
    public function checkPassword($password, $level_id, $type, $error_code = -1): array
    {
        $params = [
            'msg' => lang('编码不正确'),
            'errorCode' => 0,
            'extendedData' => [
                'code' => $error_code,
                'msg' => lang('编码不正确'),
            ]
        ];

        if (in_array($type, [LevelRepository::TYPE_NORMAL, LevelRepository::TYPE_PROMOTION])) {
            $level = $this->getLevelByType($type);
            //不需要校验密码的分区
            return [
                'expire_time' => $level->password_expire_time,
                'time_left' => $level->password_expire_time - time() - 1,
                'type' => $level->type,
                'code' => 0
            ];
        }
        // 验证编码是否正确
//        if ($level_id) {
//            $level = $this->getLevelByLevelID($level_id);
//            $levels = array_filter([
//                $this->getLevelByType($level->type)
//            ]);
//        } else {
        if ($type) {
            $levels = array_filter([
                $this->getLevelByType($type)
            ]);
        } else {
            $levels = array_filter([
                $this->getLevelByType(LevelRepository::TYPE_EXPERIENCE), // 体验区
                $this->getLevelByType(LevelRepository::TYPE_NORMAL), // 未推广代理区
                $this->getLevelByType(LevelRepository::TYPE_PROMOTION), // 已推广代理区
                $this->getLevelByType(LevelRepository::TYPE_ACTIVITY), // 活动区
            ]);
        }
//        }

        $matched = false;
        foreach ($levels as $item) {
            if ($item && $item->invest_password == $password) {
                $level = $item;
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            // 失败
            throw new FinanceProductPasswordException($params);
        }

        if ($level->password_expire_time < time()) {
            $params['msg'] = lang('编码已过期');
            $params['extendedData']['msg'] = lang('编码已过期');
            throw new FinanceProductPasswordException($params);
        }

        // 返回剩余时间信息
        return [
            'expire_time' => $level->password_expire_time,
            'time_left' => $level->password_expire_time - time() - 1,
            'type' => $level->type,
            'code' => 0
        ];
    }
}
