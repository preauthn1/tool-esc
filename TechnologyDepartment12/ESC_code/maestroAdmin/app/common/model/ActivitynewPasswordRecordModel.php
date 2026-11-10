<?php

namespace app\common\model;

use app\admin\model\MemberModel;
use think\Model;
use think\model\relation\BelongsTo;

class ActivitynewPasswordRecordModel extends Model {

    protected $name = 'activitys_password_record';
    protected $autoWriteTimestamp = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'user_id', 'id');
    }
}
