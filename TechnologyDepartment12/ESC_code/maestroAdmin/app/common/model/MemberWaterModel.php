<?php

namespace app\common\model;

use app\admin\model\MemberModel;
use think\Model;
use think\model\relation\BelongsTo;

class MemberWaterModel extends Model {

    protected $autoWriteTimestamp = true;
    // 确定链接表名
    protected $name = 'home_user_water';

    public function user(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'user_id', 'id');
    }

}
