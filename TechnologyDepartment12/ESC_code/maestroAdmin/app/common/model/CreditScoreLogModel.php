<?php

namespace app\common\model;

use app\admin\model\MemberModel;
use app\common\repository\FinanceProductOrderRepository;
use Carbon\Carbon;
use think\console\Output;
use think\facade\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Model;
use think\model\relation\BelongsTo;
use think\model\relation\HasOne;
use app\admin\model\UserModel as AdminModel;

class CreditScoreLogModel extends Model
{
    protected $autoWriteTimestamp = false;
    protected $name = 'credit_score_log';

    const TYPE_TRADE = 1;
    const TYPE_ACTIVE = 2;
    const TYPE_NEW = 3;
    const TYPE_OVERDUE = 4;
    const TYPE_ADMIN_ADD = 5;
    const TYPE_ADMIN_SUB = 6;

    public static array $typeMap = [
        self::TYPE_TRADE     => '交易量',
        self::TYPE_ACTIVE    => '下级活跃',
        self::TYPE_NEW       => '新增人数',
        self::TYPE_OVERDUE   => '逾期',
        self::TYPE_ADMIN_ADD => '后台增加',
        self::TYPE_ADMIN_SUB => '后台减少',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(MemberModel::class, 'id', 'user_id')->field(['id', 'is_xuni','user_name', 'tree_id']);
    }

    public function admin(): HasOne
    {
        return $this->hasOne(AdminModel::class, 'id', 'admin_id')->field(['id','user_name']);
    }

    // 今日下级活跃给上级增加信用分
    public function activeAddCreditScoreCount($date = '')
    {
        $date = date('Ymd');
        print_r($date."\n");
        print_r("今日下级活跃给上级增加信用分\n");

        $user_task = CreditScoreTaskSummaryModel::field('user_id, task_level1, task_level2, task_level3, sum(task_level1 + task_level2 + task_level3) sum_task')
            ->where('date', $date)
            ->group('user_id')
            ->select();
//        dd($user_task);
        // 获取信用分配置
        // 总分:分值范围_m人加n分:分值范围_m人加n分（例如:40:0-20_1_10:21-30_2_5:31-40_3_6）
        $credit_score_active_num = ConfigModel::getConfigValue('credit_score_active_num');
        $config_arr = explode(':', $credit_score_active_num);
        $type = CreditScoreLogModel::TYPE_ACTIVE;
        foreach ($user_task as $task) {
            // 获取该用户该类型的总分
            $user_credit_score = MemberCreditScoreModel::getUserCreditScore($task['user_id']);
            $type_score = $user_credit_score[MemberCreditScoreModel::$fieldMap[$type]];
            // 获取最新的记录
            // 获取用户该类型当月的最新数据
            $month_value = CreditScoreLogModel::where('user_id', $task['user_id'])
                ->where('type', $type)
                ->whereTime('create_time', 'month')
                ->order('id desc')
                ->value('value');
//dd($month_value);
            $count = count($config_arr) - 1;
            for ($i = 1; $i <= $count; $i++) {
                $item = explode('_', $config_arr[$i]);
                $range = $item[0];
                $num = $item[1]; // $item[1] 人加 $item[2] 分
                $score_range = explode('-', $range);
                $min = $score_range[0];
                $max = $score_range[1];
                if ($type_score + 1 >= $min && $type_score + 1 <= $max) {
                    // 计算距离当前范围最高分需要几分
                    $left_score = $max - $type_score;
                    $score = floor(($task['sum_task'] - $month_value) / $num);
                    $score = min($left_score, $score);
                    CreditScoreLogModel::changeCreditScore($task['user_id'], ($item[2]??1)*$score, $type, $score * $num + $month_value);

//                    for ($i = 1; $i <= $score; $i++) {
//                        CreditScoreLogModel::changeCreditScore($task['user_id'], $item[2]??1, $type, $i * $num + $month_value);
//                    }
                    break;
                }
            }
        }
    }

