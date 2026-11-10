<?php
declare (strict_types = 1);

namespace app\listener;

use app\common\model\AdPlanModel;
use app\common\model\ConfigModel;
use app\common\model\MoneyLogModel;
use app\common\model\UserModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\MemberWaterRepository;
use app\event\Promotion;
use think\facade\Queue;

class RechargeSuccess
{
    /**
     * 事件监听处理
     *
     */
    public function handle($order): void
    {
        // 充值活动
        $this->rechargeActivity($order);

        if ($order['is_first']) {
            $this->firstRecharge($order);
        }
        // 恢复已匹配未支付的广告位订单
        (new FinanceProductOrderRepository())->recoverOrder($order['user_id']);
    }

    private function rechargeActivity($order): void
    {
        if (array_key_exists('activity_id', $order) && $order['activity_id']) {
            Queue::push('Activity', $order);
        }
        $admin_id = $order['admin_id'] ?? 0;
        (new MemberWaterRepository())->addWater($order['user_id'], 1, $order['id'], $order['money'], $admin_id);
    }

    private function firstRecharge($order)
    {
        $user_id = $order['user_id'];
        $amount = $order['money'];
        AdPlanModel::updateUserPlanLevel($user_id); // 需要以首充为执行计划的时间起点 2025年3月27日

        $user_model = new UserModel();
        $user = $user_model->getOneUser($user_id);

        // 判断是否有首充送款
        $config = new ConfigModel();
        $configInfo = $config->getConfig();
        $user_create_date = explode(' ', $user->create_time)[0];
        if (is_numeric($order['create_time'])) {
            $order_create_date = date('Y-m-d', $order['create_time']);
        } else {
            $order_create_date = explode(' ', $order['create_time'])[0];
        }
        if ($configInfo['is_open_first_charge_rebate'] && $user_create_date == $order_create_date) { // 首充送款 当日注册当日充值才送款
            if ($amount >= $configInfo['first_charge_rebate_min'] && $amount <= $configInfo['first_charge_rebate_max']) {
                // 赠送金额
                $rebate = $amount * $configInfo['first_charge_rebate_percent'] / 100;
                if ($configInfo['first_charge_rebate_cap'] > 0 && $rebate > $configInfo['first_charge_rebate_cap']) {
                    // 超出首充送款封顶，取首充送款封顶
                    $rebate = $configInfo['first_charge_rebate_cap'];
                }
                $money_log = new MoneyLogModel();
                $money_log->changeMoney($user_id, $rebate, MoneyLogModel::TYPE_USER, 'add', '首充送款',
                    '首充送款：'.$rebate, 4, '', '', $order['id']);
            }

        }

        Promotion::handle($user, 'recharge', ['value' => $amount]);
    }
}
