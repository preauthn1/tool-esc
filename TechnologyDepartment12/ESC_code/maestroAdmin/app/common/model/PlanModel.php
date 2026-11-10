<?php

namespace app\common\model;

use think\Model;

class PlanModel extends Model
{
    const LOOP_OPEN = 1;//开启循环
    const LOOP_CLOSE = 2;//关闭循环

    const TYPE_MULTIPLE = 1;//1按照账户总额倍数
    const TYPE_FIXED = 2;//2固定充值金额

    static $LOOP_NAME = [
        self::LOOP_OPEN => "开启",
        self::LOOP_CLOSE => "关闭",
    ];

    static $TYPE_NAME = [
        self::TYPE_MULTIPLE => "总额倍数",
        self::TYPE_FIXED => "固定充值",
    ];
    // 确定链接表名
    protected $name = 'plan';

    /**
     * 获取计划任务名称
     * @param $id
     * @return mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getPlanName($id)
    {
        $plan = self::where('id', $id)->field('name')->find();
        if (empty($plan)) {
            return "";
        }
        return $plan['name'];
    }

    /**
     * 获取计划列表
     * @return array|bool|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getPlanList()
    {
        $plan = self::field('id,name')->select();
        if (empty($plan)) {
            return [];
        }
        return $plan;
    }
}
