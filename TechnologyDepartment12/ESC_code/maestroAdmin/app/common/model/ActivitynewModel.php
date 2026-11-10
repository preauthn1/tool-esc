<?php

namespace app\common\model;

use app\admin\model\MemberModel;
use app\admin\model\RechargeModel;
use app\common\repository\FinanceProductOrderRepository;
use think\facade\Cache;
use think\Model;
use think\model\relation\HasMany;
use think\console\Output;

class ActivitynewModel extends Model {

    protected $name = 'activitys';
    protected $autoWriteTimestamp = true;
//    protected $type = [
//        'start_time' => 'timestamp',
//        'end_time' => 'timestamp',
//    ];

    protected function setUserTypeAttr($value) {
        return isArrayAndNotEmpty($value) ? implode(",", $value) : '';
    }

    protected function getUserTypeAttr($value) {
        return is_string($value) ? explode(",", $value) : [];
    }
    protected function setTriggerTypeAttr($value) {
        return isArrayAndNotEmpty($value) ? implode(",", $value) : '';
    }

    protected function getTriggerTypeAttr($value) {
        return is_string($value) ? explode(",", $value) : [];
    }

    public function summaryInviteActivity(Output $output)
    {
        // 获取活动要求
        $activity = ActivitynewModel::where('type', 7)
            ->where('status', 1)
            ->find();
        if (!$activity) {
            return;
        }
        // 有效用户:充值加上购买广告位的
        //有效充值
        $recharge_num_valid_user = ConfigModel::getConfigValue('recharge_num_valid_user');
        $cache_prefix = config('cache.stores.redis.prefix');
        $redis_lock_key_prefix = $cache_prefix.'invite_activity_lock_'.$activity['id'].'##';

        $apiRedis = Cache::store("redis")->handler();
        $keys = $apiRedis->keys($redis_lock_key_prefix.'*');
        $user_ids = [0];
        foreach ($keys as $key) {
            $user_ids[] = explode('##', $key)[1];
        }
        // 获取活动期间有 leader1 的注册用户
        $users = MemberModel::hasWhere('financeProductOrder', [
            ['status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]],
            ['level_id', '>', 0]
        ])
            ->where('leader1', '>', 0)
//            ->where('recharge_money', '>=', $recharge_num_valid_user)// 限制条件修改
            ->where('member_model.create_time', 'between', [$activity['start_time'], $activity['end_time']])
            ->where('member_model.id', 'not in', $user_ids)
            ->field('member_model.id')
            ->field('member_model.id,leader1')
            ->select();
        // 循环用户，给上级增加活动记录
        foreach ($users as $user) {
            $effectiveRecharge =  RechargeModel::where("user_id" ,$user['id'])
                ->where("status" , RechargeModel::STATUS_SUCCESS)->sum("original_money");
            if ($effectiveRecharge < $recharge_num_valid_user) {
                continue ;
            }
            $output->info( $user['id']."--------满足条件-------".$effectiveRecharge);
            // 用户充值并且完成广告位
            $exists = ActivitynewRecordModel::where('user_id', $user['leader1'])
                ->where('from_user_id', $user['id'])
                ->where('activity_id', $activity['id'])
                ->where('reward_type', $activity['reward_type'])
                ->find();
            $redisKey = $redis_lock_key_prefix.$user['id'];
            if ($exists) {
                $apiRedis->set($redisKey, 1);
                // 加入缓存，不再查询该用户
                continue;
            }
            ActivitynewRecordModel::create([
                'is_xuni'      => $user['is_xuni'],
                'activity_id'  => $activity['id'],
                'user_id'      => $user['leader1'],
                'from_user_id' => $user['id'],
                'reward'       => $activity['reward'],
                'reward_type'  => $activity['reward_type'],
                'recharge_bonus_id'=>0,//
                'recharge_money' => $effectiveRecharge,// 用户充值
                'status'       => 0,
            ]);
            $apiRedis->set($redisKey, 1);
        }
    }

    public function records(): HasMany
    {
        return $this->hasMany(ActivitynewRecordModel::class, 'activity_id', 'id');
    }
}
