<?php

namespace app\common\model;

use think\Model;

class WithdrawLimitModel extends Model {

    protected $autoWriteTimestamp = 'datetime';
    // 确定链接表名
    protected $name = 'withdraw_limit';

}
