<?php

namespace app\common\repository;

use app\common\model\MoneyLogModel;
use Carbon\Carbon;
use think\db\Where;


class MoneyLogRepository extends Repository
{
    protected $model;

    public function __construct()
    {

        //绑定模型
        $this->model = new MoneyLogModel();
    }

    public function getTodayMoney($user_ids, $value = [], $change = 1): float
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',' ,$user_ids);
            $where[] = ['user_id', 'in', $user_ids];
        }
        $where[] = ['status', '=', 1];
        $where[] = ['change', '=', $change]; // 1-增加 2-减少
        $where[] = ['detail_type', 'in', $value];
        $where[] = ['create_time', 'between', [Carbon::now()->startOfDay()->timestamp, Carbon::now()->endOfDay()->timestamp]];
        return $this->model->where($where)->sum('money');
    }

    public function getYesterdayMoney($user_ids, $value = [], $change = 1): float
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',' ,$user_ids);
            $where[] = ['user_id', 'in', $user_ids];
        }
        $where[] = ['status', '=', 1];
        $where[] = ['change', '=', $change]; // 1-增加 2-减少
        $where[] = ['detail_type', 'in', $value];
        $where[] = ['create_time', 'between', [Carbon::now()->subDay()->startOfDay()->timestamp, Carbon::now()->subDay()->endOfDay()->timestamp]];
        return $this->model->where($where)->sum('money');
    }
    public function getTotalMoney($user_ids, $value = [], $change = 1, $date = []): float
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',' ,$user_ids);
            $where[] = ['user_id', 'in', $user_ids];
        }
        $where[] = ['status', '=', 1];
        $where[] = ['change', '=', $change]; // 1-增加 2-减少
        if ($date) {
            $where[] = ['create_time', 'between', $date];
        }
        $where[] = ['detail_type', 'in', $value];
        return $this->model->where($where)->sum('money');
    }
}
