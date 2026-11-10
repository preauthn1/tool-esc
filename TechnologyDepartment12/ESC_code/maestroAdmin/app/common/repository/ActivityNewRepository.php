<?php

namespace app\common\repository;

use app\common\model\ActivitynewModel;
use app\lib\exception\ActivityException;
use think\facade\Cache;

class ActivityNewRepository extends Repository
{
    protected $model;

    public function __construct()
    {
        //绑定模型
        $this->model = new ActivitynewModel();
    }


    // 检查活动状态
    public function checkActivityStatusById($id): bool
    {
        $activity = $this->model->find($id);
        if (!$activity || $activity->status != 1) {
            throw new ActivityException(['msg' => lang('活动不存在或已停止')]);
        }
        if ($activity->end_time < time()) {
            throw new ActivityException(['msg' => lang('活动已结束')]);
        }
        return true;
    }

    /**
     * @throws \Throwable
     */
    public function getActivityById($id)
    {
        return Cache::store('redis')->remember(config("redis_cache_key.activity_info") . $id, function () use($id) {
            return (new ActivitynewModel)->find($id);
        }, 300);
    }
}