    // 新用户给上级增加信用分
    public function newAddCreditScoreCount($date = '')
    {
        print_r("新用户给上级增加信用分\n");
        $register_recharge = CreditScoreTaskSummaryModel::field('user_id, sum(first_match_finance_product) sum_first_match_finance_product')
            ->whereTime('create_time', 'month')
            ->group('user_id')
            ->having('sum_first_match_finance_product > 0')
            ->select();
//dd($register_recharge);
        // 获取信用分配置
        // 总分:前n人_每人加m分（例如:30:5_3）
        $credit_score_active_num = ConfigModel::getConfigValue('credit_score_new_num');
        $config_arr = explode(':', $credit_score_active_num);
        $type = CreditScoreLogModel::TYPE_NEW;
        foreach ($register_recharge as $task) {
            // 获取最新的记录
            // 获取用户该类型当月的最新数据（人数）
            $month_value = CreditScoreLogModel::where('user_id', $task['user_id'])
                ->where('type', $type)
                ->whereTime('create_time', 'month')
                ->order('id desc')
                ->value('value');
//dd($month_value);
            $item = explode('_', $config_arr[1]); // 加分配置
            $member = $item[0]; // 前n人，每人加m分
            $score = $item[1]; // 1人加n分

            if ($month_value + 1 <= $member) {// 判断当月人数是否大于 $member
                // 获取还差几人
                $left_member = $member - $month_value;
                $can_add_score_number = $task['sum_first_match_finance_product'] - $month_value;
                $times = min($can_add_score_number, $left_member);

                CreditScoreLogModel::changeCreditScore($task['user_id'], $score * $times, $type, $times + $month_value);

//                for ($i = 1; $i <= $times; $i++) {
//                    CreditScoreLogModel::changeCreditScore($task['user_id'], $score, $type, $i + $month_value);
//                }
            } else {
                // 获取还有几个新人未加分
                $times = $task['sum_first_match_finance_product'] - $month_value;

                CreditScoreLogModel::changeCreditScore($task['user_id'], $times, $type, $times + $month_value);
//                for ($i = 1; $i <= $times; $i++) {
//                    CreditScoreLogModel::changeCreditScore($task['user_id'], 1, $type, $i + $month_value);
//                }
            }
        }
    }


    // 交易金额给用户增加信用分
    public function tradeAddCreditScoreCount()
    {
        // 获取信用分配置
        $credit_score_active_num = ConfigModel::getConfigValue('credit_score_trade_num');
        $config_arr = explode('_', $credit_score_active_num);

        // 获取5分钟之内的订单
        $user_ids = FinanceProductOrderModel::where('buy_time', '>=', time()-600)
            ->group('user_id')
            ->column('user_id');

        // 获取用户广告位匹配金额累计
        $finance_product_user = FinanceProductOrderModel::field('user_id, sum(price) sum_price, sum(money) sum_money')
            ->where('user_id', 'in', $user_ids)
            ->where('status',
            'in', [
                FinanceProductOrderRepository::STATUS_WAIT_BUY,
                FinanceProductOrderRepository::STATUS_PURCHASED,
                FinanceProductOrderRepository::STATUS_SETTLED,
            ])
            ->whereTime('buy_time', 'month')
            ->group('user_id')
            ->having('sum_price >= '.$config_arr[1])
            ->select();
//dd($finance_product_user);
        $type = CreditScoreLogModel::TYPE_TRADE;
        foreach ($finance_product_user as $user) {
            // 获取用户该类型的最新信用分
            $today_value = CreditScoreLogModel::where('user_id', $user['user_id'])
                ->where('type', $type)
                ->whereTime('create_time', 'month')
                ->order('id desc')
                ->value('value');
            $num = $config_arr[1]; // 多少金额加一分
            $times = floor(($user['sum_price'] - $today_value) / $num);

            CreditScoreLogModel::changeCreditScore($user['user_id'], $times, $type, $times * $num + $today_value);

//            for ($i = 1; $i <= $times; $i++) {
//                CreditScoreLogModel::changeCreditScore($user['user_id'], 1, $type, $i * $num + $today_value);
//            }
        }
    }

//    /**
//     * @throws DataNotFoundException
//     * @throws ModelNotFoundException
//     * @throws DbException
//     * @throws Exception
//     */
//    private function firstTimes($task, $condition, $config_arr, $type)
//    {
//        if ($condition >= $config_arr[1]) {
//            CreditScoreLogModel::changeCreditScore($task['user_id'], $config_arr[2], $type, $config_arr[0], 1);
//        }
//    }
//
//    /**
//     * @throws DataNotFoundException
//     * @throws DbException
//     * @throws ModelNotFoundException
//     * @throws Exception
//     */
//    private function otherTimes($task, $condition, $config_arr, $credit_score_log, $type)
//    {
//        $times = floor(($condition - $config_arr[0]) / ($config_arr[1] ?: 0)); // 活跃
//
//        if ($credit_score_log->times > 1) {
//            $times = $times - ($credit_score_log->times - 1);
//        }
//
//        for ($i = 1; $i <= $times; $i++) {
//            $new_times = $credit_score_log->times + $i;
//            $value = $config_arr[0] + ($config_arr[1] * ($new_times - 1));
//            CreditScoreLogModel::changeCreditScore($task['user_id'], $config_arr[2], $type, $value, $new_times);
//        }
//    }


