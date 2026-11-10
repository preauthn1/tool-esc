<?php

namespace app\admin\model;

use think\Model;
use think\model\relation\BelongsTo;

class LevelTeamModel extends Model
{

    protected $autoWriteTimestamp = true;
    // 确定链接表名
    protected $name = 'level_team';

    public function level(): BelongsTo
    {
        return $this->belongsTo(LevelModel::class, 'level_id', 'level_id');
    }
}
