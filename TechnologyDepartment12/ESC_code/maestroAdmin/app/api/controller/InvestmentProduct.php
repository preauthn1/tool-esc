<?php

namespace app\api\controller;

use app\api\transform\InvestmentProductOrderTransform;
use app\api\transform\InvestmentProductTransform;
use app\common\model\InvestmentProductModel;
use app\common\model\InvestmentProductOrderModel;
use app\common\model\MoneyLogModel;
use app\common\repository\InvestmentProductOrderRepository;
use app\common\repository\InvestmentProductSettlementTimeRepository;
use app\common\repository\MoneyLogRepository;
use app\lib\exception\InvestmentProductProductException;
use app\lib\exception\LockException;
use Carbon\Carbon;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\response\Json;
use util\Locker;

class InvestmentProduct extends Base
{
    public function index(): Json
    {
        // 获取投资产品列表
        $products = InvestmentProductModel::where('status', 1)->order('sort desc')->select();
        $data = (new InvestmentProductTransform())->transformCollection($products->toArray());
        return json(msg(0, $data, lang('获取成功')));
    }

    /**
     * 获取用户收益
     * @return Json
     */
    public function detail(): Json
    {
        return json(msg(0, [
            'yesterday_profit' => i18n_sprintf((new MoneyLogRepository())->getYesterdayMoney([$this->userid], [14])),
            'total_profit' => i18n_sprintf((new MoneyLogRepository())->getTotalMoney([$this->userid], [14])),
        ], lang('获取成功')));
    }

    /**
     * 购买AI智能托管
     * @return Json
     * @throws DataNotFoundException
     * @throws LockException
     * @throws ModelNotFoundException
     * @throws InvestmentProductProductException
     * @throws \think\db\exception\DbException
     */
    public function buy(): Json
    {
        (new Locker('investment_lock_' . $this->userid))->lock();
        $user_id = $this->userid;

        $param = $this->request->param();
        $user_amount = $param[MoneyLogModel::$TYPE_FIELDS[MoneyLogModel::TYPE_USER]] = $this->userInfo[MoneyLogModel::$TYPE_FIELDS[MoneyLogModel::TYPE_USER]];

        // 获取选中id的产品
        $product = InvestmentProductModel::find($param['id']);
        $param['product'] = $product;
        $param['user_id'] = $user_id;
        $result = $this->validate($param, 'InvestmentProductValidate');
        if (true !== $result) {
            return json(msg(-1, '', lang($result)));
        }
        //最小限制金额
        $min_balance = $product->min_balance;
        if ($user_amount < $min_balance) {
            return json(msg(-1, '', lang('托管金额不足')));
        }
        $amount = $param['amount'];
        $profit = $product->profit;
        $cycle = 0;
        $settlement_time = 0;
        $settlement_times = 0;
        $profit_money = 0;
        if ($product->profit_type == 1) {
            //长期投资 按照配置周期结算
            $cycle = $product->cycle;
            $settlement_time = time() + ($cycle * 86400);
            $profit_money = $amount * $profit / 100;
            $settlement_times = 1;
        }
        if ($product->profit_type == 2) {
            //短期投资
            $investment_product_settlement_time_repository = new InvestmentProductSettlementTimeRepository;
            // 获取收益结算时间点配置
            $settlement_time = $investment_product_settlement_time_repository->getSettlementTime();
            $settlement_times = $investment_product_settlement_time_repository->getSettlementTimes($settlement_time);
            $cycle = $investment_product_settlement_time_repository->getCycle($settlement_times);
            $profit_money = $investment_product_settlement_time_repository->getProfitMoney($amount, $profit,
                $settlement_times);
        }
        if (empty($cycle)) {
            return json(msg(-1, '', lang('当前处于结算周期阶段，暂不支持转入')));
        }
        // 先扣款
        $money_log_model = new MoneyLogModel();
        $res = $money_log_model->changeMoney($this->userid, $amount, MoneyLogModel::TYPE_USER, 'sub', 'AI智能托管', '',
            13,
            '', '', '', true, $this->userInfo['is_xuni'], true);
        if ($res['code'] !== 1) {
            return json(msg(-1, '', lang($res['msg'])));
        }
        // 扣款成功，生成订单
        $order = InvestmentProductOrderModel::create([
            'order_no' => genOrderSN(random(6, 'number'), 'IP'),
            'user_id' => $user_id,
            'level_id' => $product->level_id,
            'cycle' => $cycle,
            'end_time' => $settlement_time,
            'money' => $amount,
            'investment_product_id' => $product->id,
            'investment_product_name' => $product->product_name,
            'profit' => $profit,
            'profit_money' => $profit_money,
            'profit_type' => $product->profit_type, // 收益类型 1长期投资（到期本金与利息一起返）,2短期投资安装现有逻辑配置周期结算
            'start_time' => time(),
            'settlement_times' => $settlement_times,
            'settlement_time' => 0,
            'status' => InvestmentProductOrderRepository::STATUS_PURCHASED,
        ]);
        // 购买成功
        return json(msg(0, [], lang('购买成功')));
    }

