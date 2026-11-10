<?php

namespace app\api\controller;

use app\common\model\ConfigModel;
use app\common\model\TaskModel;
use app\common\model\UserModel;

//任务中心
class Mission extends Base
{
//    public function __construct(Request $request = null)
//    {
//        $this->userid = 1;
//        parent::__construct($request);
//    }

    // 首页
    public function index()
    {
        $task_model = new TaskModel();
//        $task_model->updateTask($this->userid);
        $day24 = false;
        $hours_24 = ConfigModel::getConfigValue('task_reset_type');
        if ($hours_24 == 1) {
            $day24 = true;
        }
        $task_count = $task_model->getTaskNumber($this->userid, $day24);
        $plan_id = UserModel::getFieldData($this->userid, 'plan_id');
        if (empty($plan_id)) {
            $plan_id = 0;
            $task_list = $task_model->getTaskList($this->userid, TaskModel::TYPE_GOODS, $day24);
        } else {
            $task_list = $task_model->getTaskList($this->userid, TaskModel::TYPE_PLAN, $day24);
        }

        $task_plan = $task_model->getTaskPlanNumber($this->userid);
        $user_money = UserModel::getFieldData($this->userid, 'user_money');
        $brokerage_money = UserModel::getFieldData($this->userid, 'brokerage_money');
        $data = [
            'task_list' => $task_list,
            'task_count' => $task_count,
            'task_plan' => $task_plan,
            'plan_id' => $plan_id,
            'all_user_money' => number_format($user_money + $brokerage_money, 2, '.', ',')
        ];
        return json(msg(0, $data, ''));
    }


    /**
     * 刷新数据
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function indexData()
    {
        $task_model = new TaskModel();
        $day24 = false;
        $hours_24 = ConfigModel::getConfigValue('task_reset_type');
        if ($hours_24 == 1) {
            $day24 = true;
        }
        $day_task = $task_model->getTaskNumber($this->userid, $day24);
        $plan_task = $task_model->getTaskPlanNumber($this->userid);
        $user_money = UserModel::getFieldData($this->userid, 'user_money');
        $brokerage_money = UserModel::getFieldData($this->userid, 'brokerage_money');
        $out_data = [
            'day_task_all' => $day_task['day_task_all'],
            'day_task_ing' => $day_task['day_task_ing'],
            'reset_time' => $day_task['reset_time'],
            'plan_task_all' => $plan_task['task_all'],
            'plan_task_ing' => $plan_task['task_ing'],
            'plan_task_end' => $plan_task['task_end'],
            'all_user_money' => number_format($user_money + $brokerage_money, 2, '.', ',')
        ];
        $this->success(lang('成功'), '', $out_data);
    }

    /**
     * 视频评分
     * @param int $level_id
     * @param int $video_id
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function addVideoTask($level_id = 0, $video_id = 0)
    {
        $task_model = new TaskModel();
        $out_data = $task_model->addVideoTask($this->userid, $level_id, $video_id);
        if ($out_data['code'] == 1) {
            return json(msg(0, '', lang($out_data['msg'])));
        } else {
            return json(msg(-1, '', lang($out_data['msg'])));
        }
    }

    /**
     * 完成任务
     * @param string $task_id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function endTask($task_id = '')
    {
        $map = [
            'id' => $task_id,
            'user_id' => $this->userid
        ];
        $task_model = new TaskModel();
        $temp = $task_model->where($map)->find();
        $out_data = [
            'code' => 2,
            'msg' => '操作失败'
        ];
        if ($temp['task_type'] == TaskModel::TYPE_GOODS) {
            $out_data = $task_model->endTask($task_id, $this->userid);
        }
        if ($temp['task_type'] == TaskModel::TYPE_PLAN) {
            $out_data = $task_model->endPlanTask($task_id, $this->userid);
            if ($out_data['code'] == 16) {
                return json(msg(0, $out_data, lang($out_data['msg'])));
            }
        }
        if ($out_data['code'] == 1) {
            return json(msg(0, '', lang($out_data['msg'])));
        } else {
            return json(msg(-1, '', lang($out_data['msg'])));
        }
    }

    /**
     * 爬取任务
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function crawlTask()
    {
        $task_model = new TaskModel();
        $out_data = $task_model->addPlanTask($this->userid, 1, 1);
        if ($out_data['code'] == 1) {
            $this->success(lang($out_data['msg']));
        } else {
            $this->error(lang($out_data['msg']));
        }
    }
}
