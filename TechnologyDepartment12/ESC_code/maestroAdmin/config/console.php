<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        "app\command\UserFinanceSettle", // 用户投资结算
        "app\command\UserCreditScoreTaskSummary", // 信用分统计
        "app\command\CreditScoreCount", // 加减信用分
        "app\command\UserTaskSummary", // 每日用户推广统计
        "app\command\InviteActivity", // 邀请活动结算
        "app\command\PromotionReward", // 连续推广奖励
        "app\command\RefreshLevelPassword", // 更新广告位编码
        "app\command\LangTask", // 语言翻译
        "app\command\TestToken", // 测试Token命令
        "app\command\Test", // 测试命令
        "app\command\WorkerHttp", // 匹配处理
        "app\command\WorkerTimer", // 接收匹配队列
        "app\command\FinanceProduct", // 匹配队列处理
        "app\command\NewTeamActiveReward", // 新版本的团队活跃奖励统计
        "app\command\NewUserTaskSummary", // 新版本的团队活跃奖励统计
    ],
];