    /**
     * @param  int  $user_id
     * @param  int  $score
     * @param  int  $type
     * @param  float  $value
     * @param  int  $times
     * @param  int  $from_user_id
     * @param  int  $admin_id
     * @param  int  $order_id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function changeCreditScore(
        int $user_id,
        int $score,
        int $type,
        float $value = 0,
        int $times = 0,
        int $from_user_id = 0,
        int $admin_id = 0,
        int $order_id = 0
    ): array {
        $user = MemberModel::where('id', $user_id)->find();
        if (!filter_var($score, FILTER_VALIDATE_INT) || $score == 0) {
            return [
                'code' => 0,
                'msg'  => lang('信用分不正确')
            ];
        }
        if (!$user) {
            return [
                'code' => 0,
                'msg'  => lang('用户不存在')
            ];
        }
        // 获取该用户该类型的总分
        $user_credit_score = MemberCreditScoreModel::getUserCreditScore($user_id);
        // <= 0，还扣分,提示失败
        if ($user_credit_score->credit_score <= 0
            && ($type == CreditScoreLogModel::TYPE_ADMIN_SUB || $score <= 0) && $type != CreditScoreLogModel::TYPE_OVERDUE
        ) {
            return [
                'code' => 0,
                'msg'  => lang('用户信用分已达到0')
            ];
        }
        // >= 100，还加分，提示失败
        if ($user_credit_score->credit_score >= 100 && $score > 0) {
            return [
                'code' => 0,
                'msg'  => lang('用户信用分已达到100')
            ];
        }

        // 判断用户是否有待购买的流量包 && 分数已经 == 0， 不扣分也不加分
        $wait_buy_order_count = FinanceProductOrderModel::where('user_id', $user->id)
            ->where('status', FinanceProductOrderRepository::STATUS_WAIT_BUY)
            ->count();
        if ($wait_buy_order_count > 0 && $user_credit_score->credit_score == 0 && $type != CreditScoreLogModel::TYPE_OVERDUE) {
            return [
                'code' => 0,
                'msg'  => lang('用户有待购买的流量包，不再扣分')
            ];
        }

        if ($wait_buy_order_count > 0 && $score > 0) {
            // 有待购买的流量包&&分值 > 0
            // 不再加分
            return [
                'code' => 0,
                'msg'  => lang('用户有待购买的流量包，不再加分')
            ];
        }

//         获取该类型的总分
        $type_total_score = 0;
        $type_score = 0;

        if (in_array($type,
            [CreditScoreLogModel::TYPE_TRADE, CreditScoreLogModel::TYPE_ACTIVE, CreditScoreLogModel::TYPE_NEW])) {
            switch ($type) {
                case CreditScoreLogModel::TYPE_TRADE:
                    $type_total_score = ConfigModel::getConfigValue('credit_score_trade_num');
                    if ($score < 0) {
                        $value = FinanceProductOrderModel::where('status',
                            'in', [
                                FinanceProductOrderRepository::STATUS_PURCHASED,
                                FinanceProductOrderRepository::STATUS_SETTLED,
                            ])
                            ->whereTime('create_time', 'month')
                            ->where('user_id', $user_id)
                            ->sum('price');
                    }
                    break;
                case CreditScoreLogModel::TYPE_ACTIVE:
                    $type_total_score = ConfigModel::getConfigValue('credit_score_active_num');
                    if ($score < 0) {
                        $task_summary = CreditScoreTaskSummaryModel::field('user_id, task_level1, task_level2, task_level3, sum(task_level1 + task_level2 + task_level3) sum_task')
                            ->whereTime('create_time', 'month')
                            ->where('user_id', $user_id)
                            ->group('date')
                            ->buildSql();
                        $value = Db::table($task_summary . ' t')->max('sum_task');
                    }
                    break;
                case CreditScoreLogModel::TYPE_NEW:
                    $type_total_score = ConfigModel::getConfigValue('credit_score_new_num');
                    if ($score < 0) {
                        $value = CreditScoreTaskSummaryModel::whereTime('create_time', 'month')
                            ->where('user_id', $user_id)
                            ->sum('register_recharge');
                    }
                    break;
            }
            $config_arr = explode(':', $type_total_score);
            $type_total_score = intval($config_arr[0]);
            $type_score = $user_credit_score[MemberCreditScoreModel::$fieldMap[$type]];
//            dd($type_score);
            if ($score + $type_score > $type_total_score) {
                $score = $type_total_score - $type_score;
                if ($score <= 0) {
                    return [
                        'code' => 0,
                        'msg'  => lang('该类型信用分已达到满分')
                    ];
                }
            }

            if ($type_score + $score < 0) {
                $score = -$type_score;
            }
        }

        if ($type == CreditScoreLogModel::TYPE_ADMIN_SUB || $type == CreditScoreLogModel::TYPE_OVERDUE) {
            $score = -abs($score);
        }

        if ($type_score <= 0 && $score <= 0 && $type != CreditScoreLogModel::TYPE_OVERDUE) {
            return [
                'code' => 0,
                'msg'  => lang('该类型信用分已达到0分')
            ];
        }

        if ($type == CreditScoreLogModel::TYPE_OVERDUE) {
            $after = $user_credit_score->credit_score;
        } else {
            $after = $score + $user_credit_score->credit_score;
        }
        if ($after > 100) {
            $score = $score - ($after - 100);
            $after = 100;
        } else {
            if ($after < 0) {
                $after = 0;
            }
        }

        $symbol = $score > 0 ? '增加' : '减少';
        $title = CreditScoreLogModel::$typeMap[$type].$symbol.'信用分';
        $remarks = CreditScoreLogModel::$typeMap[$type].$symbol."信用分:{$score} ".($param['remarks'] ?? '');
        $data = [
            'user_id'      => $user_id,
            'from_user_id' => $from_user_id,
            'before'       => $user_credit_score->credit_score ?? 0,
            'score'        => $score,
            'after'        => $after,
            'type'         => $type,
            'value'        => $value,
            'times'        => $times,
            'order_id'     => $order_id,
            'title'        => $title,
            'remarks'      => $remarks,
            'admin_id'     => $admin_id,
            'create_time'  => time(),
            'overdue_time' => Carbon::now()->endOfMonth()->timestamp
        ];
        $res = CreditScoreLogModel::create($data);

        if ($type != CreditScoreLogModel::TYPE_OVERDUE) {
            // 更新用户的信用分明细表
            MemberCreditScoreModel::where('user_id', $user_id)->where('month', date('Ym'))
                ->update([
                    MemberCreditScoreModel::$fieldMap[$type] => $type_score + $score,
                    'credit_score'                           => $after,
                ]);

            // 同时更新用户的信用分
            $user->credit_score = $data['after'];
            $user->save();
        }

        return [
            'code' => 1,
            'msg'  => lang('操作成功')
        ];
    }

    // 逾期扣分
    public function overdueCreditScoreCount()
    {
        // 获取待结算的流量包订单
        $wait_buy_orders = FinanceProductOrderModel::with('user')
            ->field('id,user_id, min(id), update_time,status')
            ->where('status', 'in', [
                FinanceProductOrderRepository::STATUS_WAIT_BUY,
            ])
            ->group('user_id')
            ->select();
//        dd($wait_buy_orders);
        // 获取信用分扣分配置
        $credit_score_overdue = ConfigModel::getConfigValue('credit_score_overdue');
        $config_arr = explode('_', $credit_score_overdue);

        foreach ($wait_buy_orders as $order) {
            // 获取这个订单id扣分次数
            $credit_score_count = CreditScoreLogModel::where('user_id', $order['user_id'])
                ->where('order_id', $order->id)
                ->where('type', CreditScoreLogModel::TYPE_OVERDUE)
                ->where('score', '<', 0)
                ->count();
            if ($credit_score_count) {
                if ($credit_score_count >= count($config_arr)) {
                    continue;
                }
                $item = explode(':', $config_arr[$credit_score_count]);
                $hour = Carbon::now()->floatDiffInHours(Carbon::parse($order->update_time));
                if (abs($hour) > $item[0] && $order->status == FinanceProductOrderRepository::STATUS_WAIT_BUY) {
                    $item[1] = intval($item[1]);
                    $this->overdueSubScore($order, $item[1]);
                    CreditScoreLogModel::changeCreditScore($order['user_id'], -$item[1],
                        CreditScoreLogModel::TYPE_OVERDUE, 0, 0, 0, 0,
                        $order->id);
                }
            } else {
                // 没有扣分过，直接扣分
                $hour = Carbon::now()->floatDiffInHours(Carbon::parse($order->update_time));//得到当前时间到订单更新时间之间的小时差
                $item = explode(':', $config_arr[0]);
                // 如果订单时间到现在>
                if (abs($hour) >= $item[0]) {
                    $item[1] = intval($item[1]);
                    $this->overdueSubScore($order, $item[1]);
                    CreditScoreLogModel::changeCreditScore($order['user_id'], -$item[1],
                        CreditScoreLogModel::TYPE_OVERDUE, 0, 0, 0, 0,
                        $order->id);
                }
            }
        }
    }

    public function overdueSubScore($order, $score)
    {
        $credit_score = MemberCreditScoreModel::getUserCreditScore($order['user_id']);
        $user_id = $order['user_id'];
        $credit_score_detail = [
            'trade'  => 0,
            'active' => 0,
            'new'    => 0,
        ];
        if ($credit_score) {
            $credit_score_detail = [
                'trade'  => $credit_score['trade'],
                'active' => $credit_score['active'],
                'new'    => $credit_score['new'],
            ];
        }
        // 扣分顺序按照 新增人数 > 团队活跃人数 > 交易量进行扣分
        if ($credit_score_detail['new'] > $score) {
            //  新增人数扣分
            CreditScoreLogModel::changeCreditScore($user_id, -$score, CreditScoreLogModel::TYPE_NEW, 0, 0, $user_id,
                0,
                $order->id);
        } else {
            //  新增人数扣分
            CreditScoreLogModel::changeCreditScore($user_id, -$credit_score_detail['new'],
                CreditScoreLogModel::TYPE_NEW, 0, 0, $user_id, 0,
                $order->id);
            $score -= $credit_score_detail['new'];
            if ($credit_score_detail['active'] > $score) {
                // 团队活跃人数扣分
                CreditScoreLogModel::changeCreditScore($user_id, -$score, CreditScoreLogModel::TYPE_ACTIVE, 0,
                    0, $user_id, 0,
                    $order->id);
            } else {
                // 团队活跃人数扣分
                CreditScoreLogModel::changeCreditScore($user_id, -$credit_score_detail['active'],
                    CreditScoreLogModel::TYPE_ACTIVE, 0,
                    0, $user_id, 0,
                    $order->id);
                $score -= $credit_score_detail['active'];
                if ($credit_score_detail['trade'] > $score) {
                    // 交易量扣分
                    CreditScoreLogModel::changeCreditScore($user_id, -$score, CreditScoreLogModel::TYPE_TRADE,
                        0, $user_id, 0, 0,
                        $order->id);
                } else {
//                    $score = $credit_score_detail['trade'];
                    // 交易量扣分
                    CreditScoreLogModel::changeCreditScore($user_id, -$credit_score_detail['trade'],
                        CreditScoreLogModel::TYPE_TRADE,
                        0, $user_id, 0, 0,
                        $order->id);
                }
            }
        }
    }

    public function financeProductOrder(): BelongsTo
    {
        return $this->belongsTo(FinanceProductOrderModel::class, 'order_id', 'id');
    }
}
