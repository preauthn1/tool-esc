<?php

namespace app\common\model;

use app\admin\model\MemberModel;
use think\Model;
use think\model\relation\BelongsTo;

class HomeUserExcessAdRatioConfigModel extends Model
{
    protected $name = 'home_user_excess_ad_ratio_config'; // 表名
    protected $autoWriteTimestamp = true; // 开启自动时间戳

    protected $field = [
        'id',
        'user_id',
        'level_id',
        'ratio',
        'create_time',
        'update_time',
        'subordinate',
        'profit_rate',
        'ad_cycle',
    ];
    // 定义与用户表的关联
    public function user(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'user_id', 'id');
    }
}
