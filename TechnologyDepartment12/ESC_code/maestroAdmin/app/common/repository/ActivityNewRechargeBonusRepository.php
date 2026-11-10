<?php

namespace app\common\repository;

use app\common\model\ActivitynewRechargeBonusModel;

class ActivityNewRechargeBonusRepository extends Repository
{
    protected $model;

    public function __construct()
    {
        //绑定模型
        $this->model = new ActivitynewRechargeBonusModel();
    }


    // 获取活动可用金额门槛
    public function getRechargeBonusByActivityId($activity_id, $status = null)
    {
        $where = [
            ['activity_id', '=', $activity_id]
        ];
        if ($status) {
            $where[] = ['status', '=', $status];
        }
        return $this->selectWhere($where);
    }


    public function createOrUpdateRechargeBonus($activity_id, array $recharge_bonuses): void
    {
        // 获取已有的记录
        $old_recharge_bonuses = $this->selectWhere(['activity_id' => $activity_id])->toArray();
        // 获取新增数据，新增
        foreach ($recharge_bonuses as $recharge_bonus) {
            if (empty($recharge_bonus['id'])) {
                unset($recharge_bonus['id']);
                $recharge_bonus['activity_id'] = $activity_id;
                $this->create($recharge_bonus);
            }
        }

        // 获取已有id的数据
        $update_recharge_bonuses = array_filter($recharge_bonuses, function ($item) {
            return !empty($item['id']);
        });
        // 获取两个数组的差集,需要删除
        $diff_data = $this->getArrayDiffByIdLoop($old_recharge_bonuses, $update_recharge_bonuses);
        // 获取交集，更新交集
        $intersect = $this->getArrayIntersectByIdLoop($update_recharge_bonuses, $old_recharge_bonuses);
        // 删除旧数据
        $this->delete([
            ['id', 'in', array_column($diff_data, 'id')]
        ]);

        $update_data = [];
        foreach ($intersect as $item) {
            // 查找原来id的数据，如果不同则更新
            $update_data[] = [
                'id'     => $item['id'],
                'amount' => $item['amount'],
                'num'    => $item['num'],
                'rate'   => $item['rate'],
                'remark' => $item['remark'],
                'status' => $item['status'],
            ];
        }
        $this->model->saveAll($update_data);
    }

    // 获取两个数组的差集
    public function getArrayDiffByIdLoop($array1, $array2): array
    {
        // 创建一个以ID为键的数组，方便快速查找
        $idMap = [];
        foreach ($array2 as $item) {
            $idMap[$item['id']] = true;
        }

        // 筛选出不在$array2中的元素
        $result = [];
        foreach ($array1 as $item) {
            if (!isset($idMap[$item['id']])) {
                $result[] = $item;
            }
        }

        return $result;
    }

    // 获取两个数组的交集
    function getArrayIntersectByIdLoop($array1, $array2): array
    {
        // 创建一个以ID为键的数组，方便快速查找
        $idMap = [];
        foreach ($array2 as $item) {
            $idMap[$item['id']] = $item; // 保存完整元素
        }

        // 筛选出在两个数组中都存在的元素
        $result = [];
        foreach ($array1 as $item) {
            if (isset($idMap[$item['id']])) {
                $result[] = $item;
            }
        }

        return $result;
    }


}
