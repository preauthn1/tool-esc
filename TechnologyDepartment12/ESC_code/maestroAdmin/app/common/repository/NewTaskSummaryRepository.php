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
use app\common\model\TaskSummaryV2Model;
use app\common\model\UserModel;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

class NewTaskSummaryRepository extends Repository
{
    protected $model;

    public function __construct()
    {
        //绑定模型
        $this->model = new TaskSummaryV2Model();
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
        $recharges = RechargeModel::hasWhere('user', [['leader1','>', 0],['create_time','>=',strtotime(config('app.activity_start_time'))]])
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
            ['leader1','>', 0],['create_time','>=',strtotime(config('app.activity_start_time'))]
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
}
