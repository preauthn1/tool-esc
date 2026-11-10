<?php

namespace app\common\repository;

use app\admin\model\MemberModel;
use app\common\model\ConfigModel;
use app\common\model\PromotionRewardDayModel;
use app\common\model\PromotionRewardModel;
use app\common\model\PromotionRewardRecordModel;
use Carbon\Carbon;
use think\facade\Cache;

/**
 * Class PromotionRewardRepository
 */
class PromotionRewardRepository extends Repository
{
    protected $model;
    protected $redis;
    protected $cache_prefix;

    public function __construct()
    {

        //绑定模型
        $this->model = new PromotionRewardModel();
        $this->redis = Cache::store('redis')->handler();
        $this->cache_prefix = config('cache.stores.redis.prefix');
    }

    public function addReward()
    {
        $date = date('Y-m-d');
        // 有效用户:充值加上购买广告位的
        //有效充值
        $recharge_num_valid_user = ConfigModel::getConfigValue('recharge_num_valid_user');
        $redis_lock_key_prefix = $this->cache_prefix . 'promotion_reward_lock_' . $date . '##';
        $keys = $this->redis->keys($redis_lock_key_prefix . '*');
        $user_ids = [];
        foreach ($keys as $key) {
            $user_ids[] = explode('##', $key)[1];
        }
        // 判断今天是否有邀请新用户
        // 获取$date有leader1的注册用户
        $users = MemberModel::hasWhere('financeProductOrder', [
            ['status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]],
            ['level_id', '>', 0]
        ])
            ->where('leader1', '>', 0)
            ->where('recharge_money', '>=', $recharge_num_valid_user)
            ->where('member_model.create_time', 'between', [Carbon::parse($date)->startOfDay()->timestamp, Carbon::parse($date)->endOfDay()->timestamp])
            ->where('member_model.leader1', 'not in', $user_ids)
            ->field('member_model.id')
            ->field('member_model.id,leader1')
            ->select();
        $promotion_reward_record_repository = new PromotionRewardRecordRepository();
        foreach ($users as $user) {
            $leader_id = $user->leader1;
            trace_log('用户' . $leader_id . '开始发放奖励...');
            // 判断今天是否已经给上级增加个了奖励
            if ($promotion_reward_record_repository->getTodayRewardRecordByUserId($leader_id, $date)) {
                // 今天已经给上级增加奖励了 跳过
                trace_log('用户' . $user->id . '，' . $date . '已经执行过了，跳过');
                continue;
            }

            $redis_lock_key = $redis_lock_key_prefix . $leader_id;
            // 获取今天剩余时间秒数
            $expire = Carbon::now()->diffInSeconds(Carbon::now()->endOfDay());
            // 增加奖励，
            if ($this->addUserRecord($leader_id, $date)) {
                trace_log('用户' . $leader_id . '奖励发放成功');
                $this->redis->set($redis_lock_key, 1, $expire);
            }
        }
    }

    public function addUserRecord($user_id, $date, $sign_id = 0): bool
    {
        // 获取当前配置
        $sign_data = PromotionRewardModel::where('id', $sign_id)->where('status', 1)->find();
        if (empty($sign_data)) {
            $sign_data = PromotionRewardModel::where('status', 1)->find();
            if (empty($sign_data)) {
                return false;
            }
            $sign_id = $sign_data['id'];
        }

        $cycle_day = $sign_data['day'];
        // 获取奖励
        $award_data = PromotionRewardDayModel::where('sign_id', $sign_id)->column('award,award_type', 'day');
        // 获取上一次签到
        $last_sign_record = PromotionRewardRecordModel::where('user_id', $user_id)->order('id desc')->find();
        if ($last_sign_record && $date == $last_sign_record['day_time']) {
            return false;
        }
        if ($last_sign_record && date("Y-m-d") == date("Y-m-d",
                strtotime($last_sign_record['day_time']) + (3600 * 24))) {
            $day = $last_sign_record['day'] + 1;
            if ($day > $cycle_day) {
                $day = 1;
            }
        } else {
            $day = 1;
        }
        if (isset($award_data[$day])) {
            $award = $award_data[$day];
        } else {
            $award = $award_data[1];
        }
        $data = [
            'user_id' => $user_id,
            'sign_id' => $sign_id,
            'day' => $day,
            'award' => $award['award'],
            'award_type' => $award['award_type'],
            'day_time' => $date,
            'status' => 0,
        ];
        $result = PromotionRewardRecordModel::create($data);
        if ($result) {
            return true;
        }
        return false;
    }
}
