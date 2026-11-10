<?php

namespace app\common\model;

use app\admin\model\MemberModel;
use think\Model;
use think\model\relation\BelongsTo;

class PddTurntableRecordModel extends Model
{
    protected $name = 'pdd_turntable_record';
    protected $autoWriteTimestamp = true;

    protected $type = [
        'is_big_prize' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'user_id', 'id');
    }

}
