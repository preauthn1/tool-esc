<?php

namespace app\common\model;

use think\Model;

class PlanTaskModel extends Model
{
    // 确定链接表名
    protected $name = 'plan_task';

    /**
     * 爬取任务
     * @param $user_id
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getPlanTask($user_id)
    {

        $task_model = new TaskModel();
        $plan_id = UserModel::getFieldData($user_id, 'plan_id');
        $plan_task_id = $task_model->getLastPlanId($user_id, $plan_id);
        if ($plan_task_id === false) {
            return [];
        }
        $plan_model = new PlanModel();
        $plan = $plan_model->where('id', $plan_id)->find();
        if (empty($plan)) {
            return [];
        }
        $plan_list = $this->where('plan_id', $plan_id)->order('sort ASC')->select();
        $plan_list_next = $this->where('plan_id', $plan['next_id'])->order('sort ASC')->select();
        $plan_list = array_merge($plan_list, $plan_list_next);
        if (empty($plan_list)) {
            return [];
        }
        $index = 0;
        if ($plan_task_id == '') {
            return $plan_list[$index];
        }
        foreach ($plan_list as $key => $value) {
            if ($value['id'] == $plan_task_id) {
                $index = $key;
            }
        }
        if ($plan['loop'] == 1) {
            if (($index + 1) >= count($plan_list)) {
                $index = 0;
            } else {
                $index += 1;
            }
        } else {
            if (($index + 1) >= count($plan_list)) {
                return [];
            } else {
                $index += 1;
            }
        }
        return $plan_list[$index];
    }

    /**
     * 判断计划任务是否存在
     * @param $user_id
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getHasDayPlanTask($user_id)
    {
        $map = [
            'user_id' => $user_id,
            'task_type' => TaskModel::TYPE_PLAN,
            'day' => date("Y-m-d")
        ];
        $task_model = new TaskModel();
        $plan_task = $task_model->where($map)->order('id DESC')->find();
        if (empty($plan_task)) {
            //可自动刷新任务
            return 1;
        }
        if ($plan_task['status'] == 1) {
            //不可添加任务
            return 0;
        } else {
            //可爬取任务
            return 2;
        }
    }
}
