<?php

namespace app\api\controller;

use app\common\model\ConfigModel;
use app\common\model\HomeUserVirtualModel;
use app\common\model\UserModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\MoneyLogRepository;
use Carbon\Carbon;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\response\Json;

class Team extends Base
{
    public function index(): Json
    {
        $start_time = $this->request->param('start_time/s');
        $end_time = $this->request->param('end_time/s');
        $day = false;
        if (!empty($start_time)) {
            $day = false;
            $end_time = empty($end_time) ? time() : strtotime($end_time);
            $time_arr = [
                'start_time' => strtotime($this->request->param('start_time/s')),
                'end_time'   => $end_time
            ];
        } else {
            $day = true;
            $time_arr = [
                'start_time' => strtotime("2024-10-10"),
                'end_time'   => time()
            ];
        }
        if ($day) {
            //总数
            $team_ids = UserModel::getTeamAllIds($this->userid, 3, false, []);
            $team_recharge = $team_ids ? UserModel::getTeamRecharge($team_ids, false, []) : 0;
            $team_withdrawals = $team_ids ? UserModel::getTeamWithdrawals($team_ids, false, []) : 0;
        } else {
            $team_ids = UserModel::getTeamAllIds($this->userid, 3, false, $time_arr);
            $team_recharge = UserModel::getTeamRecharge($team_ids, false, $time_arr);
            $team_withdrawals = UserModel::getTeamWithdrawals($team_ids, false, $time_arr);
        }
        $team_ids_new = UserModel::getTeamAllIds($this->userid, 3, true, $time_arr);
        $team_recharge_new = $team_ids ? UserModel::getTeamRecharge($team_ids, true, $time_arr) : '';
        $team_withdrawals_new = $team_ids ? UserModel::getTeamWithdrawals($team_ids, true, $time_arr) : '';
        $level_list = UserModel::getTeamLevelInfo($this->userid);
        $official_website_url = ConfigModel::getConfigValue('official_website_url');
        $share_link = $official_website_url."/#/register?type=2&ic=".$this->userInfo['invite_code'];
        $data = [
            'team_num'             => count($team_ids),
            'team_recharge'        => number_format($team_recharge, 2, '.', ','),
            'team_withdrawals'     => number_format($team_withdrawals, 2, '.', ','),
            'team_num_new'         => count($team_ids_new),
            'team_recharge_new'    => $team_recharge_new,
            'team_withdrawals_new' => $team_withdrawals_new,
            'level_list'           => $level_list,
            'share_link'           => $share_link,
        ];
        return json(msg(0, $data, ''));

    }

