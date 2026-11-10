<?php

namespace app\common\model;

use think\Model;
use think\model\relation\HasMany;

class PddTurntableModel extends Model
{
    protected $autoWriteTimestamp = true;
    protected $name = 'pdd_turntable';

    protected $type = [
        'fixed_times' => 'integer'
    ];

    /**
     * Define relationship with PddTurntablePrizeModel
     */
    public function prizes(): HasMany
    {
        return $this->hasMany(PddTurntablePrizeModel::class, 'turntable_id', 'id')
            ->order('sort DESC');
    }
}
