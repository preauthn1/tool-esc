<?php

namespace app\common\model;

use app\admin\model\MemberModel;
use think\Model;
use think\model\relation\HasOne;

class MemberIpModel extends Model
{

    protected $autoWriteTimestamp = true;

    // 确定链接表名
    protected $name = 'home_user_ip';

    public function user(): HasOne
    {
        return $this->hasOne(MemberModel::class, 'id', 'user_id')->field('id,user_name');
    }
}
