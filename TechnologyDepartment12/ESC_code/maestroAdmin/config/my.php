<?php

return [
    'urls' => [
        ['url' => 'index/index', 'name' => '首页'],
//        ['url' => 'index/notice', 'name' => '信息公告'],
//        ['url' => 'yuebao/index', 'name' => '余额宝'],
        ['url' => 'user/cash', 'name' => '提现'],
        ['url' => 'recharge/index', 'name' => '充值'],
        ['url' => 'service/index', 'name' => '客服中心'],
        ['url' => 'user/index', 'name' => '用户中心'],
        ['url' => 'user/share', 'name' => '推广海报'],
        ['url' => 'user/bind_card', 'name' => '绑定银行卡'],
        ['url' => 'user/usdt', 'name' => '绑定USDT'],
        ['url' => 'user/pass_word', 'name' => '修改密码'],
        ['url' => 'order/index', 'name' => '拼单记录'],
        ['url' => 'user/money_log', 'name' => '账户明细'],
//        ['url' => 'user/activity_log', 'name' => '活动奖励记录'],
        ['url' => 'user/cash_log', 'name' => '提现记录'],
//        ['url' => 'user/team_list', 'name' => '团队报表'],
        ['url' => 'recharge/lists', 'name' => '充值记录'],
//        ['url' => 'turntable/index', 'name' => '转盘抽奖'],
    ],
    'js_version' => 'v20240103',
    'css_version' => 'v20240103',
    'img_version' => 'v20240103',
    'app_version' => '8',
    'article_type' => [
        'article' => '普通文章',
        'help' => '帮助中心',
    ],
    'article_navs' => [
        '0' => '否',
        '1' => '首页菜单',
        '2' => '个人中心',
        '3' => '消息通知',
        '4' => '首页广告',
    ],
    'activity_new_type' => [
        '0' => '线下活动',
        '1' => '充值活动',
        '2' => '邀请活动',
        '3' => '邀请充值活动',
        '4' => '注册赠送活动',
//        '5' => '签到活动',
        '6' => '转盘抽奖活动',
        '7' => '邀请有效用户',
        '8' => '充值送金',
        '9' => '体验金',
    ],
    'activity_reward_type' => [
        '1' => '货币',
//        '2' => '积分',
        '3' => '转盘抽奖次数',
    ],
    'activity_new_user_type' => [
        '0' => '所有用户',
        '6' => '未充值用户',
        '1' => '充值用户',
        '2' => '二次充值用户',
        '3' => '新用户',
        '4' => '老用户',
        '5' => '有直属下级的用户',
    ],
    'activity_trigger_type' => [
        '1' => '自己充值',
        '2' => '下级充值',
        '7' => '下级首充',
        '3' => '用户登录',
        '4' => '下级注册',
        '5' => '当天抢完单',
        '6' => '存入余额宝',
        '8' => '会员升级',
    ],
    'redis_key' => [
        'config' => 'video_setting',//系统配置
        'banner' => 'banner_list',//banner列表
        'user_fake'=>'user_fake_list',//虚拟用户列表
        'notice'=>'notice_list',//公告列表
        'customer_service'=>'customer_service_list',//客服列表
    ],
    // 余额变更类型
    'money_type' => [
        '0' => '系统变更',
        '1' => '充值',
        '2' => '下级返佣',
        '3' => '拼单付款',
        '4' => '回购佣金',
        '9' => '回购',
        '5' => '余额宝-存入',
        '6' => '余额宝-取出',
        '7' => '提现',
        '8' => '提现失败',
        '20' => '积分',
    ],

    'is_xuni' => [
        '0' => '普通用户',
        '1' => '虚拟账号',
    ],

    'pay_type' => [
        1 => '银行卡',
        2 => '支付宝',
        3 => '第三方渠道',
        4 => 'USDT',
    ],
    'withdraw_type' => [
        1 => '银行卡',
        2 => '支付宝',
        4 => 'USDT充值',
    ],

    'message_type' => [
        '0' => '派单联单申请',
        '1' => '出入款咨询',
    ],
];
