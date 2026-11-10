<?php

namespace app\common\repository;

use app\admin\model\LevelModel;
use app\common\model\ConfigModel;
use app\common\model\TaskModel;
use app\common\model\TaskSessionModel;
use Carbon\Carbon;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

class TaskRepository extends Repository
{
    protected $model;

    public function __construct()
    {

        //绑定模型
        $this->model = new TaskModel();
    }


    /**
     * 获取任务重置倒计时
     * @param $user_id
     * @return float|int|string
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function getTaskResetCountdown($user_id)
    {

        $task_reset_type = ConfigModel::getConfigValue('task_reset_type');

        if ($task_reset_type == 1) {
            //绝对24小时
            $where = [
                ['user_id', '=', $user_id],
                ['create_time', '>', Carbon::now()->subDay()->timestamp],
                ['task_type', 'in', [TaskModel::TYPE_GOODS, TaskModel::TYPE_PRODUCT, TaskModel::TYPE_VIDEO]]
            ];
            $task_last = $this->order(['id' => 'DESC'])->findWhere($where);
            if ($task_last) {
                $task_his_time = strtotime($task_last['create_time']);
            } else {
                $task_his_time = 0;
            }
            //下次任务重置时间
            return time() >= $task_his_time ? (($task_his_time + (24 * 3600)) - time()) : 0;
        } elseif ($task_reset_type == 2) {
            // 固定时间
            $task_reset_time = ConfigModel::getConfigValue('task_reset_time'); // hh:mm
            // 判断当前时间是否大于设定的时间
            $now = Carbon::now();
            $reset_time = Carbon::createFromFormat('H:i', $task_reset_time);

            if ($now->gt($reset_time)) {
                // 超过设定的时间，则重置时间为明天
                return $reset_time->addDay()->diffInSeconds($now);
            }
        } else if ($task_reset_type == 3) {
            return $this->getTaskTimeLeft();
        }

        return 0;
    }

    public function getTaskTimeLeft($level_id = '')
    {
        if (is_numeric($level_id)) {
            $type = LevelModel::where('level_id', $level_id)->value('type');
            if ($type == LevelRepository::TYPE_EXPERIENCE) {
                //体验取全天开放
                return Carbon::now()->diffInSeconds(Carbon::parse("23:59:59"));
            }
        }
        // 获取当前时间
        $now = date('H:i:s');
        $task_session = TaskSessionModel::where('start_time', '<', $now)
            ->where('end_time', '>', $now)
            ->where('status', TaskSessionModel::STATUS_ENABLE)->find();
        if (!$task_session) {
            return 0;
        }
        return Carbon::now()->diffInSeconds(Carbon::parse($task_session->end_time));
    }

    /**
     * 获取今日收益和今日任务统计
     * @param $user_id
     * @return array
     */
    public function getTodayTaskCount($user_id): array
    {
        // 查询今日任务
        $where = [
            ['user_id', '=', $user_id],
            ['day', '=', date("Y-m-d")],
            ['status', '=', TaskModel::STATUS_END],
        ];
        $tasks = $this->selectWhere($where);
        $income = 0;
        $task_count = [];
        foreach ($tasks as $task) {
            $income += $task['money'];
            if (isset($task_count[$task['level_id']])) {
                $task_count[$task['level_id']] = [
                    'level' => $task['level_id'],
                    'count' => $task_count[$task['level_id']]['count'] + 1
                ];
            } else {
                $task_count[$task['level_id']] = [
                    'level' => $task['level_id'],
                    'count' => 1
                ];
            }
        }
        return [
            'income' => $income,
            'task_count' => $task_count
        ];
    }

    /**
     * 活动任务次数统计
     * @param $user_id
     * @return array
     */
    public function getActivityTaskCount($user_id): array
    {
        // 查询活动任务
        $activities = (new LevelRepository)->findWhereIn('type', [LevelRepository::TYPE_ACTIVITY]);
        // 获取activity 的 level_id
        $activity_level_ids = array_column($activities, 'level_id');
        // 获取活动任务次数
        $activity_tasks = $this->selectWhere([
            ['user_id', '=', $user_id],
            ['level_id', 'in', $activity_level_ids]
        ]);
        $task_count = [];
        // 给$task_count数组添加活动任务次数
        foreach ($activity_tasks as $activity_task) {
            if (isset($task_count[$activity_task['level_id']])) {
                $task_count[$activity_task['level_id']]['count'] += 1;
            } else {
                $task_count[$activity_task['level_id']] = [
                    'level' => $activity_task['level_id'],
                    'count' => 1
                ];
            }
        }
        return $task_count;
    }

    /**
     * 获取今日收益
     * @param int $user_id
     * @return float
     */
    public function getTotalIncome(int $user_id): float
    {
        $where = [
            ['user_id', '=', $user_id],
            ['status', '=', TaskModel::STATUS_END],
        ];
        return $this->model->where($where)->sum('money');
    }
}
