<?php

namespace app\common\repository;

use app\common\model\ActivitynewPasswordModel;

class ActivityNewPasswordRepository extends Repository
{
    protected $model;

    public function __construct()
    {
        //绑定模型
        $this->model = new ActivitynewPasswordModel();
    }

    // 获取活动可用密码
    public function getPasswordByActivityId($activity_id, $password = '')
    {
        $where = [
            ['activity_id', '=', $activity_id]
        ];
        if ($password) {
            $where[] = ['password', '=', $password];
            return $this->findWhere($where);
        }
        return $this->selectWhere($where);
    }

    public function createOrUpdatePassword($activity_id, array $passwords): void
    {
        // 获取已有的密码记录
        $old_passwords = $this->selectWhere(['activity_id' => $activity_id])->toArray();
        // 获取新增数据，新增
        foreach ($passwords as $password) {
            if (empty($password['id'])) {
                unset($password['id']);
                $password['password_update_time'] = time();
                $password['activity_id'] = $activity_id;
                $this->create($password);
            }
        }

        // 获取已有id的数据
        $update_passwords = array_filter($passwords, function ($item) {
            return !empty($item['id']);
        });
        // 获取两个数组的差集,需要删除
        $diff_data = $this->getArrayDiffByIdLoop($old_passwords, $update_passwords);
        // 获取交集，更新交集
        $intersect = $this->getArrayIntersectByIdLoop($update_passwords, $old_passwords);
        // 删除旧数据
        $this->delete([
            ['id', 'in', array_column($diff_data, 'id')]
        ]);

        $update_data = [];
        foreach ($intersect as $key => $item) {
            // 查找原来id的密码，如果不同则更新
            $old_password = collect($old_passwords)->where('id', $item['id'])->first();
            $update_data[$key] = [
                'id'                   => $item['id'],
                'password'             => $item['password'],
                'remark'               => $item['remark'],
                'num'                  => $item['num'],
            ];
            if ($item['password'] != $old_password['password']) {
                $update_data[$key]['password_update_time'] = time();
            }
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

    public function checkPasswordStatus($user_id, $activity_id): bool
    {
        $record = (new ActivityNewPasswordRecordRepository())->findWhere([
            'activity_id' => $activity_id,
            'user_id'     => $user_id
        ]);
        if (!$record) {
            return false;
        }
        $activity_passwords = $this->getPasswordByActivityId($activity_id);
        // 如果参加时的密码和当前密码不一致，校验不通过
        $password = $activity_passwords->where('password', $record->password)->first();
        if ($password) {
            return true;
        } else {
            return false;
        }
    }
}
