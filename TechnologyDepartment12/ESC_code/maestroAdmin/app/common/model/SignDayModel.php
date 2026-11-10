<?php

namespace app\common\model;

use think\Model;

class SignDayModel extends Model
{

    protected $autoWriteTimestamp = true;
    // 确定链接表名
    protected $name = 'sign_day';

    public static $award_type_list = [
        '1' => '基础账户',
        '2' => '佣金账户'
    ];

}
