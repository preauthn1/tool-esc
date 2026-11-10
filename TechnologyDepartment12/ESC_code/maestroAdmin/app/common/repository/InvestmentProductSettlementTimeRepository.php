<?php

namespace app\common\repository;

use app\common\model\ConfigModel;
use app\common\model\InvestmentProductSettlementTimeModel;
use app\lib\exception\InvestmentProductProductException;
use Carbon\Carbon;

class InvestmentProductSettlementTimeRepository extends Repository
{
    protected $model;

    public function __construct()
    {

        //绑定模型
        $this->model = new InvestmentProductSettlementTimeModel();
    }

    /**
     * @throws InvestmentProductProductException
     */
    public function getSettlementTime(): int
    {
        // 获取当前时间点
        $now_time = date('H:i:s');
        // 获取结算时间点
        $settlement_times = (new InvestmentProductSettlementTimeModel)->where('status', 1)
            ->order('settlement_time')
            ->column('settlement_time');
        if (!$settlement_times) {
            throw new InvestmentProductProductException();
        }
        $settlement_time = null;
        foreach ($settlement_times as $time) {
            if ($time > $now_time) {
                $settlement_time = Carbon::parse($time)->timestamp; // 可以结算
                break;
            }
        }
        if (!$settlement_time) {
            $settlement_time = Carbon::parse($settlement_times[0])->addDay()->timestamp;
        }
        return $settlement_time;
    }

    /**
     * 结算次数
     * @throws InvestmentProductProductException
     */
    public function getSettlementTimes($settlement_time): float
    {
        // 获取结算周期
        $investment_product_settlement_cycle = ConfigModel::getConfigValue('investment_product_settlement_cycle');
        if (!$investment_product_settlement_cycle) {
            throw new InvestmentProductProductException();
        }

        $diff_hours = Carbon::parse($settlement_time)->diffInHours(Carbon::now(), true);
        return intval($diff_hours / $investment_product_settlement_cycle);
    }
    /**
     * 结算周期
     * @throws InvestmentProductProductException
     */
    public function getCycle($times): float
    {
        // 获取结算周期
        $investment_product_settlement_cycle = ConfigModel::getConfigValue('investment_product_settlement_cycle');
        if (!$investment_product_settlement_cycle) {
            throw new InvestmentProductProductException();
        }

        return $times * $investment_product_settlement_cycle;

    }

    /**
     * 结算收益
     * @param $money
     * @param $profit
     * @param $times
     * @return float
     */
    public function getProfitMoney($money, $profit, $times): float
    {
        return round($money * $profit / 100 * $times, 2);
    }

}
