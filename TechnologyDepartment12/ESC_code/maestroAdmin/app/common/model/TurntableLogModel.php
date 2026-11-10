<?php

namespace app\common\model;

use think\Model;

class TurntableLogModel extends Model
{
    const TYPE_REG = 1;//注册抽奖
    const TYPE_INV = 2;//邀请抽奖

    static $TYPE_NAME = [
        self::TYPE_REG => "注册抽奖",
        self::TYPE_INV => "注册抽奖",
    ];
    // 确定链接表名
    protected $name = 'turntable_log';

    /**
     * 添加抽奖记录
     * @param $user_id
     * @param $prize_id
     * @param $money
     * @param $type
     * @return bool
     */
    public static function addTurntableLog($user_id, $prize_id, $money, $name, $type)
    {
//        `user_id` int(11) DEFAULT '0' COMMENT '用户ID',
//  `prize_id` int(11) DEFAULT '0' COMMENT '奖品ID',
//  `money` decimal(20,4) DEFAULT '0.0000' COMMENT '抽奖金额',
//  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '奖品名称',
//  `type` int(11) DEFAULT '1' COMMENT '抽奖次数类型 1注册次数 2邀请的次数',
//  `create_time` int(11) DEFAULT '0' COMMENT '创建时间',
        $in_data = [
            'user_id' => $user_id,
            'prize_id' => $prize_id,
            'money' => $money,
            'name' => $name,
            'type' => $type,
            'create_time' => time(),
        ];
        $ret = self::insert($in_data);
        if ($ret) {
            return true;
        }
        return false;
    }

}
