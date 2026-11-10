<?php

namespace app\common\repository;

use app\admin\model\MemberModel;
use app\admin\model\RechargeModel;
use app\common\model\ConfigModel;
use app\common\model\FinanceProductOrderModel;
use app\common\model\MoneyLogModel;
use app\common\model\RecommendModel;
use app\common\model\TaskModel;
use app\common\model\TaskSummaryModel;
use app\common\model\UserModel;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

class TaskSummaryRepository extends Repository
{
    protected $model;

    public function __construct()
    {

        //绑定模型
        $this->model = new TaskSummaryModel();
    }

    /**
     * 新增任务汇总数据
     * @param string $type
     * @param int $number
     * @param int $leader1
     * @param string $date
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
                'date' => $date,
                $type => $number,
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
        print_r($date . "\n");
        print_r("统计直推数据\n");
        $date_arr = [strtotime($date . '00:00:00'), strtotime($date . '23:59:59')];

        $date = str_replace("-", "", $date);
        //有效充值
        $recharge_num_valid_user = ConfigModel::getConfigValue('recharge_num_valid_user');
        // 获取$date有leader1的注册用户
        $users = MemberModel::where('leader1', '>', 0)
            ->where('recharge_money', '>=', $recharge_num_valid_user)
            ->where('create_time', 'between', $date_arr)
            ->field('count(id) as count, leader1')
            ->group('leader1')
            ->select();
        foreach ($users as $user) {
            $this->add('register', $user['count'], $user['leader1'], $date);
        }

//         获取$date充值的用户
        $recharges = RechargeModel::hasWhere('user', [['leader1','>', 0]])
            ->with([
                'user' => function ($query) {
                    $query->field('id, leader1, create_time, recharge_money');
                }
            ])
            ->where('recharge_model.create_time', 'between', $date_arr)
            ->where('recharge_model.status', RechargeModel::STATUS_SUCCESS)
            ->group('recharge_model.user_id')
            ->select();
        $recharge_data = [];
        foreach ($recharges as $recharge) {
            // 初始化 leader1 的键，如果它还不存在
            if (!isset($recharge_data[$recharge['user']['leader1']])) {
                $recharge_data[$recharge['user']['leader1']] = ['recharge' => 0, 'register_recharge' => 0];
            }

            // 现在可以安全地递增 recharge 计数
            $recharge_data[$recharge['user']['leader1']]['recharge'] += 1;
            // 今天注册&& 今日充值 && 充值金额大于有效用户充值金额
            if (date('Ymd', strtotime($recharge['create_time'])) == $date
                && date('Ymd', strtotime($recharge['user']['create_time'])) == $date
                && $recharge['user']['recharge_money'] >= $recharge_num_valid_user) {
                $recharge_data[$recharge['user']['leader1']]['register_recharge'] += 1;
            }
        }
        foreach ($recharge_data as $user_id => $data) {
            $this->add('recharge', $data['recharge'], $user_id, $date);
            $this->add('register_recharge', $data['register_recharge'], $user_id, $date);
        }

        // 获取$date日匹配过广告位的用户
        $users = FinanceProductOrderModel::hasWhere('user', [
            ['leader1','>', 0],
        ]) ->with('user')
            ->where('finance_product_order_model.create_time', 'between', $date_arr)
            ->where('finance_product_order_model.status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
            ->where('finance_product_order_model.level_id', '>', 0)
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

            // 现在可以安全地递增 recharge 计数
            // 获取用户当天之前的充值总额
//            $recharge_money = (new RechargeRepository())->model()
//                ->where('user_id', $task['user_id'])
//                ->whereTime('create_time', 'between', [strtotime('2024-12-20 00:00:00'), $date_arr[1]])
//                ->sum('money');
//            if ($recharge_money >= ConfigModel::getConfigValue('recharge_num_valid_user')) {
            // 充值总额大于有效用户充值金额，才算有效任务人数
//                $task_date_level1[$task['user']['leader1']] += 1;
//                if ($task['user']['leader2'] > 0) {
//                    $task_date_level2[$task['user']['leader2']] += 1;
//                }
//                if ($task['user']['leader3'] > 0) {
//                    $task_date_level3[$task['user']['leader3']] += 1;
//                }
//            }

            $task_date_level1[$task['user']['leader1']] += 1;
            if ($task['user']['leader2'] > 0) {
                $task_date_level2[$task['user']['leader2']] += 1;
            }
            if ($task['user']['leader3'] > 0) {
                $task_date_level3[$task['user']['leader3']] += 1;
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

    /**
     * 结算任务汇总数据
     * @param string $date
     * @return boolean
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function settleSummary($date = '')
    {
        $recommend_info = RecommendModel::where('status', '1')->find();
        if (!$recommend_info) {
            return false;
        }
        $date = $date ?: date('Ymd', strtotime('-1 days'));
        $map = [
            'rec_settle' => '',
            'date' => $date
        ];
        print_r($map['date'] . "\n");
        print_r("直推数据结算\n");
        $vip_list = [];
        $recommend_info = json_decode(json_encode($recommend_info), true);
        foreach ($recommend_info as $key => $value) {
            if (strpos($key, 'vip') === 0 && $key != 'vip_tip') {
                if (!empty($value)) {
                    $vip_list[strtoupper($key)] = explode('_', $value);
                }
            }
        }
        //当日直推
        $list = $this->model->where($map)->field('user_id,register_recharge,task_level1,task_level2,task_level3')->limit(1000)->select();
//        print_r($list);
        //发放当日直推奖励阶梯奖励
        $money_log = new MoneyLogModel();
        foreach ($list as $item) {
            $user_id = $item['user_id'];
            $vip = [];
            $key = "";
            foreach ($vip_list as $k => $v) {
                if ($v[0] <= $item['task_level1'] && $v[1] <= ($item['task_level1'] + $item['task_level2'] + $item['task_level3'])) {
                    $vip = $v;
                    $key = str_replace('VIP', '', $k);
                }
            }
            if (!empty($vip)) {
                //满足vip发放标准
                //当日直推奖励
                $title = "当日直推{$key}级奖励";
                $remarks = "当日直推{$key}级获得奖励:{$vip[2]} ";
                $change = "add";
                $type = MoneyLogModel::TYPE_BROKERAGE;
                $money_log->changeMoney($user_id, floatval($vip[2]), $type, $change, $title, $remarks, 15);
                $map['user_id'] = $user_id;
                $this->model->where($map)->update(['rec_settle' => date("Ymd", strtotime($date))]);
            }
        }
        return true;
    }

    /**
     * 获取用户的活跃人数
     * @param  int  $user_id
     * @param  int  $level
     * @param  string  $date
     * @return int
     */
    public static function getUserActiveByDate(int $user_id, int $level, string $date): int
    {
        if (!$date) {
            $date = date('Ymd');
        }
        try {
            $task = TaskSummaryModel::where('user_id', $user_id)
                ->where('date', $date)
                ->find();
        } catch (\Exception $e) {
            return 0;
        }
        if (!$task) {
            return 0;
        }
        return match ($level) {
            1 => $task->task_level1,
            2 => $task->task_level2,
            default => $task->task_level1 + $task->task_level2 + $task->task_level3,
        };
    }
}
