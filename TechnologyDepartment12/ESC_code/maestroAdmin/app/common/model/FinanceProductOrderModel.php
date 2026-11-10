<?php

namespace app\common\model;

use app\admin\model\LevelModel;
use app\admin\model\MemberModel;
use think\Model;
use think\model\relation\BelongsTo;

class FinanceProductOrderModel extends Model
{

    // 确定链接表名
    protected $name = 'finance_product_order';
    protected $autoWriteTimestamp = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'user_id', 'id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(LevelModel::class, 'level_id', 'level_id');
    }
}
