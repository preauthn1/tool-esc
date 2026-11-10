<?php

namespace app\common\repository;

use app\admin\model\WithdrawModel;
use app\common\model\ConfigModel;
use app\common\model\PaymentPlatformModel;

use Carbon\Carbon;
use think\Exception;
use think\facade\Log;

class WithdrawalRepository extends Repository
{
    protected $model;

    public function __construct()
    {

        //绑定模型
        $this->model = new WithdrawModel();
    }

    const TYPE_USDT = 1; // 虚拟币
    const TYPE_BANK = 2; // 货币

    const STATUS_PENDING = 0; // 待处理
    const STATUS_WAIT = 1; // 待审核
    const STATUS_AGREE = 2; // 审核通过
    const STATUS_REJECT = 3; // 审核失败
    const STATUS_SUCCESS = 4; // 已出款
    const STATUS_FAIL = 5; // 出款失败
    const STATUS_OFFLINE_FAIL = 6; // 线下出款失败
    const STATUS_RESTRICTED = 7; // 卡审核


    // 转移订单到待审核页面
    public function movieToWait()
    {
        // 获取所有待处理订单
        $orders = $this->selectWhere([['status' , '=', self::STATUS_PENDING]]);
        $withdraw_check_hour = ConfigModel::getConfigValue('withdraw_check_hour');
        foreach ($orders as $order) {
            // 如果创建时间 > $config, 设置状态为待审核
            if (time() - strtotime($order['create_time']) > $withdraw_check_hour * 3600) {
                $order->status = self::STATUS_WAIT;
                $order->save();
            }
        }
    }

    // 自动出款
    public function autoPayment()
    {
        $auto_payment_safety_value = ConfigModel::getConfig('auto_payment_safety_value');
        $auto_payment = ConfigModel::getConfig('auto_payment_safety_value');
        // 获取待审核订单
        if ($auto_payment) { // 开启自动出款
            $orders = $this->selectWhere([['status' , '=', self::STATUS_WAIT]]);
            foreach ($orders as $order) {
                // 如果订单金额 < $auto_payment_safety_value, 自动出款
                if ($order['convert_money'] < $auto_payment_safety_value) {
                    // 自动出款
                    try {
                        PaymentPlatformModel::withdraw($order->user_id, $order->channel, $order['convert_money'],
                            $order->order_id);
                    } catch (Exception $e) {
                        Log::write($e->getMessage()."\n".$e->getTraceAsString(), "error");
                    }
                }
            }
        }
    }

    public function getTeamWithdrawalSuccessAmount($user_ids, $where = [], $include_xuni = false): float
    {
        $default_where = [['user_id', 'in', $user_ids], 'status' => self::STATUS_SUCCESS];
        $new_where = array_merge($default_where, $where);
        return $this->getWithdrawalMoney($new_where, $include_xuni);
    }

    public function getWithdrawalMoney($where = [], $include_xuni = false): float
    {
        $default_where = [];
        $new_where = array_merge($default_where, $where);
        if (!$include_xuni) {
            $new_where[] = ['is_xuni', '=', 0];
        }
        return $this->model->where($new_where)->sum('original_money');
    }

    // 当日提现总额
    public function getTeamTodayWithdrawalMoney($user_ids, $where = [], $include_xuni = false): float
    {
        $default_where = [
            ['user_id', 'in', $user_ids],
            'status' => self::STATUS_SUCCESS,
            ['pay_time', 'between', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]]
        ];
        $new_where = array_merge($default_where, $where);
        if (!$include_xuni) {
            $new_where[] = ['is_xuni', '=', 0];
        }
        return $this->model->where($new_where)->sum('original_money');
    }

    public function getTeamTodayWithdrawalCount($user_ids, $where = [], $include_xuni = false): int
    {
        $now = Carbon::now();
        $default_where = [
            ['user_id', 'in', $user_ids],
            'status' => self::STATUS_SUCCESS,
            ['pay_time', 'between', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]]
        ];
        $new_where = array_merge($default_where, $where);
        if (!$include_xuni) {
            $new_where[] = ['is_xuni', '=', 0];
        }
        return $this->model->where($new_where)->distinct('user_id')->count();
    }

    public function getTeamTodayThirdWithdrawalMoney($user_ids, $where = [], $include_xuni = false)
    {
        $default_where = [
            ['user_id', 'in', $user_ids],
            'status' => self::STATUS_SUCCESS,
            ['pay_time', 'between', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]],
            ['channel', '<>', 30] // 30 线下转账
        ];
        $new_where = array_merge($default_where, $where);
        return $this->getWithdrawalMoney($new_where, $include_xuni);
    }

}
