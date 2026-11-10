<?php

namespace app\common\model;

use think\Model;

class HomeUserVirtualModel extends Model
{
    // 确定链接表名
    protected $name = 'home_user_virtual';

    /**
     * 获取用户虚拟数据
     * @param string $user_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getUserVirtual($user_id = '')
    {
        $user_data = self::where('user_id', $user_id)->find();
        if (empty($user_data)) {
            // `user_money` decimal(15,2) DEFAULT '0.00' COMMENT '账户余额',
            //  `user_income` decimal(15,2) DEFAULT '0.00' COMMENT '用户总收入',
            //  `user_brokerage_income` decimal(15,2) DEFAULT '0.00' COMMENT '用户佣金总收入',
            //  `user_recharge_money` decimal(15,2) DEFAULT '0.00' COMMENT '累计储值',
            //  `user_withdrawal_amount` decimal(15,2) DEFAULT '0.00' COMMENT '累计提款',
            //  `user_team_num` int(11) DEFAULT '0' COMMENT '团队总规模',
            //  `user_today_team_num` int(11) DEFAULT '0' COMMENT '今日新增',
            // `one_count` int DEFAULT '0' COMMENT '1级团队人数',
            //  `one_active_count` int DEFAULT '0' COMMENT '1级团队活跃人数',
            //  `one_total_recharge` decimal(15,2) DEFAULT '0.00' COMMENT '1级团队总充值',
            //  `one_total_brokerage` decimal(15,2) DEFAULT '0.00' COMMENT '1级团队总佣金',
            //  `one_today_brokerage` decimal(15,2) DEFAULT '0.00' COMMENT '1级团队今日佣金',
            //  `tow_count` int DEFAULT '0' COMMENT '2级团队人数',
            //  `two_active_count` int DEFAULT '0' COMMENT '2级团队活跃人数',
            //  `two_total_recharge` decimal(15,2) DEFAULT '0.00' COMMENT '2级团队总充值',
            //  `two_total_brokerage` decimal(15,2) DEFAULT '0.00' COMMENT '2级团队总佣金',
            //  `two_today_brokerage` decimal(15,2) DEFAULT '0.00' COMMENT '2级团队今日佣金',
            //  `three_count` int DEFAULT '0' COMMENT '3级团队人数',
            //  `three_active_count` int DEFAULT '0' COMMENT '3级团队活跃人数',
            //  `three_total_recharge` decimal(15,2) DEFAULT '0.00' COMMENT '3级团队总充值',
            //  `three_total_brokerage` decimal(15,2) DEFAULT '0.00' COMMENT '3级团队总佣金',
            //  `three_today_brokerage` decimal(15,2) DEFAULT '0.00' COMMENT '3级团队今日佣金',
            //  `summary_today_user_count` int DEFAULT '0' COMMENT '团队今日总新增',
            //  `summary_total_count` int DEFAULT '0' COMMENT '团队总人数',
            //  `summary_total_team_recharge` decimal(15,2) DEFAULT '0.00' COMMENT '团队总充值',
            //  `summary_today_user_recharge` int DEFAULT '0' COMMENT '团队今日充值并充值人数',
            //  `total_invite_income` decimal(15,2) DEFAULT '0.00' COMMENT '总佣金收益',
            //  `total_invest_income` decimal(15,2) DEFAULT '0.00' COMMENT '总投注收益',
            //  `total_task_income` decimal(15,2) DEFAULT '0.00' COMMENT '总任务收益',
            //  `user_today_income` decimal(15,2) DEFAULT '0.00' COMMENT '用户今日收益',
            //  `user_total_income` decimal(15,2) DEFAULT '0.00' COMMENT '用户累计收益',
            //  `now_day_rec` int DEFAULT '0' COMMENT '今日直推人数',
            //  `now_week_rec` int DEFAULT '0' COMMENT '本周直推人数',
            //  `now_month_rec` int DEFAULT '0' COMMENT '本月直推人数',
            // `now_day_status` int DEFAULT '0' COMMENT '今日直推领取状态 0未完成 1未领取 2已领取',
            //  `now_week_status` int DEFAULT '0' COMMENT '本周直推领取状态 0未完成 1未领取 2已领取',
            //  `now_month_status` int DEFAULT '0' COMMENT '本月直推领取状态 0未完成 1未领取 2已领取',

            $user_data = [
                'user_id' => $user_id,
                'user_money' => 0,
                'user_income' => 0,
                'user_brokerage_income' => 0,
                'user_recharge_money' => 0,
                'user_withdrawal_amount' => 0,
                'user_team_num' => 0,
                'user_today_team_num' => 0,
                'one_count' => 0,
                'one_active_count' => 0,
                'one_total_recharge' => 0,
                'one_total_brokerage' => 0,
                'one_today_brokerage' => 0,
                'two_count' => 0,
                'two_active_count' => 0,
                'two_total_recharge' => 0,
                'two_total_brokerage' => 0,
                'two_today_brokerage' => 0,
                'three_count' => 0,
                'three_active_count' => 0,
                'three_total_recharge' => 0,
                'three_total_brokerage' => 0,
                'three_today_brokerage' => 0,
                'summary_today_user_count' => 0,
                'summary_total_count' => 0,
                'summary_total_team_recharge' => 0,
                'summary_today_user_recharge' => 0,
                'total_invite_income' => 0,
                'total_invest_income' => 0,
                'total_task_income' => 0,
                'total_ai_income' => 0,
                'total_brokerage_income' => 0,
                'user_today_income' => 0,
                'now_day_rec' => 0,
                'now_week_rec' => 0,
                'now_month_rec' => 0,
                'now_day_status' => 0,
                'now_week_status' => 0,
                'now_month_status' => 0,
                'update_time' => time(),
                'credit_score' => 0,
                'total_count' => 0,
                'today_user_recharge_sum' => 0,
                'today_user_recharge_count' => 0,
                'user_total_income' => 0,
                'team_active_level1_num' => 0,
                'team_active_level1_status' => 0,
                'team_active_level2_num' => 0,
                'team_active_level2_status' => 0,
                'team_active_level3_num' => 0,
                'team_active_level3_status' => 0,
                'team_active_level4_num' => 0,
                'team_active_level4_status' => 0,
                'credit_score_trade' => 0,
                'credit_score_active' => 0,
                'credit_score_new' => 0,
            ];
            $id = self::insertGetId($user_data);
            $user_data['id'] = $id;
        }

        $data = [
            'team' => [
                'one' => [
                    'count' => $user_data['one_count'], // 团队人数
                    'active_count' => $user_data['one_active_count'], // 活跃人数
                    'total_recharge' => $user_data['one_total_recharge'], // 总充值
                    'total_brokerage' => $user_data['one_total_brokerage'], // 总佣金
                    'today_brokerage' => $user_data['one_today_brokerage'], // 今日佣金
                ],
                'two' => [
                    'count' => $user_data['two_count'], // 团队人数
                    'active_count' => $user_data['two_active_count'], // 活跃人数
                    'total_recharge' => $user_data['two_total_recharge'], // 总充值
                    'total_brokerage' => $user_data['two_total_brokerage'], // 总佣金
                    'today_brokerage' => $user_data['two_today_brokerage'], // 今日佣金
                ],
                'three' => [
                    'count' => $user_data['three_count'], // 团队人数
                    'active_count' => $user_data['three_active_count'], // 活跃人数
                    'total_recharge' => $user_data['three_total_recharge'], // 总充值
                    'total_brokerage' => $user_data['three_total_brokerage'], // 总佣金
                    'today_brokerage' => $user_data['three_today_brokerage'], // 今日佣金
                ],
                'summary' => [
                    'total_count' => $user_data['total_count'], // 团队成员总数
                    'today_user_recharge_sum' => $user_data['today_user_recharge_sum'], // 今日新增充值量
                    'today_user_recharge_count' => $user_data['today_user_recharge_count'], //  今日新增成员
                ]

            ],
            'team_statistics' => [ // 图表
                'total_invite_income' => $user_data['total_invite_income'], // 总佣金收入
                'total_invest_income' => $user_data['total_invest_income'], // 总投资收入
                'total_ai_income' => $user_data['total_ai_income'], // 总任务收入
                'total_brokerage_income' => $user_data['total_brokerage_income'], // 总佣金
            ],
            'user' => [
                // 3任务返利 4储值返利 5抽奖活动 6签到活动 7升级返利 8分享奖励 9一级返利 10二级返利 11三级返利 12广告位 13AI智能托管理财 14AI智能托管收益
                'today_income' => $user_data['user_today_income'], // 今日收益
                'total_income' => $user_data['user_total_income'], // 累计收益
                'user_money' => $user_data['user_money'],// 用户余额
                'user_income' => $user_data['user_income'],// 用户收益
                'user_brokerage_income' => $user_data['user_brokerage_income'],// 用户佣金收益
                'user_recharge_money' => $user_data['user_recharge_money'],// 用户充值金额
                'user_withdrawal_amount' => $user_data['user_withdrawal_amount'],// 用户提现金额
                'user_team_num' => $user_data['user_team_num'],// 用户团队人数
                'user_today_team_num' => $user_data['user_today_team_num'],// 用户今日团队人数2
                'credit_score' => $user_data['credit_score']// 信用分
            ],
            'rec' => [
                'now_day_rec' => $user_data['now_day_rec'],// 用户团队人数
                'now_week_rec' => $user_data['now_week_rec'],// 用户今日团队人数
                'now_month_rec' => $user_data['now_month_rec'],// 用户今日团队人数
                'now_day_status' => $user_data['now_day_status'],// 用户今日领取状态
                'now_week_status' => $user_data['now_week_status'],// 用户本周领取状态
                'now_month_status' => $user_data['now_month_status'],// 用户本月领取状态
            ],
            'team_active_reward' => [
                'level1' => [
                    'reward_count' => $user_data['team_active_level1_num'],
                    'can_reward' => $user_data['team_active_level1_status']
                ],
                'level2' => [
                    'reward_count' => $user_data['team_active_level2_num'],
                    'can_reward' => $user_data['team_active_level2_status']
                ],
                'level3' => [
                    'reward_count' => $user_data['team_active_level3_num'],
                    'can_reward' => $user_data['team_active_level3_status']
                ],
                'level4' => [
                    'reward_count' => $user_data['team_active_level4_num'],
                    'can_reward' => $user_data['team_active_level4_status']
                ]
            ]
        ];
        return $data;
    }
}