    /**
     * 待购买的广告位列表
     * @param InvestmentProductOrderTransform $transform
     * @param InvestmentProductOrderRepository $orderRepository
     * @return Json
     */
    public function waitingPayList(
        InvestmentProductOrderTransform $transform,
        InvestmentProductOrderRepository $orderRepository
    ): Json
    {
        $where = [];
        $where[] = ['status', 'in', [InvestmentProductOrderRepository::STATUS_WAIT_BUY]];
        $where[] = ['end_time', '>', time()];
        $orders = $orderRepository->selectWhere($where);
        $data = $transform->transformCollection($orders);
        return json(msg(0, $data, lang('获取成功')));
    }

    public function order_list(InvestmentProductOrderTransform $transform): Json
    {
        $page = input('page', 1);
        $size = input('limit', 10);
        $status = input('status', '');
        $where = [];
        $where[] = ['status', '=', $status];
        $where[] = ['user_id', '=', $this->userid];
        $orderRepository = new InvestmentProductOrderRepository();
        $list = $orderRepository
            ->with(['product'])
            ->order(['start_time' => 'desc'])
            ->where($where)
            ->paginate(['page' => $page, 'list_rows' => $size]);
        $data = $transform->transformPages($list->toArray());
        return json(msg(0, $data, lang('获取成功')));
    }

    /**
     * 理财订单统计
     * @param InvestmentProductOrderRepository $orderRepository
     * @return Json
     */
    public function order_count(InvestmentProductOrderRepository $orderRepository): Json
    {

        $where = [
            ['user_id', '=', $this->userid],
            [
                'status',
                'in',
                [InvestmentProductOrderRepository::STATUS_PURCHASED, InvestmentProductOrderRepository::STATUS_SETTLED]
            ],
        ];
        $orders = $orderRepository->selectWhere($where, ['id', 'money', 'profit_money', 'end_time']);
        $money = collect($orders)->column('money');
        $profit_money = collect($orders)->column('profit_money');


        $yesterday_where = [
            ['user_id', '=', $this->userid],
            [
                'status',
                'in',
                [InvestmentProductOrderRepository::STATUS_PURCHASED, InvestmentProductOrderRepository::STATUS_SETTLED]
            ],
            [
                'end_time',
                'between',
                [Carbon::now()->subDay()->startOfday()->timestamp, Carbon::now()->subDay()->endOfday()->timestamp]
            ]
        ];
        $yesterday_orders = $orderRepository->selectWhere($yesterday_where,
            ['id', 'money', 'profit_money', 'end_time']);;
        $yesterday_money = collect($yesterday_orders)->column('money');
        $yesterday_profit_money = collect($yesterday_orders)->column('profit_money');
        $sum_money = array_sum($money);
        $data = [
            'order_money' => sprintf4f($sum_money), // 理财订单总金额
            'order_num' => count($orders), // 理财订单数量
            'total_invest_income' => sprintf4f(array_sum($profit_money) - $sum_money), // 理财收益总金额
            'yesterday_order_income' => sprintf4f(array_sum($yesterday_profit_money) - array_sum($yesterday_money)),// 昨日理财收益金额
        ];
        return json(msg(0, $data, lang('获取成功')));
    }
}
