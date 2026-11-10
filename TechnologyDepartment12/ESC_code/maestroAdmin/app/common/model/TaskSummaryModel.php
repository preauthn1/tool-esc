<?php

namespace app\common\model;

use think\Model;
use think\model\relation\BelongsTo;

class TaskSummaryModel extends Model {

    protected $autoWriteTimestamp = true;
    // 确定链接表名
    protected $name = 'task_summary';

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id', 'id');
    }
}
