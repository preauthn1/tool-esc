<?php

namespace app\common\repository;

use app\common\model\InvestmentProductOrderModel;
use app\common\model\MoneyLogModel;

class InvestmentProductOrderRepository extends Repository
{
    protected $model;

    public function __construct()
    {

        //绑定模型
        $this->model = new InvestmentProductOrderModel();
    }


    // 状态 0-待匹配 1-待购买 2-已购买 3-已结算 4-已过期
    const STATUS_PENDING = 0; // 待匹配
    const STATUS_WAIT_BUY = 1; // 待购买
    const STATUS_PURCHASED = 2; // 已购买
    const STATUS_SETTLED = 3; // 已结算
    const STATUS_EXPIRED = 4; // 已过期

    public static array $statusMap = [
        self::STATUS_PENDING => '待匹配',
        self::STATUS_WAIT_BUY => '待购买',
        self::STATUS_PURCHASED => '已购买',
        self::STATUS_SETTLED => '已结算',
        self::STATUS_EXPIRED => '已过期',
    ];


    // AI智能托管
    public function settlement()
    {
        // 获取待结算订单
        $where = [
            'status' => self::STATUS_PURCHASED,
        ];
        $orders = $this->selectWhere($where);
        foreach ($orders as $order) {
            // 判断是否可以结算
            switch ($order->profit_type) { //  收益类型 1长期投资（到期本金与利息一起返）,2短期投资安装现有逻辑配置周期结算
                case 0: // 无

                    break;
                case 1: // 长期投资
                case 2: // 本息到期还
                    //结算逻辑一致 本金与利息一起结算
                    if ($order->end_time < time()) { // 到期了, 可以发放收益
                        $money_log = new MoneyLogModel();
                        $user_id = $order->user_id;
                        $money_log->changeMoney($user_id, $order->money,
                            MoneyLogModel::TYPE_BROKERAGE, 'add', 'AI智能托管本金', '', 13, '', '', $order->id);
                        $money_log->changeMoney($user_id, $order->profit_money,
                            MoneyLogModel::TYPE_BROKERAGE, 'add', 'AI智能托管收益', '', 14, '', '', $order->id);
                        $order->settlement_time = time();
//                        $order->settlement_times = 1;
                        $order->status = self::STATUS_SETTLED;
                        $order->save();
                    }
                    break;
            }
        }

    }
}
