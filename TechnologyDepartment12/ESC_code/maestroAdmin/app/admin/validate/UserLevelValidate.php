<?php

namespace app\admin\validate;

use think\Validate;

class UserLevelValidate extends Validate
{
    protected $rule = [
        "user_id"        => "require",
        "from_level"     => "require",
        "to_level"       => "require",
        "money"          => "require",
//        "level_time"     => "require",
        "level_type"     => "require",
        "level_end_time" => "require",
    ];
    protected $message = [
        "user_id.require"        => "参数错误",
        "from_level.require"     => "原等级不能为空",
        "to_level.require"       => "升级后等级不能为空",
        "money.require"          => "花费金额不能为空",
//        "level_time.require"     => "有效天数不能为空",
        "level_type.require"     => "有效类型不能为空",
        "level_end_time.require" => "到期时间不能为空",
    ];

}
