<?php

namespace app\common\model;

use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\LevelRepository;
use app\common\repository\TaskSessionRepository;
use Carbon\Carbon;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Model;
use think\model\relation\BelongsTo;

class PddTurntableCountModel extends Model
{
    protected $name = 'pdd_turntable_count';
    protected $autoWriteTimestamp = true;

    /**
     * 给上级增加抽奖次数
     * @param $user_id
     * @param $amount
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function addLeader1Count($user_id, $amount): bool
    {
        // 判断充值金额是否大于配置的值
        if (!$amount > ConfigModel::getConfigValue('recharge_num_valid_user')) {
            return false;
        }
        // 判断用户是否有上级
        $user_info = UserModel::where('id', $user_id)->find();
        // 判断用户是买过广告
        $finance_product_order_count = FinanceProductOrderModel::where('user_id', $user_id)
            ->where('status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
            ->count();
        if (isset($user_info['leader1'])) {
            if ($user_info['leader1'] > 0 && $finance_product_order_count > 0) {
                // 给上级增加抽奖次数
                $exists = self::where('user_id', $user_info['leader1'])->where('from_user_id', $user_id)->find();
                if (!$exists) {
                    self::create([
                        'user_id' => $user_info['leader1'],
                        'from_user_id' => $user_id,
                    ]);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 给自己增加抽奖次数
     * @param  int  $user_id
     * @param $order
     * @return bool
     */
    public static function addCount(int $user_id, $order): bool
    {
        $level_id = $order->level_id;
        $session_no = $order->session_no;
        $order_id = $order->id;
        $task_session = (new TaskSessionRepository())->getCurrentTaskSession();
        if (!$task_session) {
            // 当前不在广告位时段内
            return false;
        }
        $time_arr = [
            Carbon::parse(date('Y-m-d').' '.$task_session->start_time)->timestamp,
            Carbon::parse(date('Y-m-d').' '.$task_session->end_time)->timestamp
        ];
        // 判断用户是买过广告
        $finance_product_order_count = FinanceProductOrderModel::where('user_id', $user_id)
            ->where('match_time', 'between', $time_arr)
            ->count();

        if ($finance_product_order_count > 0) {
            // 获取时段内增加抽奖次数的记录
            try {
                $exists_order_session_nos = self::where('user_id', $user_id)
                    ->where('from_user_id', $user_id)
                    ->where('create_time', 'between', $time_arr)
                    ->find();
            } catch (\Exception $e) {
                $exists_order_session_nos = null;
            }

            // 如果存在就不再添加抽奖次数
            if ($exists_order_session_nos) {
                return false;
            }

            self::create([
                'user_id'      => $user_id,
                'from_user_id' => $user_id,
                'order_id'     => $order_id,
                'session_no'   => $session_no,
            ]);
            return true;
        }
        return false;
    }

    /**
     * @param $user_id int 用户uid
     * @param $turntable_id int 转盘活动id
     * @param $prize_id int 奖项id
     * @param $record_id int 中奖记录id
     * @param $value float
     * @param  $type int 1-金额 2-进度
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function useCount(int $user_id, int $turntable_id, int $prize_id, int $record_id, float $value, int $type)
    {
        // 找到第一条未使用的邀请记录
        $invite_info = self::where('user_id', $user_id)->where('turntable_id', 0)->find();
        if ($invite_info) {
            self::where('id', $invite_info->id)->update([
                'type' => $type,
                'turntable_id' => $turntable_id,
                'prize_id' => $prize_id,
                'record_id' => $record_id,
                'value' => $value,
                'update_time' => time()
            ]);
        }
    }


    /**
     * 获取用户可用抽奖次数
     * @param $user_id
     * @return int
     * @throws Exception
     */
    public static function getAvailableNum($user_id): int
    {
        return self::where([
            'user_id' => $user_id,
            'turntable_id' => 0,
        ])->count();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id', 'id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'from_user_id', 'id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(PddTurntableRecordModel::class, 'record_id', 'id');
    }
}