    /**
     * 团队成员列表
     * @param  int  $level
     * @return Json
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function detail(int $level = 1): Json
    {
        $team_ids = UserModel::getTeamIds($this->userid, $level);
        $team_list = UserModel::getTeamUserInfo($team_ids);
        return json(msg(0, $team_list, ''));
    }

    /**
     * 团队信息
     * @return Json
     * @throws Exception
     */
    public function team_info(): Json
    {
        // 2025年3月12日
        // 团队页面指标调整：
        //1、团队总收入：展示各级总回报之和+自己总的收益
        //2、今日团队收入：展示各级今日收益之和+自己今日的收益
        //3、团队余额改为今日新增充值量：展示今天新增的成员的充值金额
        $user =  UserModel::where('id', $this->userid)->field('is_xuni,show_virtual_data,team_info_month_reset')->find();
        if ($user['is_xuni'] == 1 && $user['show_virtual_data'] == 1) {
            $data = HomeUserVirtualModel::getUserVirtual($this->userid);
            return json(msg(0, $data, ''));
        }
        $start_time = $this->request->param('start_date/s');
        $end_date = $this->request->param('end_date/s');
        $time_arr = [];
        $where = [];
        $where[] = ['user_money', '>', 0];
        if (!empty($start_time) && !empty($end_date)) {
            $start_time = strtotime($start_time." 00:00:00");
            $end_time = strtotime($end_date." 23:59:59");
            $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
            $time_arr = ['start_time' => $start_time, 'end_time' => $end_time];
        } else {
            // 如果团队数据是否每月重新统计
            if ($user['team_info_month_reset']) {
                $time_arr = [
                    'start_time' => Carbon::now()->startOfMonth()->timestamp,
                    'end_time'   => Carbon::now()->endOfMonth()->timestamp
                ];
            } else {
                $time_arr = [
                    'start_time' => strtotime("2024-10-10"),
                    'end_time'   => time()
                ];
            }
        }

        //有效充值金额
        $recharge_num_valid_user = ConfigModel::getConfigValue('recharge_num_valid_user');

        $money_repository = new MoneyLogRepository();
        $team_all_ids = UserModel::where('tree_id', 'like', "%/{$this->userid}/%")
            ->where('tree_id', 'not like', '%/'.$this->userid.'/')
            ->column('id');
        $team_all_ids_str = implode(',', $team_all_ids ?: [0]);
        $team_three_ids = UserModel::getTeamAllIds($this->userid, 3, false, $time_arr);
        $team_two_ids = UserModel::getTeamAllIds($this->userid, 2, false, $time_arr);
        $team_one_ids = UserModel::getTeamAllIds($this->userid, 1, false, $time_arr);

        $team_only_three_ids = array_diff($team_three_ids, $team_two_ids);
        $team_two_ids = array_diff($team_two_ids, $team_one_ids);

        $finance_product_order_repository = new FinanceProductOrderRepository();

        //  团队有效人数
        // $team_one_active_count = UserModel::whereIn('id', $team_one_ids)
        //     ->where($where)
        //     ->where('recharge_money', '>', $recharge_num_valid_user)
        //     ->count();

        // 3任务返利 4储值返利 5抽奖活动 6签到活动 7升级返利 8分享奖励 9一级返利 10二级返利 11三级返利 12购买广告位 13AI智能托管理财 14AI智能托管收益 15直推奖励 16 广告位收入
        //  3, 4, 5, 6, 9, 10, 11, 14, 15, 16
        // 今日购买广告位的人数
        $team_one_today_finance_num = $team_one_ids ? $finance_product_order_repository->getTodayFinanceNum($team_one_ids, true,false) : 0;
        $team_one_recharge_sum = UserModel::whereIn('id', $team_one_ids)->where($where)->sum('recharge_money');
        $team_one_user_money_sum = UserModel::whereIn('id', $team_one_ids)->where($where)->sum('user_money');
        $team_one_brokerage_sum = $money_repository->getTotalMoney([$this->userid],
            [9], 1, [$time_arr['start_time'], $time_arr['end_time']]); // 总佣金 4储值返利 9一级返利 10二级返利 11三级返利
        $team_one_today_brokerage_sum = $money_repository->getTodayMoney([$this->userid],
            [9]); // 总佣金 4储值返利 9一级返利 10二级返利 11三级返利
        $team_two_today_finance_num = $team_two_ids? $finance_product_order_repository->getTodayFinanceNum($team_two_ids, true,false) : 0;
        $team_two_recharge_sum = UserModel::whereIn('id', $team_two_ids)->where($where)->sum('recharge_money');
        $team_two_user_money_sum = UserModel::whereIn('id', $team_two_ids)->where($where)->sum('user_money');
        $team_two_brokerage_sum = $money_repository->getTotalMoney([$this->userid],
            [10], 1, [$time_arr['start_time'], $time_arr['end_time']]); // 总佣金 4储值返利 9一级返利 10二级返利 11三级返利
        $team_two_today_brokerage_sum = $money_repository->getTodayMoney([$this->userid],
            [10]); // 总佣金 4储值返利 9一级返利 10二级返利 11三级返利
        // $team_three_active_count = UserModel::whereIn('id', $team_only_three_ids)
        //     ->where($where)
        //     ->where('recharge_money', '>', $recharge_num_valid_user)
        //     ->count();
        $team_three_today_finance_num = $team_only_three_ids ? $finance_product_order_repository->getTodayFinanceNum($team_only_three_ids, true,false) : 0;
        $team_three_recharge_sum = UserModel::whereIn('id', $team_only_three_ids)->where($where)->sum('recharge_money');
        $team_three_user_money_sum = UserModel::whereIn('id', $team_only_three_ids)->where($where)->sum('user_money');
        $team_three_brokerage_sum = $money_repository->getTotalMoney([$this->userid],
            [11], 1, [$time_arr['start_time'], $time_arr['end_time']]); // 总佣金 4储值返利 9一级返利 10二级返利 11三级返利
        $team_three_today_brokerage_sum = $money_repository->getTodayMoney([$this->userid],
            [11]); // 总佣金 4储值返利 9一级返利 10二级返利 11三级返利
        $total_count = count($team_all_ids); // 团队总人数：所有团队人数
        // 今日新增成员：当日团队下级新增的所有人数
        $today_user_recharge = UserModel::where('id in (' . $team_all_ids_str . ')')
            ->where('recharge_money', '>=', $recharge_num_valid_user)
            ->whereTime('create_time', 'today')
            ->count();
        // 今日新增充值量：当日新增注册充值的金额
        $today_user_recharge_sum = UserModel::where('id in (' . $team_all_ids_str . ')')
            ->where('recharge_money', '>=', $recharge_num_valid_user)
            ->whereTime('create_time', 'today')
            ->sum('recharge_money');
        $data = [
            'team' => [
                'one'     => [
                    'count'           => count($team_one_ids), // 团队人数
                    'active_count'    => $team_one_today_finance_num, // 活跃人数
                    'total_recharge'  => i18n_currency($team_one_recharge_sum), // 总充值
                    'total_brokerage' => i18n_currency($team_one_brokerage_sum), // 总佣金
                    'today_brokerage' => i18n_currency($team_one_today_brokerage_sum), // 今日佣金
                    'total_balance' => i18n_currency($team_one_user_money_sum), // 团队余额
                ],
                'two'     => [
                    'count'           => count($team_two_ids), // 团队人数
                    'active_count'    => $team_two_today_finance_num, // 活跃人数
                    'total_recharge'  => i18n_currency($team_two_recharge_sum), // 总充值
                    'total_brokerage' => i18n_currency($team_two_brokerage_sum), // 总佣金
                    'today_brokerage' => i18n_currency($team_two_today_brokerage_sum), // 今日佣金
                    'total_balance' => i18n_currency($team_two_user_money_sum), // 团队余额
                ],
                'three'   => [
                    'count'           => count($team_only_three_ids), // 团队人数
                    'active_count'    => $team_three_today_finance_num, // 活跃人数
                    'total_recharge'  => i18n_currency($team_three_recharge_sum), // 总充值
                    'total_brokerage' => i18n_currency($team_three_brokerage_sum), // 总佣金
                    'today_brokerage' => i18n_currency($team_three_today_brokerage_sum), // 今日佣金
                    'total_balance' => i18n_currency($team_three_user_money_sum), // 团队余额
                ],
                'summary' => [
                    'total_count'               => $total_count, // 团队成员总数
//                    'total_team_recharge'       => i18n_currency($team_one_recharge_sum), // 总充值
                    'today_user_recharge_sum'   => i18n_currency($today_user_recharge_sum), // 今日新增充值量：当日新增注册充值的金额
                    'today_user_recharge_count' => $today_user_recharge, // 今日新增成员：当日团队下级新增的所有人数
                ]

            ],
            // 饼状图指标调整，均展示自己的收入数据：
            //1、个人库存利润（通过购买广告位所得的收入）
            //2、AI智能托管收益（通过AI智能托管所得的收入）
            //3、代理团队佣金奖励（通过下级用户购买广告位产生收益所得的收入）
            //4、推广及其他奖励（通过直推任务、转盘、签到所得的收入）
            'team_statistics' => [ // 图表
                // 广告位收入
                'total_invest_income'    => i18n_sprintf($money_repository->getTotalMoney([$this->userid], [16], 1,
                    [$time_arr['start_time'], $time_arr['end_time']])),
                // AI智能托管收入
                'total_ai_income'        => i18n_sprintf($money_repository->getTotalMoney([$this->userid], [14], 1,
                    [$time_arr['start_time'], $time_arr['end_time']])),
                //  代理团队佣金奖励
                'total_brokerage_income' => i18n_sprintf($money_repository->getTotalMoney([$this->userid],
                    [4, 9, 10, 11], 1, [$time_arr['start_time'], $time_arr['end_time']])), // 4储值返利 9一级返利 10二级返利 11三级返利
                // 推广及其他奖励
                'total_invite_income'    => i18n_sprintf($money_repository->getTotalMoney([$this->userid],
                    [5, 6, 8, 15, 19], 1, [$time_arr['start_time'], $time_arr['end_time']])), // 推广及其他奖励
            ],
            'user'            => [
                // 3任务返利 4储值返利 5抽奖活动 6签到活动 7升级返利 8分享奖励 9一级返利 10二级返利 11三级返利 12购买广告位 13AI智能托管理财 14AI智能托管收益 15直推奖励 16 广告位收入 17 解锁vip 18 解锁vip失败 19团队活跃奖
                'today_income' =>  i18n_currency($team_all_ids ?$money_repository->getTodayMoney($team_all_ids,
                    [3, 4, 5, 6, 9, 10, 11, 14, 15, 16]) : 0), // 今日收益
                'total_income' => i18n_currency($team_all_ids ? $money_repository->getTotalMoney($team_all_ids,
                    [3, 4, 5, 6, 9, 10, 11, 14, 15, 16], 1, [$time_arr['start_time'], $time_arr['end_time']]): 0) , // 累计收益
            ]
        ];
        return json(msg(0, $data, ''));
    }

}
