<?php

namespace app\admin\model;

use think\Model;
use think\model\relation\BelongsTo;

class OperationModel extends Model {

    protected $name = 'operation_log';
    protected $autoWriteTimestamp = 'datetime';
    protected $insert = ['ip'];

    protected function setIpAttr() {
        return getip();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\app\common\model\UserModel::class,'user_id', 'id');
    }
}
