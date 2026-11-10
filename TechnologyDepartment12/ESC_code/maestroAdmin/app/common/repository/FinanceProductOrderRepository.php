<?php

namespace app\common\repository;

use app\admin\model\LevelModel;
use app\api\transform\FinanceProductOrderTransform;
use app\common\model\AdPlanDaysConfig;
use app\common\model\AdPlanModel;
use app\common\model\AdPlanUserDaysConfigModel;
use app\common\model\ConfigModel;
use app\common\model\FinancePlanModel;
use app\common\model\FinanceProductOrderModel;
use app\common\model\MoneyLogModel;
use app\common\model\PddTurntableCountModel;
use app\common\model\TaskSessionModel;
use app\common\model\UserModel;
use app\lib\exception\MatchFinanceProductException;
use Carbon\Carbon;
use think\db\exception\DbException;
use think\db\Where;

use think\facade\Cache;
use think\facade\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\facade\Log;
use think\Model;
use think\facade\Config;
use util\RedisSyncPool;

class FinanceProductOrderRepository extends Repository
{
    protected $model;

    protected $redis;

    public function __construct()
    {
        //绑定模型
        $this->model = new FinanceProductOrderModel();

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

    const MATCH_TYPE_NORMAL = 1; // 正常匹配
    const MATCH_TYPE_OVERDUE = 2; // 超额匹配

    public static array $matchTypeMap = [
        self::MATCH_TYPE_NORMAL => '正常匹配',
        self::MATCH_TYPE_OVERDUE => '超额匹配',
    ];


    // 广告库存订单结算
    public function settlement()
    {
        // 获取待结算订单
        $where = [
            ['status', '=', self::STATUS_PURCHASED],
        ];
        $orders = $this->selectWhere($where);
        foreach ($orders as $order) {
            // 判断是否可以结算
            switch ($order->profit_type) { //  0-按日返息到期不还本 1-按日返息到期还本 2-本息到期还
                case 0: // 按日返息到期不还本
                    break;
                case 1: // 按日返息到期还本
                    break;
                case 2: // 本息到期还
                    if ($order->end_time > 0 && $order->end_time < time()) { // 到期了, 可以发放收益
                        $money_log = new MoneyLogModel();
                        $user_id = $order->user_id;
                        $money_log->changeMoney($user_id, $order->price,
                            MoneyLogModel::TYPE_BROKERAGE, 'add', '广告库存本金', '', 12, '', '', $order->id);
                        //归还本金的时候需要检测用户流水water_money是否有欠款
                        //更新用户流水 water_money
                        $water_money = UserModel::where("id", $order->user_id)->column("water_money");
                        if ($water_money < 0) {
                            $water_money += $order->price;
                            if ($water_money > 0) {
                                $water_money = 0;
                            }
                            UserModel::where("id", $order->user_id)->update(["water_money" => $water_money]);
                        }

                        $money_log->changeMoney($user_id, $order->profit_money,
                            MoneyLogModel::TYPE_BROKERAGE, 'add', '广告库存收益', '', 16, '', '', $order->id);
                        $order->settlement_time = time();
                        $order->settlement_times = 1;
                        $order->status = self::STATUS_SETTLED;
                        $order->save();
                    }
                    break;
            }
        }

    }

    /**
     * 广告库存结算返利
     * @param $user
     * @param float $money
     * @param int $level
     * @return true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function allotBrokerage($user, float $money, int $level = 3): bool
    {
        $parent = [];
        $temp_id = $user->id;
        //获取
        for ($i = 0; $i < $level; $i++) {
            $temp_id = UserModel::getParentId($temp_id);
            if (!empty($temp_id)) {
                $parent[$i] = $temp_id;
            }
        }
        if (empty($parent)) {
            return true;
        }
        $level_data = (new LevelRepository())->getLevelByLevelID($user->level_id);
        if (empty($level_data)) {
            $level_data = (new LevelRepository())->getLevelByLevelID($user->level_id);
        }
        $task_rebate = str_replace('[', '', $level_data['task_rebate']);
        $task_rebate = str_replace(']', '', $task_rebate);
        $task_rebate_arr = explode(',', $task_rebate);
        $money_log_model = new MoneyLogModel();

        for ($i = 0; $i < $level; $i++) {
            if (isset($parent[$i])) {
                $money_temp = round($money * (floatval($task_rebate_arr[$i]) / 100), 2);//获得返利
                $type = MoneyLogModel::TYPE_BROKERAGE_POOL;
                $title = "广告库存返利-" . ($i + 1) . "级别";
                $money_log_model->changeMoney($parent[$i], $money_temp, $type, 'add', $title, $title . '_' . $money_temp,
                    $i + 9, '', $user->id);
            }
        }
        return true;
    }

    // 广告库存订单超时未支付取消订单
    public function cancel()
    {
        // 获取待支付订单
        $where = [
            ['status', '=', self::STATUS_WAIT_BUY],
        ];
        $orders = $this->selectWhere($where);
        foreach ($orders as $order) {
            if ($order->end_time - $order->create_time > 24 * 3600) {
                $order->status = self::STATUS_EXPIRED;
                $order->save();
            }
        }
    }

    /**
     * 获取广告库存配置
     * @param $level
     * @param $user
     * @param $ad_plan_id
     * @param null $plan
     * @param bool $level_first 团队设置优先
     * @return array
     * @throws MatchFinanceProductException|Throwable
     */
    private function getInvestConfig(
        $level,
        $user,
        $ad_plan_id,
        $plan = null,
        bool $level_first = false
    ): array
    {
        $startTime = microtime(true);
        $user_id = $user->id;
        $plan_id = 0;
        $invest_num = 0;
        $invest_rate = 0;
        $cycle = 0;
        $invest_period_type = 'day';
        $invest_profit = 0;
        $is_plan = 0;

        $stock_range = ['min' => 0, 'max' => 0];
        $ratios = [];
        $user_excess_ad_ratio_config = [];
        // 超额单控广告位比例配置
        $tree_ids = explode('/', $user->tree_id);

        if (!empty($tree_ids)) {
            // 倒序
            $tree_ids = array_reverse(array_filter($tree_ids));
        }
        foreach ($tree_ids as $tree_id) {
            $config = (new HomeUserExcessAdRatioConfigRepository())
                ->getUserExcessAdRatioConfig($tree_id, $level->level_id);
            if ($config) {
                $ratio = array_filter(json_decode($config->ratio, true));
                if (count($ratio) == 0) {
                    continue;
                }
                if ($tree_id == $user->id || $config->subordinate == 1) {
                    $user_excess_ad_ratio_config = $config;
                    $invest_num = $level->invest_num; // 广告库存个数
                    $invest_rate = $level->invest_rate; // 广告库存个数
                    break;
                }
            }
        }

        if (!$user_excess_ad_ratio_config) {
            // 获取等级金额区间库存
            $startTime1 = microtime(true);
            list($current_inventory, $total_inventory, $can_over_match, $stock_range,$user_money_interval) = FinanceProductOrderRepository::isUserMoneyInRangeAndStockEnough($level,
                $user);
            trace_log("isUserMoneyInRangeAndStockEnough：" . microtime(true) - $startTime1);
//            if (in_array($level->type, [LevelRepository::TYPE_EXPERIENCE, LevelRepository::TYPE_ACTIVITY])) { // 活动和体验区
//                // 根据vip配置生成订单
//                $invest_num = $level->invest_num; // 广告库存个数
//                list($stock_range, $cycle, $invest_period_type, $invest_profit, $invest_rate) = $this->getLevelInvestConfig($level,
//                    $user, $cycle, $invest_period_type, $invest_profit, $invest_rate, $user_id);
//
//            } else {
            // 生效的优先级： 单控 > 群控 > 广告位购买计划页面配置的广告位周期、比例、收益率 >  不同金额区间弹窗内配置的广告位周期、比例、收益率 >平台配置的广告位周期、比例、收益率
            if ($level_first) { // 单控/群控
                $invest_num = $level->invest_num;// 广告位数量
                $cycle = $level->invest_day; // 广告库存周期
                $invest_period_type = $level->invest_period_type; // 广告库存周期类型(天、小时）
                $invest_rate = $this->getInvestRate($level, $user_id); // 占余额比例
                $invest_profit = $level->invest_profit; // 收益率
            } else {
                if ($user->finance_plan_id && $plan) { // 计划任务
                    $plan_id = $plan->id;
                    $invest_num = $plan->num;// 广告位数量
                    $cycle = $plan->cycle;
                    $invest_period_type = $plan->invest_period_type;
                    $invest_rate = $plan->rate;
                    $invest_profit = $plan->profit;
                    $is_plan = 1;
                } else {
                    $invest_num = $level->invest_num;// 广告位数量
                    if ($ad_plan_id && $plan) { // 广告位购买计划
                        $cycle = $plan->invest_day ?: $level->invest_day; // 广告库存周期
                        $invest_period_type = $plan->invest_day ? ($plan->invest_period_type ?: $level->invest_period_type) : $level->invest_period_type; // 广告库存周期类型(天、小时）
                        $invest_rate = $plan->invest_rate; // 占余额比例
                        $invest_profit = $plan->invest_profit; // 收益率
                    } else {// 平台控
                        $startTime11 = microtime(true);
                        list($stock_range, $cycle, $invest_period_type, $invest_profit, $invest_rate) = $this->getLevelInvestConfig($level,
                            $user, $cycle, $invest_period_type, $invest_profit, $invest_rate, $user_id);
                        trace_log("getLevelInvestConfig：" . microtime(true) - $startTime11);
                        $cycle = $stock_range['period'] ?? $cycle;
                        $invest_period_type = $stock_range['period_type'] ?? $invest_period_type;
                        $invest_rate = $stock_range['rate'] ?? $invest_rate;
                        $invest_profit = $stock_range['profit'] ?? $invest_profit;
                    }
                }
            }
//            }

            if (!$invest_num && !$invest_rate && !$cycle && !$invest_profit) {
                throw new MatchFinanceProductException([
                    'msg' => lang('该等级暂无可匹配的广告库存'),
                    'errorCode' => 0,
                    'extendedData' => [
                        'msg' => lang('该等级暂无可匹配的广告库存'),
                        'code' => -1
                    ]
                ]);
            }
        }
        trace_log("getInvestConfig:" . microtime(true) - $startTime);
        return [
            'plan_id' => $plan_id,
            'ad_plan_id' => $ad_plan_id,
            'invest_num' => $invest_num,
            'invest_rate' => $invest_rate,
            'cycle' => $cycle,
            'invest_period_type' => $invest_period_type,
            'invest_profit' => $invest_profit,
            'is_plan' => $is_plan,
            'stock_range' => $stock_range,
            'user_excess_ad_ratio_config' => $user_excess_ad_ratio_config
        ];
    }

    // 广告库存占余额比例
    private function getInvestRate($level, $user_id)
    {
        $invest_rate = $level->invest_rate;
        // 获取一级下级用户
        if ($level->active_rate > 0) {
            $team_one_ids = UserModel::getTeamAllIds($user_id);
            $active_count = $this->getYesterdayFinanceNum($team_one_ids ?: [0]);
            if ($active_count > 0) {
                $new_invest_rate = $invest_rate + $active_count * $level->active_rate;
                $invest_max_rate = max($level->invest_max_rate, $level->invest_rate);
                $invest_rate = min($new_invest_rate, $invest_max_rate);
            }
        }
        return $invest_rate;
    }

    /**
     * 匹配广告库存
     * @param int $user_id
     * @param int $level_id
     * @param string $password
     * @param int $match_type
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws MatchFinanceProductException
     * @throws ModelNotFoundException
     */
    public function match(
        int $user_id,
        int $level_id,
        string $password = '',
        int $match_type = FinanceProductOrderRepository::MATCH_TYPE_NORMAL
    ): array
    {
//        Log::write('user_id: ' . $user_id . "--level_id:" . $level_id . "--match_type:" . $match_type, 'debug');
        list($orders, $task_session) = (new FinanceProductOrderRepository)->getLevelSessionOrders($level_id,
            $user_id);

        if (!$task_session) {
            return msg(-1, '', lang('目前无法购买'));
        }
        // 生成一个唯一编码
        $session_no = genOrderSN(random(6, 'number'));
        $time3 = microtime(true);
        // 如果有未匹配的订单，就不判断等级
        if ($orders->isEmpty()) {
            list($level, $user) = $this->checkUserLevel($user_id, $level_id);
            list($level, $level_first) = $this->getTeamLevel($level, $user_id, $level_id);
            $count = $this->getExperienceSessionsCount($user_id);
            if ($level->session_count && $count >= $level->session_count) {
                throw new MatchFinanceProductException([
                    'msg' => lang('暂无可匹配的广告库存'),
                    'errorCode' => 0,
                    'extendedData' => [
                        'msg' => lang('暂无可匹配的广告库存'),
                        'code' => -1
                    ]
                ]);
            }
        } else {
            $level = (new LevelRepository())->getLevelByLevelID($level_id);
            $user = UserModel::where('id', $user_id)->find();
            list($level, $level_first) = $this->getTeamLevel($level, $user_id, $level_id);
        }
        $time4 = microtime(true);
        trace_log('checkUserLevel:' . $time4 - $time3);
        // 检查当前时段是否能匹配广告位
//        $this->canUnlockAtCurrentTime($level_id);
        $balance = $user->user_money;
        $time44 = microtime(true);
        list($plan, $ad_plan_id) = $this->getUserPlan($user_id, $level_id, $task_session->id, $user->ad_plan_id,
            $user->finance_plan_id);
        $user_money = $user->user_money;
        $overdue_money = $this->getOverdueMoney($user_money, $user->credit_score);
        // 获取投资配置
        $config = $this->getInvestConfig($level, $user, $ad_plan_id, $plan, $level_first);
        $time5 = microtime(true);
        trace_log('getUserPlan + getInvestConfig:' . $time5 - $time44);
        if ($orders->isEmpty() && $match_type == FinanceProductOrderRepository::MATCH_TYPE_NORMAL && $config['invest_rate'] > 100) {
            // 弹出超额匹配弹窗
            return msg(-3, '', lang('当前预算内库存已撮合完成，是否启用信用预支参与更高价位库存交易？'));
        }

        // 未匹配订单
        $pending_orders = $orders->filter(function ($item) use ($level_id) {
            return $item['status'] == FinanceProductOrderRepository::STATUS_PENDING;
        });
        $matched_order_count = 0;
//        Db::startTrans();
        $timeprepareCreateOrderStart = microtime(true);
        if ($level_first && count($pending_orders) > 0) {
            // 判断是否已经单控了
            $is_team_level = $pending_orders->filter(function ($item) {
                return $item['is_team_level'] == 1;
            });
            if ($is_team_level->isEmpty()) {
                $session_no = $pending_orders->first()->session_no;
                $balance = $pending_orders->first()->balance;
                // 删除未匹配的订单
                foreach ($pending_orders as $pending_order) {
                    $pending_order->delete();
                }
                // 已匹配数量
                $matched_order_count = count($orders->filter(function ($item) use ($level_id) {
                    return $item['status'] != FinanceProductOrderRepository::STATUS_PENDING;
                }));
                $orders = collect();
            }
        }


        $matched_order_count = $this->prepareCreateOrder($orders, $level, $user, $plan, $overdue_money, $match_type,
            $config, $session_no, $matched_order_count, $balance, $level_first);
        $timeprepareCreateOrderEnd = $time3 = microtime(true);
        trace_log('prepareCreateOrder执行时长：' . $timeprepareCreateOrderEnd - $timeprepareCreateOrderStart);
//        DB::commit();


        $order = $this->getPendingOrder($level_id, $user_id, $task_session, $matched_order_count);

//        Log::write('order: ' . json_encode($order) ." task_session: ".json_encode($task_session)." user: ".json_encode($user), 'debug');
        $time4 = microtime(true);
        trace_log('getPendingOrder' . $time4 - $time3);
        $data = $this->updateOrder($order, $user);

        // 最新ID缓存起来
        $redis = Cache::store('redis');
        $end_key = "session_end_order_id_" . Carbon::parse(date('Y-m-d') . ' ' . $task_session->end_time)->timestamp;
        $redis->set($end_key, $data['order_id'], 86400);

        $time5 = microtime(true);
        trace_log('updateOrder' . $time5 - $time4);
//        $data['user_money'] // 最新的用户余额
        // 判断是否是最后一个订单

        if (count($orders) == $order->sort) {
            if ($data['user_money'] >= $order->overdue_money) {
                $status = FinanceProductOrderRepository::STATUS_PURCHASED;
                $start_time = time();
                $end_time = $this->calculateTimestampFromDays($order);
                foreach ($orders as $order_item) {
                    if ($order_item->status == FinanceProductOrderRepository::STATUS_SETTLED) {
                        continue;
                    }
                    $order_item->status = $status;
                    $order_item->start_time = $start_time;
                    $order_item->end_time = $end_time;
                    $order_item->update_time = time();
                    $order_item->save();
                }
            }
        }

        // 匹配成功，立即返佣，返佣按照购广告库存的收益返佣
        $time6 = microtime(true);
        $this->allotBrokerage($user, $order->profit_money);
        $endTime = microtime(true);
        trace_log("allotBrokerage：" . $endTime - $time6);
        // 返回匹配结果
        return msg(0, $data, lang('匹配成功'));
    }

    /**
     * 根据天数计算时间戳
     * @param $order
     * @param int $start_time
     * @return int
     */
    public function calculateTimestampFromDays($order, int $start_time = 0): int
    {
        if ($start_time) {
            $now = Carbon::createFromTimestamp($start_time);
        } else {
            $now = Carbon::now();
        }
        switch ($order->invest_period_type) {
            case 'hour':
                $hour = (float)$order->cycle; // ✅ 强制转换为 float
                $hoursInt = floor($hour);
                $minutes = ($hour - $hoursInt) * 60;
                return $now->copy()->addHours($hoursInt)->addMinutes($minutes)->timestamp;
            case 'minute':
                $minutes = $order->cycle;
                return $now->copy()->addMinutes($minutes)->timestamp;
            case 'day':
            default:
                $days = $order->cycle;
                $daysInt = floor($days);
                $hoursDecimal = ($days - $daysInt) * 24;
                $minutes = $hoursDecimal * 60;
                return $now->copy()->addDays($daysInt)->addMinutes($minutes)->timestamp;
        }
    }


    /**
     * 创建广告库存订单
     * @param $index int 序号
     * @param $user_id int 用户id
     * @param float $invest_rate 广告位占余额比例
     * @param $amount float 金额
     * @param $cycle float 周期
     * @param string $invest_period_type
     * @param $profit float 收益率
     * @param $level_id int 等级id
     * @param float $balance
     * @param float $overdue_money 用户余额大于此预支金额即可结算
     * @param array $config
     * @param string $session_no
     * @param int $match_type
     * @param int $level_first
     * @param float $fee_rate // 手续费比例
     * @return void
     */
    private function createOrder(
        int $index,
        int $user_id,
        float $invest_rate,
        float $amount,
        float $cycle,
        string $invest_period_type,
        float $profit,
        int $level_id,
        float $balance,
        float $overdue_money,
        array $config,
        string $session_no = '',
        int $match_type = 1,
        int $level_first = 0,
        float $fee_rate = 0.0
    ): void
    {
        $index = $index + 1;
        $finance_product_data = ConfigModel::getConfigValue('finance_product_data');
        $finance_product_data = $finance_product_data ? json_decode($finance_product_data, true) : [];
        $enabled_product_data = array_filter($finance_product_data, function ($item) {
            return $item['enabled'] == 1;
        });
        $other_info = [];
        foreach ($enabled_product_data as $item) {
            $other_info[] = [
                'title' => $item['title'],
                // 从item['content'] 随机取一个， 赋值个content
                'content' => $item['content'][mt_rand(0, count($item['content']) - 1)]
            ];
        }

        $data = [
            'order_no' => genOrderSN(random(6, 'number'), 'FP'),
            'user_id' => $user_id,
            'level_id' => $level_id,
            'cycle' => $cycle,
            'invest_period_type' => $invest_period_type,
            'end_time' => 0,
            'balance' => $balance,
            'overdue_money' => $overdue_money,
            'invest_rate' => $invest_rate,
            'price' => $amount,
            'money' => $amount,
            'finance_product_name' => $index,
            'profit' => $profit,
            'profit_money' => round($amount * $profit / 100, 2),
            'profit_type' => 2, // 收益类型 0-按日返息到期不还本 1-按日返息到期还本 2-本息到期还
            'start_time' => 0,
            'settlement_time' => 0,
            'sort' => $index,
            'status' => FinanceProductOrderRepository::STATUS_PENDING,
            'plan_id' => $config['plan_id'],
            'other_info' => json_encode($other_info),
            'session_no' => $session_no,
            'match_type' => $match_type,
            'fee_rate' => $fee_rate,
            'fee' => ($fee_rate / 100) * $amount,
            'stock_range_min' => $config['stock_range']['min'],
            'stock_range_max' => $config['stock_range']['max'],
            'is_team_level' => $level_first
        ];
        $this->create($data);
    }

    // 每个流量包金额计算

    /**
     * @throws Throwable
     */
    private function getInvestAmount($user, $config, $level, $match_type, $balance): array
    {
        $invest_mount = [];
        $invest_rate = $config['invest_rate'];
        $invest_num = $config['invest_num'];
        // 获取等级金额区间库存
        list($current_inventory, $total_inventory, $can_over_match, $stock_range,$user_money_interval) = FinanceProductOrderRepository::isUserMoneyInRangeAndStockEnough($level,
            $user);


        if ($level->limit_unlock_num > 0 && $can_over_match && !$total_inventory && $match_type == FinanceProductOrderRepository::MATCH_TYPE_OVERDUE) {
            // 库存不足
            // 判断用户是否有 库存超额广告位比例配置
            $tree_ids = explode('/', $user->tree_id);
            // 倒序
            $tree_ids = array_reverse(array_filter($tree_ids));

            foreach ($tree_ids as $tree_id) {
                $user_excess_ad_ration_config = (new HomeUserExcessAdRatioConfigRepository())->getUserExcessAdRatioConfig($tree_id,
                    $level->level_id);
                if ($user_excess_ad_ration_config) {
                    $ratios = array_filter(json_decode($user_excess_ad_ration_config->ratio, true));
                    if (count($ratios) == 0) {
                        continue;
                    }
                    if ($tree_id == $user->id) {
                        foreach ($ratios as $ratio) {
                            if ($ratio > 0) {
                                $invest_mount[] = [
                                    'amount' => round($balance * $ratio / 100, 2),
                                    'invest_rate' => $ratio,
                                    'cycle' => $user_excess_ad_ration_config['cycle'],
                                    'invest_period_type' => $user_excess_ad_ration_config['invest_period_type'],
                                    'invest_profit' => $user_excess_ad_ration_config['invest_profit']
                                ];
                            }
                        }
                        break;
                    }

                    if ($user_excess_ad_ration_config->subordinate == 1) {
                        foreach ($ratios as $ratio) {
                            if ($ratio > 0) {
                                $invest_mount[] = [
                                    'amount' => round($balance * $ratio / 100, 2),
                                    'invest_rate' => $ratio,
                                    'cycle' => $user_excess_ad_ration_config['cycle'],
                                    'invest_period_type' => $user_excess_ad_ration_config['invest_period_type'],
                                    'invest_profit' => $user_excess_ad_ration_config['invest_profit'],
                                ];
                            }
                        }
                        break;
                    }
                }
            }

            if (empty($invest_mount)) {
                // 判断是否有金额区间超额库存比例配置
                if (array_key_exists('amount_range_excess_invest_rate', $stock_range)) {
                    // 获取金额区间超额库存比例
                    foreach ($stock_range['amount_range_excess_invest_rate'] as $rate) {
                        $invest_mount[] = [
                            'amount' => round($balance * $rate['rate'] / 100, 2),
                            'invest_rate' => $rate['rate'],
                            'cycle' => $rate['period'],
                            'invest_period_type' => $rate['period_type'],
                            'invest_profit' => $rate['profit'],
                        ];
                    }
                }
                if (empty($invest_mount)) {
                    $excess_invest_rate = array_filter(json_decode($level->excess_invest_rate));
                    foreach ($excess_invest_rate as $rate) {
                        $invest_mount[] = [
                            'amount' => round($balance * $rate / 100, 2),
                            'invest_rate' => $rate,
                            'cycle' => $config['cycle'],
                            'invest_period_type' => $config['invest_period_type'],
                            'invest_profit' => $config['invest_profit'],
                        ];
                    }
                }
            }
        } else {
            $amount = round($invest_rate / 100 * $balance, 2);
            $distribute_number = $this->distributeNumber($amount, $invest_num);
            foreach ($distribute_number as $amount) {
                $invest_mount[] = [
                    'amount' => $amount,
                    'invest_rate' => $invest_rate,
                    'cycle' => $config['cycle'],
                    'invest_period_type' => $config['invest_period_type'],
                    'invest_profit' => $config['invest_profit'],

                ];
            }
        }

        return [
            'user_money' => $balance,
            'invest_amount' => $invest_mount
        ];
    }

    function distributeNumber($total, $parts): array
    {
        // 初始化数组来存储平分后的每一份
        $baseValue = $total / $parts;
        $adjustedValues = [];
        $sumAdjusted = 0;

        // 随机调整每一个部分
        for ($i = 0; $i < $parts; $i++) {
            $randomFactor = mt_rand(60, 140) / 100.0;
            $adjustedPart = $baseValue * $randomFactor;
            $adjustedValues[] = $adjustedPart;
            $sumAdjusted += $adjustedPart;
        }

        // 计算调整因子，以便总和保持不变
        $adjustmentFactor = $total / $sumAdjusted;

        // 调整每一个部分
        for ($i = 0; $i < $parts; $i++) {
            $adjustedValues[$i] = round($adjustedValues[$i] * $adjustmentFactor, 2);
        }

        return $adjustedValues;
    }

    /**
     * 恢复订单
     * @param $user_id
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function recoverOrder($user_id)
    {
        $user = UserModel::where('id', $user_id)->find();
        // 判断是否有未支付的广告库存
        $finance_order = (new FinanceProductOrderRepository)->selectWhere([
            ['user_id', '=', $user_id],
            ['status', '=', FinanceProductOrderRepository::STATUS_WAIT_BUY],
            ['overdue_money', '<=', $user[MoneyLogModel::$TYPE_FIELDS[MoneyLogModel::TYPE_USER]]]
        ]);

        if (!$finance_order->isEmpty()) {
            // 判断用户金额是否足够
            if ($user->user_money >= $finance_order[0]->overdue_money) {
                // 循环未支付的订单，
                foreach ($finance_order as $order) {
                    if ($order->status == FinanceProductOrderRepository::STATUS_PURCHASED) {
                        continue;
                    }

                    $order->status = FinanceProductOrderRepository::STATUS_PURCHASED;
                    if (!$order->buy_time) {
                        $order->buy_time = time();
                    }
                    $order->save();
                }
            }
        }

        // 获取已购买未开始执行的订单
        $purchased_order = (new FinanceProductOrderModel())
            ->order(['id' => 'desc'])
            ->where([
                'user_id' => $user_id,
                'status' => FinanceProductOrderRepository::STATUS_PURCHASED,
                'end_time' => 0
            ])->find();
        $orders = [];
        if ($purchased_order && $purchased_order['session_no']) {
            $orders = $this->selectWhere([
                ['user_id', '=', $user_id],
                ['level_id', '=', $purchased_order->level_id],
                ['session_no', '=', $purchased_order['session_no']]
            ]);

            if (count($orders) > 0 && count($orders) == $purchased_order->sort) {
                if ($user->user_money >= $purchased_order->overdue_money) {
                    $status = FinanceProductOrderRepository::STATUS_PURCHASED;
                    $start_time = $buy_time = $update_time = time();
                    $end_time = $this->calculateTimestampFromDays($purchased_order);
                    foreach ($orders as $order_item) {
                        if ($order_item->status == FinanceProductOrderRepository::STATUS_SETTLED) {
                            continue;
                        }
                        if (!$order_item->buy_time) {
                            $order_item->buy_time = $buy_time;
                        }
                        $order_item->status = $status;
                        $order_item->start_time = $start_time;
                        $order_item->end_time = $end_time;
                        $order_item->update_time = $update_time;
                        $order_item->save();
                    }
                }
            }
        }
    }

    /**
     * 获取今日购买广告库存的人数
     * @param array|string $user_ids
     * @param bool $include_xuni
     * @param bool $is_level
     * @return int
     */
    public function getTodayFinanceNum($user_ids = [], bool $include_xuni = false, $is_level = true): int
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if ($user_ids === '0') {
            return 0;
        }
        if (!$include_xuni) {
            $query = $this->model->hasWhere('user', ['is_xuni' => 0]);
        } else {
            $query = $this->model->hasWhere('user');
        }
        if (!empty($user_ids)) {
            $query = $query->where('user_id in (' . $user_ids . ')');
        }
        if ($is_level) {
            return $query
                ->whereTime('finance_product_order_model.create_time', 'today')
                ->where('finance_product_order_model.status', 'in',
                    [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
                ->where('finance_product_order_model.level_id', '>', 0)
                ->group('user_id')
                ->count();
        }
        return $query
            ->whereTime('finance_product_order_model.create_time', 'today')
            ->where('finance_product_order_model.status', 'in',
                [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
            ->where('finance_product_order_model.level_id', '>', 0)
            ->group('user_id')
            ->count();
    }

    /**
     * 获取今日购买广告库存的总金额
     * @param array|string $user_ids
     * @return float
     */
    public function getTodayFinanceMoney($user_ids = []): float
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if ($user_ids === '0') {
            return 0;
        }
        $query = $this->model->hasWhere('user', ['is_xuni' => 0]);
        if (!empty($user_ids)) {
            $query = $query->where('user_id in (' . $user_ids . ')');
        }
        return $query
            ->whereTime('finance_product_order_model.create_time', 'today')
            ->where('finance_product_order_model.status', 'in',
                [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
            ->sum('money');
    }

    // 获取昨日购买广告库存的人数
    public function getYesterdayFinanceNum($user_ids = []): int
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if ($user_ids === '0') {
            return 0;
        }
        $query = $this->model->hasWhere('user', ['is_xuni' => 0]);
        if (!empty($user_ids)) {
            $query = $query->where('user_id in (' . $user_ids . ')');
        }
        return $query
            ->whereTime('finance_product_order_model.create_time', 'yesterday')
            ->where('finance_product_order_model.status', 'in',
                [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
            ->group('user_id')
            ->count();
    }

    // 获取今日购买广告库存用户的余额
    public function getTodayFinanceUserMoney()
    {
        $finance_product_order_user_ids = $this->model
            ->whereTime('create_time', 'today')
            ->where('status', 'in',
                [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
            ->group('user_id')
            ->column('user_id');
        $user_ids_str = '0';
        $user_ids_str .= implode(',', $finance_product_order_user_ids);
        return UserModel::where('id in  (' . $user_ids_str . ')')
            ->where('is_xuni', 0)
            ->sum('user_money');
    }

    // 用户今日购买广告位数量
    public function todayUserFinanceProductCount($user_id, $day = ''): int
    {
//        $day = '2025-09-09';//测试用
        $where_time = [];
        if ($day) {
            $where_time[] = ['start_time', 'between', [strtotime($day . ' 00:00:00'), strtotime($day . ' 23:59:59')]];
        } else {
            $where_time[] = [
                'start_time',
                'between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . '23:59:59')]
            ];
        }
        $today_order = $this->model->where('user_id', $user_id)
            ->where('status', 'in',
                [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
            ->where($where_time)
            ->select();
        // 获取广告位时段
        $task_sessions = TaskSessionModel::where('status', 1)->select();
        $count = 0;
        foreach ($task_sessions as $session) {
            // 循环广告位时段
            foreach ($today_order as $order) {
                // 获取订单的 level_id ,根据 level_id 获取 level_type
                $level = (new LevelRepository())->getLevelByLevelID($order->level_id);
                $create_time = explode(' ', $order->create_time)[1];
                $level_type_session = 0;
                if (false !== strpos($session->level_types, ',' . $level->type . ',')) {
                    $level_type_session = 1;
                }
                if ($session->start_time < $create_time && $session->end_time > $create_time & $level_type_session) {
                    $count++;
                    break;
                }
            }
        }
        return $count;
    }

    /**
     * 检查用户是否可以批判该等级的流量包
     * @throws MatchFinanceProductException
     */
    public function checkUserLevel($user_id, $level_id)
    {
        $memberLevelRepository = new MemberLevelRepository();
        //判断该等级是否可以做任务
        $user_level = $memberLevelRepository->with([
            'level' => function ($query) {
                $query->where('is_can_buy', 1);
            }, 'user'
        ])->findWhere([
            ['user_id', '=', $user_id],
            ['to_level', '=', $level_id],
            ['level_end_time', '>', time()],
        ]);

        if (empty($user_level)) {
            throw new MatchFinanceProductException([
                'msg' => lang('等级不匹配'),
                'errorCode' => 0,
                'extendedData' => [
                    'msg' => lang('等级不匹配'),
                    'code' => -1
                ]
            ]);
        }

        $level = $user_level->level;
        $user = $user_level->user;

        //判断该等级是否可以做任务
        if (!in_array($level->type, [
                LevelRepository::TYPE_EXPERIENCE, LevelRepository::TYPE_ACTIVITY
            ]) && $level->level_id !== $user->level_id) {
            throw new MatchFinanceProductException([
                'msg' => lang('等级不匹配'),
                'errorCode' => 0,
                'extendedData' => [
                    'msg' => lang('等级不匹配'),
                    'code' => -1
                ]
            ]);
        }

        return [$level, $user];
    }

    /**
     * 检查流量包匹配编码是否正确
     * @return void
     * @throws MatchFinanceProductException
     */
    public function checkPassword($level, $password)
    {
        // 判断编码是否正确
        if ($level->invest_password && in_array($level->type,
                [LevelRepository::TYPE_EXPERIENCE, LevelRepository::TYPE_ACTIVITY])) { // 体验区和活动才需要校验密码，普通区不需要
            if (!$password) {
                throw new MatchFinanceProductException([
                    'msg' => lang('请输入广告库存编码'),
                    'errorCode' => 0,
                    'extendedData' => [
                        'msg' => lang('请输入广告库存编码'),
                        'code' => -2
                    ]
                ]);
            }
            if ($password !== $level->invest_password) {
                throw new MatchFinanceProductException([
                    'msg' => lang('编码错误'),
                    'errorCode' => 0,
                    'extendedData' => [
                        'msg' => lang('编码错误'),
                        'code' => -2
                    ]
                ]);
            }
            if ($level->password_expire_time < time()) {
                throw new MatchFinanceProductException([
                    'msg' => lang('编码已过期'),
                    'errorCode' => 0,
                    'extendedData' => [
                        'msg' => lang('编码已过期'),
                        'code' => -2
                    ]
                ]);
            }
        }
    }

    public function getTeamLevel($level, $user_id, $level_id): array
    {
        // 如果有设置个人比例，按照个人设置的比例为主，翻仓计划为第二个，最后才是广告位配置里面的  2025年3月21日
        // 判断是否有上级的vip配置
        // 获取用户的tree_id, 用 / 分割，然后循环，如果有vip配置，就用vip用户的vip配置，如果没有，就用系统默认的vip配置
        $level_first = false; // 是否使用团队广告位配置
        $team_level = (new LevelTeamRepository())->getLevelTeamConfig($user_id, $level_id);
        if ($team_level) {
            $team_level->limit_unlock_num = $level->limit_unlock_num;
            $team_level->excess_invest_rate = $level->excess_invest_rate;
            $team_level->amount_range_inventory = $level->amount_range_inventory;
            $team_level->price = $level->price;
            $team_level->fee_rate = $level->fee_rate;
            $level = $team_level;
            $level_first = true;
        }

        return [$level, $level_first];
    }

    /**
     * 获取广告位时段订单记录
     * @param $level_id
     * @param $user_id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getLevelSessionOrders($level_id, $user_id): array
    {
        $startTime = microtime(true);
//        $level_data = (new LevelRepository())->getLevelByLevelID($level_id);
        // 获取当前的时段
        $task_session = (new TaskSessionRepository())->getCurrentTaskSession($level_id);

        $orders = [];
//        if ($level_data['type'] == LevelRepository::TYPE_EXPERIENCE) {
//            $level_items = (new LevelRepository())->getLevelByTypes(LevelRepository::TYPE_EXPERIENCE);
//            $level_ids = [];
//            foreach ($level_items as $level_item) {
//                $level_ids[] = $level_item->level_id;
//            }
//            //判断体验区
//            // 获取注册以来的广告位数量
//            $orders = $this->selectWhere([
//                ['user_id', '=', $user_id],
//                ['level_id', 'in', $level_ids],
//            ]);
//            // 获取已匹配的体验区场数
//            $count = $this->model->where([
//                ['user_id', '=', $user_id],
//                ['level_id', 'in', $level_ids],
//            ])->group('session_no')->count();
//            if ($count <= 20) {
//
//            }
//        } else {
        // 如果存在未匹配的广告位，只能购买该等级的广告位
        $pending_order = (new FinanceProductOrderModel())
            ->order(['id' => 'desc'])
            ->where([
                ['user_id', '=', $user_id],
                ['status', '=', FinanceProductOrderRepository::STATUS_PENDING]
            ])->find();
        if ($pending_order && $pending_order->session_no) {
            $orders = $this->selectWhere([
                ['user_id', '=', $user_id],
                ['level_id', '=', $pending_order->level_id],
                ['session_no', '=', $pending_order->session_no]
            ]);
        } else {
            if ($task_session) {
                // 获取今日已匹配/购买的广告库存（可能是之前时段未匹配，到当前时段匹配的，也可能是当前时段才匹配的）
                $order = $this->findWhere([
                    ['user_id', '=', $user_id],
                    ['level_id', '=', $level_id],
                    [
                        'match_time', 'between', [
                        Carbon::parse($task_session->start_time)->timestamp,
                        Carbon::parse($task_session->end_time)->timestamp
                    ]
                    ]
                ]);
                // 如果今日有匹配，直接通过 session_no 获取
                if ($order && $order->session_no) {
                    $orders = $this->selectWhere([
                        ['user_id', '=', $user_id],
                        ['level_id', '=', $level_id],
                        ['session_no', '=', $order->session_no]
                    ]);
                } else {
                    $orders = $this->selectWhere([
                        ['user_id', '=', $user_id],
                        ['level_id', '=', $level_id],
                        [
                            'create_time', 'between', [
                            Carbon::parse($task_session->start_time)->timestamp,
                            Carbon::parse($task_session->end_time)->timestamp
                        ]
                        ]
                    ]);
                }
            }
        }
//        }

        return [$orders, $task_session];
    }

    public function getLevelSessionOrdersV2($level_id, $user_id): array
    {
        // 获取当前的时段
        $task_session = (new TaskSessionRepository())->getCurrentTaskSessionTime($level_id);


        $orders = [];
        // 如果存在未匹配的广告位，只能购买该等级的广告位
        $pending_order = (new FinanceProductOrderModel())
            ->order(['id' => 'desc'])
            ->where([
                ['user_id', '=', $user_id],
                ['status', '=', FinanceProductOrderRepository::STATUS_PENDING]
            ])->find();
        if ($pending_order && $pending_order->session_no) {
            $orders = $this->selectWhere([
                ['user_id', '=', $user_id],
                ['level_id', '=', $pending_order->level_id],
                ['session_no', '=', $pending_order->session_no]
            ]);
        } else {
            if (!empty($task_session['time_interval'])) {
                // 获取今日已匹配/购买的广告库存（可能是之前时段未匹配，到当前时段匹配的，也可能是当前时段才匹配的）
                $order = $this->findWhere([
                    ['user_id', '=', $user_id],
                    ['level_id', '=', $level_id],
                    [
                        'match_time', 'between', [
                        Carbon::parse($task_session['time_interval']->start_time)->timestamp,
                        Carbon::parse($task_session['time_interval']->end_time)->timestamp
                    ]
                    ]
                ]);
                // 如果今日有匹配，直接通过 session_no 获取
                if ($order && $order->session_no) {
                    $orders = $this->selectWhere([
                        ['user_id', '=', $user_id],
                        ['level_id', '=', $level_id],
                        ['session_no', '=', $order->session_no]
                    ]);
                } else {
                    $orders = $this->selectWhere([
                        ['user_id', '=', $user_id],
                        ['level_id', '=', $level_id],
                        [
                            'create_time', 'between', [
                            Carbon::parse($task_session['time_interval']->start_time)->timestamp,
                            Carbon::parse($task_session['time_interval']->end_time)->timestamp
                        ]
                        ]
                    ]);
                }
            }
        }

        return [$orders, $task_session];
    }

    /**
     * 获取用户计划
     */
    public function getUserPlan($user_id, $level_id, $task_session_id, $ad_plan_id, $finance_plan_id)
    {
        if ($ad_plan_id) {
            $new_plan_id = AdPlanModel::updateUserPlanLevel($user_id);
            if ($new_plan_id) {
                $ad_plan_id = $new_plan_id;
            }
            $day = AdPlanModel::getDayCount($user_id, $new_plan_id, true);
            $plan = null;
            if ($day) {
                // 用户没有特定配置，使用通用配置
                $plan = AdPlanDaysConfig::where('ad_plan_id', $new_plan_id)
                    ->where('task_session_id', $task_session_id)
                    ->where('level_id', $level_id)
                    ->where('day', $day)
                    ->find();
                if ($plan) {
                    // 首先检查用户是否有特定配置
                    $userConfig = AdPlanUserDaysConfigModel::getUserConfig($user_id, $new_plan_id);
                    if (!empty($userConfig) && isset($userConfig[$day][$task_session_id][$level_id])) {
                        // 用户有特定配置，创建一个类似于AdPlanDaysConfig的对象
                        $plan->invest_profit = $userConfig[$day][$task_session_id][$level_id]['invest_profit'];
                        $plan->invest_rate = $userConfig[$day][$task_session_id][$level_id]['invest_rate'];
                        $plan->invest_period_type = $userConfig[$day][$task_session_id][$level_id]['invest_period_type'];
                        $plan->invest_day = $userConfig[$day][$task_session_id][$level_id]['invest_day'];
                    }
                }
            }
        } else {
            $start = microtime(true);
            // 获取计划
            if ($finance_plan_id) {
                $plan = (new FinancePlanRepository)
                    ->with(['task'])
                    ->where(['status' => 1])
                    ->first($finance_plan_id);
            } else {
                $plan = (new FinancePlanRepository)->with(['task'])
                    ->findWhere([
                        ['type', '=', FinancePlanModel::TYPE_MULTIPLE],
                        ['start_time', '<', date('H:i:s')],
                        ['end_time', '>', date('H:i:s')],
                        ['status', '=', 1]
                    ]);
            }
            trace_log("获取计划耗时: " . (microtime(true) - $start));
        }

        return [$plan, $ad_plan_id];
    }

    public function getOverdueMoney($user_money, $credit_score)
    { // 信用分逻辑
        // 获取信用分
        // 获取可预支倍数配置
        $credit_score_times = ConfigModel::getConfigValue('credit_score_times');
        $credit_score_times_config_arr = explode('_', $credit_score_times);
        $times = 1;
        foreach ($credit_score_times_config_arr as $item) {
            $itm = explode(':', $item);
            $score = explode('-', $itm[1]);
            if ($score[0] <= $credit_score && $score[1] >= $credit_score) {
                $times += floatval($item[0]);
                break;
            }
        }
        return $user_money - $times * $user_money;
    }

    /**
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException|MatchFinanceProductException
     */
    public function prepareCreateOrder(
        $orders,
        $level,//配置
        $user,
        $plan,
        $overdue_money,
        $match_type,
        $config,
        $session_no,
        $matched_order_count,
        $balance,//用户当前余额
        $level_first
    ): int
    {
        // 获取投资配置
//        Log::write('config: '.json_encode($config), 'debug');
//        trace_log(json_encode($config));
        $user_money = $balance;
        if (isset($config['stock_range']['settle_type']) && $config['stock_range']['settle_type'] == 2) {
            $balance = $config['stock_range']['min'];
//            trace_log("结算方式为：固定金额。settle_type: 2,balance = {$balance}");
        }

        // 当天第一次购买
        if ($orders->isEmpty()) {
            // 如果是活动,判断当前余额是否可以参加活动
            if (in_array($level->type, [LevelRepository::TYPE_EXPERIENCE, LevelRepository::TYPE_ACTIVITY])) {
                if ($balance < $level->price) {
                    throw new MatchFinanceProductException([
                        'msg' => lang('当前账户余额未满足该交易区最低竞价区间要求'),
                        'errorCode' => 0,
                        'extendedData' => [
                            'msg' => lang('当前账户余额未满足该交易区最低竞价区间要求'),
                            'code' => -1
                        ]
                    ]);
                }
            }
            if ($config['is_plan'] && $plan->task) {// 有计划详情
                foreach ($plan->task as $task) {
                    if ($balance * $task->rate / 100 < 1) {
                        throw new MatchFinanceProductException([
                            'msg' => lang('当前账户余额未满足该交易区最低竞价区间要求'),
                            'errorCode' => 0,
                            'extendedData' => [
                                'msg' => lang('当前账户余额未满足该交易区最低竞价区间要求'),
                                'code' => -1
                            ]
                        ]);
                    }
                }
                // 循环创建订单
                foreach ($plan->task as $index => $task) {
                    $amount = round($task->rate / 100 * $balance, 2);
                    $this->createOrder($index + $matched_order_count, $user->id, $task->rate, $amount, $task->cycle, $task->invest_period_type,
                        $task->profit, $level->level_id, $user_money, $overdue_money, $config, $session_no, $match_type, $level_first, $level->fee_rate);
                }
            } else {
                // 判断用户余额是否足够购买 $config['invest_num'] 个包
                if ($balance * $config['invest_rate'] / 100 / $config['invest_num'] < 1) {
                    throw new MatchFinanceProductException([
                        'msg' => lang('当前账户余额未满足该交易区最低竞价区间要求'),
                        'errorCode' => 0,
                        'extendedData' => [
                            'msg' => lang('当前账户余额未满足该交易区最低竞价区间要求'),
                            'code' => -1
                        ]
                    ]);
                }


                // 如果有超额单控广告位比例配置
                if (!empty($config['user_excess_ad_ratio_config'])) {
                    $user_excess_ad_ratio_config = $config['user_excess_ad_ratio_config'];
                    $ratios = json_decode($user_excess_ad_ratio_config['ratio'], true);
                    $ad_cycle = json_decode($user_excess_ad_ratio_config['ad_cycle'], true);
                    $profit_rate = json_decode($user_excess_ad_ratio_config['profit_rate'], true);
//                    if ($config['invest_num'] < count($ratios)) {
//                        // 截取 $config['invest_num'] 个元素
//                        $ratios = array_slice($ratios, 0, $config['invest_num']);
//                    }
                    foreach ($ratios as $index => $ratio) {
                        $amount = round($balance * $ratio / 100);
                        $this->createOrder($index + $matched_order_count, $user->id, $ratio, $amount, $ad_cycle[$index]['value'],
                            $ad_cycle[$index]['unit'], $profit_rate[$index],
                            $level->level_id, $user_money, $overdue_money, $config,
                            $session_no,
                            $match_type, $level_first, $level->fee_rate);
                    }
                } else {

//                    Log::write('match_type: '.$match_type, 'debug');
                    // 每个流量包金额计算
                    $result = $this->getInvestAmount($user, $config, $level, $match_type, $balance);
                    $invest_amount = $result['invest_amount'];
                    // 循环创建订单
                    foreach ($invest_amount as $index => $arr) {
                        if ($arr['invest_rate'] > 0) {
                            $this->createOrder($index + $matched_order_count, $user->id, $arr['invest_rate'], $arr['amount'], $arr['cycle'],
                                $arr['invest_period_type'], $arr['invest_profit'],
                                $level->level_id, $user_money, $overdue_money, $config, $session_no,
                                $match_type, $level_first, $level->fee_rate);
                        }
                    }
                }
            }
        }
        $orders = json_decode(json_encode($orders), true);
        if (empty($orders)) {
            $pending_orders = [];
        } else {
            $pending_orders = array_filter($orders, function ($item) {
                $status = $item['status'] ?? null;
                return $status == FinanceProductOrderRepository::STATUS_PENDING;
            });
        }
        $order_count = $this->getMatchedOrderCount($orders);
        if ($order_count >= $config['invest_num'] && count($pending_orders) == 0) {
            throw new MatchFinanceProductException([
                'msg' => lang('暂无可匹配的广告库存'),
                'errorCode' => 0,
                'extendedData' => [
                    'msg' => lang('暂无可匹配的广告库存'),
                    'code' => -1
                ]
            ]);
        }

        return $order_count + $matched_order_count;
    }

    /**
     * @throws MatchFinanceProductException
     */
    public function getPendingOrder($level_id, $user_id, $task_session, $order_count)
    {
        $order = [];
        if ($level_id == 0) {
            // 获取注册以来的广告位数量
            $order = $this->findWhere([
                ['user_id', '=', $user_id],
                ['level_id', '=', $level_id],
                ['status', '=', FinanceProductOrderRepository::STATUS_PENDING],
                ['sort', '=', $order_count + 1],
            ]);
        } else {
            // 如果存在未匹配的广告位，只能购买该等级的广告位
            $pending_order = (new FinanceProductOrderModel())
                ->order(['id' => 'desc'])
                ->where([
                    ['user_id', '=', $user_id],
                    ['status', '=', FinanceProductOrderRepository::STATUS_PENDING]
                ])->find();
            if ($pending_order && $pending_order['session_no']) {
                $order = $this->findWhere([
                    ['user_id', '=', $user_id],
                    ['level_id', '=', $pending_order->level_id],
                    ['session_no', '=', $pending_order['session_no']],
                    ['status', '=', FinanceProductOrderRepository::STATUS_PENDING],
                    ['sort', '=', $order_count + 1],
                ]);
            } else {
                if ($task_session) {
                    $order = $this->findWhere([
                        ['user_id', '=', $user_id],
                        ['level_id', '=', $level_id],
                        [
                            'create_time', 'between', [
                            Carbon::parse($task_session->start_time)->timestamp,
                            Carbon::parse($task_session->end_time)->timestamp
                        ]
                        ],
                        ['status', '=', FinanceProductOrderRepository::STATUS_PENDING],
                        ['sort', '=', $order_count + 1],
                    ]);
                }
            }
        }

        if (!$order) {
            throw new MatchFinanceProductException([
                'msg' => lang('暂无可匹配的广告库存'),
                'errorCode' => 0,
                'extendedData' => [
                    'msg' => lang('暂无可匹配的广告库存'),
                    'code' => -1
                ]
            ]);
        }
        return $order;
    }

    public function updateOrder($order, $user): array
    {
        $status = FinanceProductOrderRepository::STATUS_WAIT_BUY;
        $start_time = $buy_time = $end_time = 0;
        $match_time = time();
        if ($user->user_money >= $order->money || $user->user_money - $order->money >= $order->overdue_money) {
            $status = FinanceProductOrderRepository::STATUS_PURCHASED;
            $buy_time = time(); // 交易量增加信用分需要这个
//            $end_time = $this->calculateTimestampFromDays($order);
        } else {
            if ($user['user_money'] < $order->overdue_money) {
                throw new MatchFinanceProductException([
                    'msg' => lang('当前存在未完成订单，请完成后再继续交易'),
                    'errorCode' => 0,
                    'extendedData' => [
                        'msg' => lang('当前存在未完成订单，请完成后再继续交易'),
                        'code' => -1
                    ]
                ]);
            }
        }

        $order->status = $status;
        $order->start_time = $start_time;
        $order->buy_time = $buy_time;
        $order->end_time = $end_time;
        $order->match_time = $match_time;
        $order->update_time = time();
        $order->save();

        if ($user->user_money > $order->overdue_money) {
            // 判断用户是否充值过，充值过就增加一次拼多多抽奖机会
            // 判断上级是否可以领取pdd抽奖次数
//            PddTurntableCountModel::addLeader1Count($user->id, $user['recharge_money']);
        }
        // 开放时段内有购买，自己增加一次pdd抽奖机会
        PddTurntableCountModel::addCount($user->id, $order);
        // 更新用户广告位比例，设置状态位关
        (new LevelTeamRepository())->updateLevelTeamConfig($order->user_id, $order->level_id, [
            'status' => 0
        ]);

        // 订单生成成功，扣钱
        $money_log_model = new MoneyLogModel();
        $money_log_model->changeMoney($user->id, $order->money, MoneyLogModel::TYPE_USER, 'sub', '库存买入', '', 12,
            '', '', $order->id);

        $money_log_model->changeMoney($user->id, $order->fee, MoneyLogModel::TYPE_USER, 'sub', '交易费率', '', 30,
            '', '', $order->id);

        $productOrderTransform = new FinanceProductOrderTransform();
        $data = $productOrderTransform->transform($order);
        $data['user_money'] = $user->user_money - $order->money - $order->fee;
        $data['old_user_money'] = $user->user_money;
        $data['overdue_money'] = $order->overdue_money;
        $data['order_id'] = $order->id;
        event('MatchFinanceProductSuccess', $data);
        return $data;
    }


    /**
     * 检查用户金额是在区间内，并且库存是否足够
     * @param $level
     * @param $user
     * @return array
     * @throws Throwable
     */
    public static function isUserMoneyInRangeAndStockEnough($level, $user): array
    {
        // 用户余额不被金额区间的范围覆盖，检查除了金额区间的库存与总库存比较，如果总库存大于金额区间的库存，判断库存差是否已售完
        $level_id = $level->level_id;
        $user_id = $user['id'];
        // 获取当前的时段
        $task_session = (new TaskSessionRepository)->getCurrentTaskSession($level_id);
        // 获取等级金额区间库存
        $amount_range_inventory = json_decode($level->amount_range_inventory, true);
        // 定义一个当前区间的库存是否足够变量
        $current_inventory = false;
        $total_inventory = false;
        $can_over_match = false;
        // 获取用户金额区间与区间的金额
        $user_money_interval = 0;
        $stock_range = ['min' => 0, 'max' => 0, 'amount_range_excess_invest_rate' => []];

        if ($amount_range_inventory) {
            $amount_range_inventory_collect = collect($amount_range_inventory)
                ->where('min', '<=', $user['user_money'])
                ->where('max', '>=', $user['user_money']);
            // 判断用户金额区间是否有库存
            // 获取 $amount_range_inventory 最后一个档位的最大值
//            $last_max = end($amount_range_inventory)['max'];
            // 获取 $amount_range_inventory 第一个档位的最小值
//            $first_min = reset($amount_range_inventory)['min'];
            if (count($amount_range_inventory_collect) > 0) {
                $user_money_interval = 0;//金额在区间内
            }
            $amount_min_collect = collect($amount_range_inventory)
                ->where('min', '>', $user['user_money']);
            if (count($amount_min_collect) > 0) {
                $user_money_interval = -1;//小于该区间
            }
            $amount_min_collect = collect($amount_range_inventory)
                ->where('max', '<', $user['user_money']);
            if (count($amount_min_collect) > 0) {
                $user_money_interval = 1;//大于该区间
            }

            foreach ($amount_range_inventory_collect as $range) {

                // 获取该档位已购买人数
                $count = (new FinanceProductOrderRepository())
                    ->getSessionCount($range, $user_id, $level_id, $task_session);
                // 判断库存是否足够
                if ($range['stock'] > $count) {
                    $current_inventory = true; // 当前区间有库存
                    $stock_range = [
                        'min' => $range['min'],
                        'max' => $range['max'],
                        'rate' => $range['rate'], // 占余额比例
                        'profit' => $range['profit'], // 收益率
                        'period' => $range['period'], // 周期
                        'settle_type' => $range['settle_type'] ?? 1, // 结算方式1,用户余额，2购买区间起始金额
                        'period_type' => $range['period_type'], // 周期类型
                        'amount_range_excess_invest_rate' => [],
                    ];
                    if (key_exists('amount_range_excess_invest_rate', $range)) {
                        if ($amount_range_excess_invest_rate = json_decode($range['amount_range_excess_invest_rate'], true)) {
                            $stock_range['amount_range_excess_invest_rate'] = array_filter($amount_range_excess_invest_rate);
                        }
                    }
                    break;
                }
            }


            if (!$current_inventory) { // 没有区间库存，判断是否有区间超额配置
                foreach ($amount_range_inventory_collect as $range) {
                    // 金额区间库存已售完，判断是否有超额库存比例设置
                    if (key_exists('amount_range_excess_invest_rate', $range)) {
                        if ($amount_range_excess_invest_rate = json_decode($range['amount_range_excess_invest_rate'],
                            true)) {
                            $stock_range['amount_range_excess_invest_rate'] = array_filter($amount_range_excess_invest_rate);
                            if (array_sum(array_column($stock_range['amount_range_excess_invest_rate'], 'rate')) > 0) {
                                $stock_range = [
                                    'min' => 0, // 超额不计算库存，需要把这两个值设置为0
                                    'max' => 0, // 超额不计算库存，需要把这两个值设置为0
                                    'rate' => $range['rate'], // 占余额比例
                                    'profit' => $range['profit'], // 收益率
                                    'period' => $range['period'], // 周期
                                    'settle_type' => $range['settle_type'] ?? 1, // 结算方式1,用户余额，2购买区间起始金额
                                    'period_type' => $range['period_type'], // 周期类型
                                    'amount_range_excess_invest_rate' => $stock_range['amount_range_excess_invest_rate'],
                                    // 周期类型
                                ];
                                $can_over_match = true;
                                break;
                            }
                        }
                    }
                }
            }

            // 如果当前区间没有库存，判断总库存减区间总库存是否大于0
            if (!$current_inventory) {
                if ($level->limit_unlock_num == 0) {
                    $total_inventory = true; // 是否还有库存
                } else {
                    $other_range_stock = $level->limit_unlock_num - array_reduce($amount_range_inventory,
                            function ($carry, $item) {
                                return $carry + $item['stock'];
                            }, 0);
                    // 获取区间外购买人数
                    $count = (new FinanceProductOrderRepository())->getSessionCount(['min' => 0, 'max' => 0], $user_id,
                        $level_id, $task_session);
                    if ($other_range_stock > $count) {
                        $total_inventory = true; // 是否还有库存
                    }
                }
            }
        } else {
            if ($level->limit_unlock_num == 0) {
                $total_inventory = true;
            } else {
                // 获取区间外购买人数
                $count = (new FinanceProductOrderRepository())->getSessionCount(['min' => 0, 'max' => 0], $user_id,
                    $level_id, $task_session);
                if ($level->limit_unlock_num > $count) {
                    $total_inventory = true;
                }
            }
        }

        $excess_invest_rate = json_decode($level->excess_invest_rate);
        if ($excess_invest_rate && array_sum($excess_invest_rate) > 0) {
            $can_over_match = true;
        }

        // 单控群控超额匹配
        $tree_ids = $user->tree_id;
        $tree_ids = explode('/', $tree_ids);
        // 倒序
        $tree_ids = array_reverse(array_filter($tree_ids));
        foreach ($tree_ids as $tree_id) {
            $config = (new HomeUserExcessAdRatioConfigRepository())->getUserExcessAdRatioConfig($tree_id, $level_id);
            if ($config) {
                $temp_config = json_decode($config->ratio, true);
                if (empty($temp_config)) {
                    continue;
                }
                $ratio = array_filter(json_decode($config->ratio, true));
                if (count($ratio) == 0) {
                    continue;
                }
                if ($tree_id == $user->id || $config->subordinate == 1) {
                    $can_over_match = true;
                    break;
                }
            }
        }
        $res = [$current_inventory, $total_inventory, $can_over_match, $stock_range,$user_money_interval];
        return $res;
    }

    /**
     * 检查当前时段是否能匹配广告位
     * @param $level_id
     * @return void
     * @throws MatchFinanceProductException
     */
    public function canUnlockAtCurrentTime($level_id)
    {
        // 判断当前时间是否允许解锁
        $can_unlock_current_time = (new LevelModel())->canUnlockAtCurrentTime($level_id);
        if (!$can_unlock_current_time) {
            throw new MatchFinanceProductException([
                'msg' => lang('当前时间不允许匹配'),
                'errorCode' => 0,
                'extendedData' => [
                    'msg' => lang('当前时间不允许匹配'),
                    'code' => -1
                ]
            ]);
        }
    }

    // 获取已匹配的订单数量
    public function getMatchedOrderCount($orders): int
    {
        if (empty($orders)) {
            return 0;
        }
        $orders = json_decode(json_encode($orders), true);
        $count = 0;
        foreach ($orders as $item) {
            if (in_array($item['status'], [
                self::STATUS_WAIT_BUY,
                self::STATUS_PURCHASED,
                self::STATUS_SETTLED
            ])) {
                $count++;
            }
        }
        return $count;
    }


    // 是否需要弹窗提示
    public function isNeedToast($orders, $user, $level_id, $task_session): bool
    {
        $user_id = $user->id;
        $matched_order_count = $this->getMatchedOrderCount($orders);
        $order = $this->getPendingOrder($level_id, $user_id, $task_session, $matched_order_count);
        if ($user->user_money - $order->money < 0) {
            // 获取 session_no 是否有过超额匹配
            $order = $this->findWhere([
                ['user_id', '=', $user_id],
                ['level_id', '=', $level_id],
                ['session_no', '=', $order->session_no],
                ['match_type', '=', FinanceProductOrderRepository::MATCH_TYPE_OVERDUE]
            ]);
            // 如果上一个是超额匹配，后续不再弹窗
            if ($order) {
                return false;
            }
        }
        return true;
    }

    // 获取时段内订单数量
    public function getSessionCount($value, $user_id, $level_id, $task_session, $match_type = 1)
    {
        $this->redis = Cache::store('redis');
        $start_key = "session_start_order_id_" . Carbon::parse(date('Y-m-d') . ' ' . $task_session->start_time)->timestamp;
        $end_key = "session_end_order_id_" . Carbon::parse(date('Y-m-d') . ' ' . $task_session->end_time)->timestamp;
        $session_end_id = $this->redis->get($end_key);
        $session_start_id = $this->redis->get($start_key);
        trace_log("start_key:" . $start_key . "={$session_start_id}----------------end_key:" . $end_key . "={$session_end_id}");
        if (empty($session_start_id)) {
            $session_start_id = FinanceProductOrderModel::where('create_time', ">=", Carbon::parse(date('Y-m-d') . ' ' . $task_session->start_time)->timestamp)
                ->order("id", "asc")->value("id");
            if (empty($session_start_id)) {
                $session_start_id = FinanceProductOrderModel::order("id", "desc")->value("id");
            }
            if (!empty($session_start_id)) {
                $this->redis->set($start_key, $session_start_id, 60 * 60 * 24);
            }
        }

        // 如果 session_start_id 仍然为空，说明没有任何订单，直接返回 0
        if (empty($session_start_id)) {
            return 0;
        }

        $where = [
            ['level_id', '=', $level_id],
            ['stock_range_min', '=', $value['min']],
            ['stock_range_max', '=', $value['max']],
            ['finance_product_name', '=', "1"],
            ['match_type', '=', $match_type],
            ['id', '>=', $session_start_id],
        ];
        if (!empty($session_end_id)) {
            $where[] = ['id', '<=', $session_end_id];
        }
        // 获取该档位 $match_type 类型匹配的人数
        return FinanceProductOrderModel::where($where)->where('user_id', '<>', $user_id)
            ->count();
    }

    // 获取未匹配订单数（session_no 不为空)
    public static function getWaitBuyFinanceProductOrderCount($user_id)
    {
        // 判断用户是否有未匹配的广告位
        return (new FinanceProductOrderRepository)->whereCount([
            ['user_id', '=', $user_id],
            [
                'status', 'in',
                [FinanceProductOrderRepository::STATUS_PENDING, FinanceProductOrderRepository::STATUS_WAIT_BUY]
            ],
            ['session_no', '<>', '']
        ]);
    }

    /**
     * 获取广告库存平台配置
     * @param $level
     * @param $user
     * @param $cycle
     * @param $invest_period_type
     * @param $invest_profit
     * @param $invest_rate
     * @param $user_id
     * @return array
     * @throws Throwable
     */
    private function getLevelInvestConfig(
        $level,
        $user,
        $cycle,
        $invest_period_type,
        $invest_profit,
        $invest_rate,
        $user_id
    ): array
    {
        // 获取等级金额区间库存
        list($current_inventory, $total_inventory, $can_over_match, $stock_range,$user_money_interval) = FinanceProductOrderRepository::isUserMoneyInRangeAndStockEnough($level,
            $user);
        $amount_range = false;
        // 金额区间是否有库存
        if ($current_inventory || $stock_range['amount_range_excess_invest_rate']) {
            // 金额区间配置
            if ($stock_range['rate'] && $stock_range['profit'] && $stock_range['period']) {
                $amount_range = true;
                $cycle = $stock_range['period']; // 广告库存周期
                $invest_period_type = $stock_range['period_type']; // 广告库存周期类型(天、小时）
                $invest_profit = $stock_range['profit']; // 收益率
                $invest_rate = $stock_range['rate']; // 占余额比例
            }
        }
        // 如果不在理财时段内，使用等级配置
        if (!$amount_range) {
            $cycle = $level->invest_day; // 广告库存周期
            $invest_period_type = $level->invest_period_type; // 广告库存周期类型
            $invest_profit = $level->invest_profit; // 广告库存收益率
            $invest_rate = $this->getInvestRate($level, $user_id); // 广告库存占余额比例
        }
        return [$stock_range, $cycle, $invest_period_type, $invest_profit, $invest_rate];
    }

    // 获取体验区购买场数
    public function getExperienceSessionsCount($user_id): int
    {
        // 用户购买体验区的数量
        $count = $this->model->where(
            [
                ['user_id', '=', $user_id],
                ['level_id', '=', 0],
                ['finance_product_name', '=', '1'],
                ['status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]]]
        )->count();

        $level_items = (new LevelRepository())->getLevelByTypes(LevelRepository::TYPE_EXPERIENCE);
        $level_ids = [];
        foreach ($level_items as $level_item) {
            $level_ids[] = $level_item->level_id;
        }
        $task_sessions = (new TaskSessionRepository)->getTaskSessionList(LevelRepository::TYPE_EXPERIENCE);
        $orders = $this->selectWhere([
            ['user_id', '=', $user_id],
            ['level_id', 'in', $level_ids],
            ['status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]]
        ]);
        $buy_days = [];
        foreach ($orders as $order) {
            $match_time = date('Y-m-d H:i:s', $order->match_time);
            $explode_match_time = explode(' ', $match_time);
            $day = $explode_match_time[0];
            $time = $explode_match_time[1];
            $buy_days[$day][] = $time;
        }
        $buy_session_count = [];
        foreach ($buy_days as $day => $times) {
            foreach ($times as $time) {
                foreach ($task_sessions as $session) {
                    if ($time > $session->start_time && $time < $session->end_time) {
                        $buy_session_count[$day . ' ' . $session->start_time . '-' . $session->end_time] = 1;
                    }
                }
            }
        }
        return array_sum($buy_session_count);
    }

    // 获取注册以来首次购买广告位的用户
    public function getFirstBuyUser($date_arr): array
    {
        // 获取今日购买广告位的用户id
        $user_ids = $this->model->where([
            ['match_time', 'between', $date_arr]
        ])->group('user_id')->column('user_id');

        $sub_sql = $this->model->where([
            ['status', 'in', [FinanceProductOrderRepository::STATUS_WAIT_BUY, FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]],
            ['level_id', '>', 0],
            ['user_id', 'in', $user_ids]
        ])
            ->group('user_id,session_no')
            ->buildSql();

        return Db::table($sub_sql . ' t')
            ->group('user_id')
            ->having('count(user_id) = 1')
            ->column('session_no', 'user_id');
    }

    // 做任务人数（当天） 做任务的人数指的是当天购买广告的人数
    public function getTeamTodayTaskUserCount($user_ids)
    {
        $now = Carbon::now();
        $where = [
            ['user_id', 'in', $user_ids],
            ['create_time', 'between', [$now->startOfDay()->timestamp, $now->endOfDay()->timestamp]],
            ['level_id', '>', 0]
        ];
        return $this->model()->where($where)->group('user_id')->count();
    }
}
