<?php

namespace app\common\repository;

use app\admin\model\MemberModel;
use app\admin\model\RechargeModel;
use app\common\model\ConfigModel;
use app\common\model\CreditScoreTaskSummaryModel;
use app\common\model\FinanceProductOrderModel;
use app\common\model\MoneyLogModel;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

class CreditScoreTaskSummaryRepository extends Repository
{
    protected $model;

    public function __construct()
    {

        //绑定模型
        $this->model = new CreditScoreTaskSummaryModel();
    }

    /**
     * 新增任务汇总数据
     * @param  string  $type
     * @param  int  $number
     * @param  int  $leader1
     * @param  string  $date
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function add(string $type, int $number, int $leader1 = 0, $date = '')
    {
        $date = $date ?: date('Y-m-d');
        // 判断是否已经存在记录
        $task_summary = $this->model->where('user_id', $leader1)
            ->where('date', $date)
            ->find();
        if ($task_summary) {
            $task_summary->$type = $number;
            $task_summary->save();
        } else {
            $this->model->create([
                'user_id' => $leader1,
                'date'    => $date,
                $type     => $number,
            ]);
        }
    }

    /**
     * 获取任务汇总数据
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function summary($date = '')
    {
        if (!$date) {
            // 获取昨天的日期
            $date = date('Y-m-d');
        }
        print_r($date."\n");
        print_r("统计直推数据\n");
        $date_arr = [strtotime($date.'00:00:00'), strtotime($date.'23:59:59')];

        $date = str_replace("-", "", $date);
        //有效充值
        $recharge_num_valid_user = ConfigModel::getConfigValue('recharge_num_valid_user');

//         获取$date充值的用户
        $recharges = RechargeModel::hasWhere('user', [
            ['leader1', '>', 0],
            ['create_time', 'between', $date_arr]
        ])
            ->with([
                'user' => function ($query) {
                    $query->field('id, leader1, create_time, recharge_money');
                }
            ])
            ->where('recharge_model.create_time', 'between', $date_arr)
            ->where('recharge_model.status', RechargeModel::STATUS_SUCCESS)
            ->field('min(recharge_model.create_time) as min_create_time')
            ->group('recharge_model.user_id')
            ->select();
        $recharge_data = [];
        foreach ($recharges as $recharge) {
            // 获取最新待支付的广告位
            $wait_buy_finance_product_order = FinanceProductOrderModel::where('user_id', $recharge['user']['leader1'])
                ->where('status', 'in', [
                    FinanceProductOrderRepository::STATUS_WAIT_BUY,
                    FinanceProductOrderRepository::STATUS_PURCHASED,
                    FinanceProductOrderRepository::STATUS_SETTLED
                ])
                ->where('create_time', 'between', $date_arr)
                ->order('id desc')
                ->find();
            $overdue_money = 0;
            if ($wait_buy_finance_product_order) {
                $overdue_money = $wait_buy_finance_product_order->overdue_money;
            }
            // 获取上级余额
            $leader_money = MemberModel::where('id', $recharge['user']['leader1'])
                ->value('user_money');
            // 上级余额小于可透支金额，不加数据
            if ($leader_money < $overdue_money) {
                continue;
            }
            // 获取充值时间前后各一条上级的余额变动记录，判断用户充值时是否可以加数据
            $before_money = MoneyLogModel::where('user_id', $recharge['user']['leader1'])
                ->where('create_time', '<=', $recharge['min_create_time'])
                ->where('type', MoneyLogModel::TYPE_USER)
                ->order('id desc')
                ->value('last_money');

            if ($before_money < $overdue_money) {
                continue;
            }

            // 初始化 leader1 的键，如果它还不存在
            if (!isset($recharge_data[$recharge['user']['leader1']])) {
                $recharge_data[$recharge['user']['leader1']] = ['register_recharge' => 0];
            }

//             今天注册&& 今日充值 && 充值金额大于有效用户充值金额 && 今天要购买广告位
            $finance_num = (new FinanceProductOrderRepository)->getTodayFinanceNum([$recharge['user']['id']], true,false);
            if ($recharge['user']['recharge_money'] >= $recharge_num_valid_user && $finance_num > 0) {
                $recharge_data[$recharge['user']['leader1']]['register_recharge'] += 1;
            }

        }
        foreach ($recharge_data as $user_id => $data) {
            $this->add('register_recharge', $data['register_recharge'], $user_id, $date);
        }

        $match_data = [];
        $first_buy_finance_product_order_user = (new FinanceProductOrderRepository())->getFirstBuyUser($date_arr);
        $user_repository = (new HomeUserRepository());
        $finance_product_order_repository = (new FinanceProductOrderRepository());
        foreach ($first_buy_finance_product_order_user as $user_id => $session_no) {
            $user = $user_repository->findWhere(['id' => $user_id], ['id', 'leader1']);
            $leader1 = $user->leader1;

            // 获取广告位
            $finance_product_order = $finance_product_order_repository->findWhere([
                'user_id' => $user_id,
                'sort' => 1,
                'session_no' => $session_no
            ], ['user_id', 'sort', 'session_no', 'create_time']);
            // 获取最新待支付的广告位
            $wait_buy_finance_product_order = FinanceProductOrderModel::where('user_id', $leader1)
                ->where('status', 'in', [
                    FinanceProductOrderRepository::STATUS_WAIT_BUY,
                    FinanceProductOrderRepository::STATUS_PURCHASED,
                    FinanceProductOrderRepository::STATUS_SETTLED
                ])
                ->where('create_time', 'between', $date_arr)
                ->order('id desc')
                ->find();
            $overdue_money = 0;
            if ($wait_buy_finance_product_order) {
                $overdue_money = $wait_buy_finance_product_order->overdue_money;
            }
            // 获取上级余额
            $leader_money = MemberModel::where('id', $leader1)
                ->value('user_money');
            // 上级余额小于可透支金额，不加数据
            if ($leader_money < $overdue_money) {
                continue;
            }
            // 获取购买广告位时间前后各一条上级的余额变动记录，判断用户充值时是否可以加数据
            $before_money = MoneyLogModel::where('user_id',$leader1)
                ->where('create_time', '<=', strtotime($finance_product_order['create_time']))
                ->where('type', MoneyLogModel::TYPE_USER)
                ->order('id desc')
                ->value('last_money');

            if ($before_money < $overdue_money) {
                continue;
            }

            // 初始化 leader1 的键，如果它还不存在
            if (!isset($match_data[$leader1])) {
                $match_data[$leader1] = ['first_match_finance_product' => 0];
            }
            $match_data[$leader1]['first_match_finance_product'] += 1;
        }

        foreach ($match_data as $user_id => $data) {
            $this->add('first_match_finance_product', $data['first_match_finance_product'], $user_id, $date);
        }

        // 获取$date日匹配过广告位的用户
        $users = FinanceProductOrderModel::hasWhere('user', [
            ['leader1', '>', 0],
        ])
            ->with('user')
            ->where('finance_product_order_model.buy_time', 'between', $date_arr)
            ->where('finance_product_order_model.status', 'in',
                [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
            ->where('finance_product_order_model.level_id', '>', 0)
            ->field('min(finance_product_order_model.buy_time) as min_start_time')
            ->group('user_id')
            ->select();
        $task_date_level1 = [];
        $task_date_level2 = [];
        $task_date_level3 = [];
        foreach ($users as $task) {
            // 初始化 leader1 的键，如果它还不存在
            if (!isset($task_date_level1[$task['user']['leader1']]) && $task['user']['leader1'] > 0) {
                $task_date_level1[$task['user']['leader1']] = 0;
            }
            // 初始化 leader2 的键，如果它还不存在
            if (!isset($task_date_level2[$task['user']['leader2']]) && $task['user']['leader2'] > 0) {
                $task_date_level2[$task['user']['leader2']] = 0;
            }
            // 初始化 leader3 的键，如果它还不存在
            if (!isset($task_date_level3[$task['user']['leader3']]) && $task['user']['leader3'] > 0) {
                $task_date_level3[$task['user']['leader3']] = 0;
            }


            if ($task['user']['leader1'] > 0) {
                // 获取充值时间前后各一条上级的余额变动记录，判断用户充值时是否可以加数据
                $before_money = MoneyLogModel::where('user_id', $task['user']['leader1'])
                    ->where('create_time', '<=', $task['min_start_time'])
                    ->where('type', MoneyLogModel::TYPE_USER)
                    ->order('id desc')
                    ->value('last_money');

                // 获取最新待支付的广告位
                $wait_buy_finance_product_order = FinanceProductOrderModel::where('user_id', $task['user']['leader1'])
                    ->where('status', 'in', [
                        FinanceProductOrderRepository::STATUS_WAIT_BUY, FinanceProductOrderRepository::STATUS_PURCHASED,
                        FinanceProductOrderRepository::STATUS_SETTLED
                    ])
                    ->where('create_time', 'between', $date_arr)
                    ->order('id desc')
                    ->find();
                $overdue_money = 0;
                if ($wait_buy_finance_product_order) {
                    $overdue_money = $wait_buy_finance_product_order->overdue_money;
                }
                // 获取上级余额
                $leader_money = MemberModel::where('id', $task['user']['leader1'])
                    ->value('user_money');
                // 上级余额小于可透支金额，不加数据
                if ($before_money >= $overdue_money && $leader_money >= $overdue_money) {
                    $task_date_level1[$task['user']['leader1']] += 1;
                }
            }

            if ($task['user']['leader2'] > 0) {
                // 获取充值时间前后各一条上级的余额变动记录，判断用户充值时是否可以加数据
                $before_money = MoneyLogModel::where('user_id', $task['user']['leader2'])
                    ->where('create_time', '<=', $task['min_start_time'])
                    ->where('type', MoneyLogModel::TYPE_USER)
                    ->order('id desc')
                    ->value('last_money');
                // 获取最新待支付的广告位
                $wait_buy_finance_product_order = FinanceProductOrderModel::where('user_id', $task['user']['leader2'])
                    ->where('status', 'in', [
                        FinanceProductOrderRepository::STATUS_WAIT_BUY, FinanceProductOrderRepository::STATUS_PURCHASED,
                        FinanceProductOrderRepository::STATUS_SETTLED
                    ])
                    ->where('create_time', 'between', $date_arr)
                    ->order('id desc')
                    ->find();
                $overdue_money = 0;
                if ($wait_buy_finance_product_order) {
                    $overdue_money = $wait_buy_finance_product_order->overdue_money;
                }
                // 获取上级余额
                $leader_money = MemberModel::where('id', $task['user']['leader2'])
                    ->value('user_money');
                // 上级余额小于可透支金额，不加数据
                if ($before_money >= $overdue_money && $leader_money >= $overdue_money) {
                    $task_date_level2[$task['user']['leader2']] += 1;
                }

            }
            if ($task['user']['leader3'] > 0) {
                // 获取充值时间前后各一条上级的余额变动记录，判断用户充值时是否可以加数据
                $before_money = MoneyLogModel::where('user_id', $task['user']['leader3'])
                    ->where('create_time', '<=', $task['min_start_time'])
                    ->where('type', MoneyLogModel::TYPE_USER)
                    ->order('id desc')
                    ->value('last_money');

                // 获取最新待支付的广告位
                $wait_buy_finance_product_order = FinanceProductOrderModel::where('user_id', $task['user']['leader3'])
                    ->where('status', 'in', [
                        FinanceProductOrderRepository::STATUS_WAIT_BUY, FinanceProductOrderRepository::STATUS_PURCHASED,
                        FinanceProductOrderRepository::STATUS_SETTLED
                    ])
                    ->where('create_time', 'between', $date_arr)
                    ->order('id desc')
                    ->find();
                $overdue_money = 0;
                if ($wait_buy_finance_product_order) {
                    $overdue_money = $wait_buy_finance_product_order->overdue_money;
                }
                // 获取上级余额
                $leader_money = MemberModel::where('id', $task['user']['leader3'])
                    ->value('user_money');
                // 上级余额小于可透支金额，不加数据
                if ($before_money >= $overdue_money && $leader_money >= $overdue_money) {
                    $task_date_level3[$task['user']['leader3']] += 1;
                }
            }
        }
        ksort($task_date_level1);
        ksort($task_date_level2);
        ksort($task_date_level3);

        foreach ($task_date_level1 as $user_id => $value) {
            $this->add('task_level1', $value, $user_id, $date);
        }
        foreach ($task_date_level2 as $user_id => $value) {
            $this->add('task_level2', $value, $user_id, $date);
        }
        foreach ($task_date_level3 as $user_id => $value) {
            $this->add('task_level3', $value, $user_id, $date);
        }
    }
}
