<?php

namespace app\common\repository;

use app\admin\model\MemberModel;
use app\common\model\FinanceProductOrderModel;
use app\common\model\InvestmentProductOrderModel;
use app\common\model\UserModel;
use Carbon\Carbon;


class HomeUserRepository extends Repository
{
    protected $model;

    public function __construct()
    {

        //绑定模型
        $this->model = new UserModel();
    }


    // 获取7级下级人数
    public function getLevel7TeamCount($user_id): array
    {
        for ($i = 0; $i <= 7; $i++) {
            if ($i == 0) {
                $level_team_ids = UserModel::where('tree_id', 'like', "%/{$user_id}/%")
                    ->where('tree_id', 'not like', '%/' . $user_id . '/')
                    ->column('id');
            } else {
                $level_team_ids = UserModel::getTeamIds($user_id, $i);
            }
            $level_team_count[$i] = count($level_team_ids);
        }
        return $level_team_count;
    }

// 三方充值总额
    public function getTeamThirdRechargeSuccessAmount($user_ids): float
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return (new RechargeRepository())->getTeamRechargeSuccessAmount($user_ids, ['type' => RechargeRepository::TYPE_THIRD]);
    }

    // 三方充值总额+合约+线下
    public function getTeamThirdRechargeSuccessAmountAll($user_ids): float
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return (new RechargeRepository())->getTeamRechargeSuccessAmount($user_ids, [['from', 'in', [RechargeRepository::FROM_FRONT]]]);
    }


    // 三方提现总额
    public function getTeamThirdWithdrawalSuccessAmount($user_ids): float
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return (new WithdrawalRepository())->getTeamWithdrawalSuccessAmount($user_ids);
    }

    // 当日充值总额
    public function getTeamTodayRechargeAmount($user_ids): float
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return (new RechargeRepository())->getTeamTodayRechargeAmount($user_ids, [['from', 'in', [RechargeRepository::FROM_FRONT]]]);
    }

    // 当日充值总人数
    public function getTeamTodayRechargeCount($user_ids): float
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return (new RechargeRepository())->getTeamTodayRechargeCount($user_ids);
    }

    // 当日复充人数
    public function getTeamTodayRepeatRechargeRechargeCount($user_ids): int
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return (new RechargeRepository())->getTeamTodayRepeatRechargeRechargeCount($user_ids);
    }

    // 当日基础金额
    public function getTeamUserMoney($user_ids): float
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        $where = [['id', 'in', $user_ids], 'is_xuni' => 0];
        return $this->model->where($where)->sum('user_money');
    }

    // 当日首充金额
    public function getTeamTodayFirstRechargeMoney($user_ids)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return (new RechargeRepository())->getTeamTodayFirstRechargeMoney($user_ids, [['from', 'in', [RechargeRepository::FROM_ADMIN, RechargeRepository::FROM_FRONT]]]);
    }

    // 当日首充金额
    public function getTeamTodayFirstRechargeCount($user_ids): int
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return (new RechargeRepository())->getTeamTodayFirstRechargeCount($user_ids);
    }

    // 当日提现总额
    public function getTeamTodayWithdrawalMoney($user_ids): float
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return (new WithdrawalRepository())->getTeamTodayWithdrawalMoney($user_ids);
    }

    // 当日提现人数
    public function getTeamTodayWithdrawalCount(array $user_ids): int
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return (new WithdrawalRepository())->getTeamTodayWithdrawalCount($user_ids);
    }

    // 当日已出款金额
    public function getTeamTodayThirdWithdrawalMoney($user_ids): float
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return (new WithdrawalRepository())->getTeamTodayThirdWithdrawalMoney($user_ids);
    }

    // 当日首充人数 这边指的是当日注册当日充值的账号
    public function getTeamTodayRegisterAndFirstRechargeCount(array $user_ids)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return (new RechargeRepository())->getTeamTodayRegisterAndFirstRechargeCount($user_ids);
    }

    // 今日新增注册量
    public function getTeamTodayRegisterCount($user_ids, $include_xuni = false): int
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        $now = Carbon::now();
        $where = [
            ['id', 'in', $user_ids],
            ['create_time', 'between', [$now->startOfDay()->timestamp, $now->endOfDay()->timestamp]]
        ];
        if (!$include_xuni) {
            $where[] = ['is_xuni', '=', 0];
        }
        return $this->model->where($where)->count();
    }

    // 团队活跃人数 流量包和AI未结算的
    public function getTeamActiveCount($user_ids, $include_xuni = false)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        $where = [
            ['user_id', 'in', $user_ids],
        ];
        if (!$include_xuni) {
            $where[] = ['is_xuni', '=', 0];
        }
        // AI托管冻结金额
        $ai_money = InvestmentProductOrderModel::hasWhere('user')
            ->where($where)
            ->where('settlement_time', '>', time())
            ->distinct('user_id')->count();
        // 广告库存
        $ad_money = FinanceProductOrderModel::hasWhere('user')
            ->where($where)
            ->where('buy_time', 'between', [Carbon::now()->startOfDay()->timestamp, Carbon::now()->endOfDay()->timestamp])
            ->distinct('user_id')->count();
        return $ai_money + $ad_money;
    }

    // 冻结金额
    public function getTeamFreezeMoney($user_ids, $include_xuni = false): float
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        $where = [
            ['user_id', 'in', $user_ids],
        ];
        if (!$include_xuni) {
            $where[] = ['is_xuni', '=', 0];
        }
        // 广告库存
        $ad_money = FinanceProductOrderModel::hasWhere('user')
            ->where($where)
            ->where('finance_product_order_model.status', 'in', [
                FinanceProductOrderRepository::STATUS_PURCHASED,
            ])
            ->sum('price');
//        $user_money = MemberModel::alias('member_model')->where('id', 'in', $user_ids)->sum('user_money');// 2025-9-19 加上用户基础余额
        // AI托管冻结金额
        $ai_money = InvestmentProductOrderModel::hasWhere('user')
            ->where($where)
            ->where('investment_product_order_model.status', 'in', [
                InvestmentProductOrderRepository::STATUS_PURCHASED,
            ])
            ->sum('money');
        return $ai_money + $ad_money;
    }

    public function getTeamTodayTaskUserCount(array $user_ids)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return (new FinanceProductOrderRepository())->getTeamTodayTaskUserCount($user_ids);
    }

    /**
     * 提现状态调整
     * @param $user_id
     * @param int $status 0禁止 1开启
     * @return bool
     */
    public function withdrawStatus($user_id, $status = 0)
    {
        //status 1开启 0禁止
        if (!in_array($status, [0, 1])) {
            return false;
        }
        $ret = $this->model->where('id', $user_id)->update(['withdraw_status' => $status]);
        if ($ret == false) {
            return false;
        }
        return true;
    }
}
