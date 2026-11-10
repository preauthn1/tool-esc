<?php

namespace app\common\repository;

use app\admin\model\LevelModel;
use app\common\model\TaskSessionModel;

use app\lib\exception\BaseException;
use app\lib\exception\FinanceProductPasswordException;
use think\facade\Cache;
use Throwable;

class TaskSessionRepository extends Repository
{
    protected $model;

    public function __construct()
    {

        //绑定模型
        $this->model = new TaskSessionModel();
    }

    public function getTaskSessionList($level_type = LevelRepository::TYPE_NORMAL)
    {
        $redis = Cache::store('redis');
        $redis_key = config("redis_cache_key.task_session_list");
        try {
            $task_session_list = $redis->remember($redis_key, function () {
                return $this->order(['start_time' => 'asc'])
                    ->selectWhere([
                        ['status', '=', TaskSessionModel::STATUS_ENABLE]
                    ]);
            }, 300);
            return $task_session_list->where('level_types', 'like', ',' . $level_type . ',');
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * 获取所有时段
     * @return mixed
     * @throws Throwable
     */
    public function getTaskSessionListAll()
    {
        $redis = Cache::store('redis');
        $redis_key = config("redis_cache_key.task_session_list_all", "task_session_list_all");
        return $redis->remember($redis_key, function () {
            $where[] = ['status', '=', TaskSessionModel::STATUS_ENABLE];
            return $this->order(['start_time' => 'asc'])->selectWhere($where);
        }, 300);
    }
    /**
     * 获取当前时间段可参与的广告位时段
     */
    /**
     * $level_id
     * @param string $level_id
     * @return mixed|string[]|null
     */
    public function getCurrentTaskSession($level_id = '')
    {
//        if (is_numeric($level_id)) {
//            $type = LevelModel::where('level_id', $level_id)->value('type');
//            if ($type == LevelRepository::TYPE_EXPERIENCE) {
//                //体验取全天开放
//                return (object)[
//                    'id' => 100,
//                    'start_time' => '00:00:00',
//                    'end_time' => '23:59:59'
//                ];
//            }
//        }
        $level_data = (new LevelRepository())->getLevelByLevelID($level_id);
        $now = date('H:i:s');
        $task_session_list = $this->getTaskSessionList($level_data['type']);
        if ($task_session_list) {
            foreach ($task_session_list as $task_session) {
                if ($task_session['start_time'] <= $now && $task_session['end_time'] >= $now) {
                    return $task_session;
                }
            }
        }

        return null;
    }

    /**
     * 获取当前场次时间区间与当前等级可以参与
     * @param string $level_id
     * @return array|array[]|null
     * @throws Throwable
     */
    public function getCurrentTaskSessionTime($level_id = '')
    {
        $level_data = (new LevelRepository())->getLevelByLevelID($level_id);
        $now = date('H:i:s');
        $task_session_list_all = $this->getTaskSessionListAll();
        //订单查询的时间区间
        $time_interval = [];
        $join_interval = [];
        if ($task_session_list_all) {
            foreach ($task_session_list_all as $task_session) {
                if ($task_session['start_time'] <= $now && $task_session['end_time'] >= $now) {
                    //如果types 中包含 LevelRepository::TYPE_EXPERIENCE
                    if (strpos($task_session['level_types'], ',' . LevelRepository::TYPE_EXPERIENCE . ',') !== false) {
                        $time_interval = $task_session;
                    }
                    //如果types 中包含 level_data['type']
                    if (strpos($task_session['level_types'], ',' . $level_data['type'] . ',') !== false) {
                        $join_interval = $task_session;
                    }
                }
            }
            //如果没有体验区但是配了其他区 使用其他区
            if (empty($time_interval) && (!empty($join_interval))) {
                $time_interval = $join_interval;
            }
            return [
                'time_interval' => $time_interval,
                'join_interval' => $join_interval
            ];
        }
        return [
            'time_interval' => [],
            'join_interval' => []
        ];
    }

    // 获取下一场的时间
    public function getNextTaskSession()
    {
        $now = date('H:i:s');
        $task_session_list = $this->getTaskSessionList();
        $data = null;
        foreach ($task_session_list as $task_session) {
            if ($task_session['start_time'] > $now && $task_session['end_time'] > $now) {
                // 当前时间在下一场开始时间之前
                $data = $task_session;
            }
        }
        if (!$data) {
            // 当前时间已在当日最后一场之后
            $data = $task_session_list[0];
        }
        return $data;
    }
}
