<?php

namespace app\common\model;

use app\admin\model\LevelModel;
use think\Model;
use think\model\relation\BelongsTo;

class AdPlanDaysConfig extends Model
{
  protected $table = 'qd_ad_plan_days_config';
  protected $autoWriteTimestamp = true;

    public function level(): BelongsTo
    {
        return $this->belongsTo(LevelModel::class, 'level_id', 'level_id');
  }
}
