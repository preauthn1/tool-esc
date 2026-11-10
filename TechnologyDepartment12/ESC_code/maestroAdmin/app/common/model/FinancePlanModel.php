<?php

namespace app\common\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;
use think\model\relation\HasMany;

class FinancePlanModel extends Model
{
    const TYPE_SINGLE = 1;//1单控
    const TYPE_MULTIPLE = 2;//2群控

    static $TYPE_NAME = [
        self::TYPE_SINGLE => "单控",
        self::TYPE_MULTIPLE => "群控",
    ];
    // 确定链接表名
    protected $name = 'finance_plan';

    /**
     * 获取计划任务名称
     * @param $id
     * @return mixed|string
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
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
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public static function getPlanList()
    {
        $plan = self::field('id,name')->select();
        if (empty($plan)) {
            return [];
        }
        return $plan;
    }

    public function task(): HasMany
    {
        return $this->hasMany(FinancePlanTaskModel::class, 'plan_id', 'id');
    }
}
