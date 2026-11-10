<?php

namespace app\common\validate;

use think\Validate;

class ActivitynewValidate extends Validate {

    protected $rule = [
        "title" => "require",
        "start_time" => "require",
        "end_time" => "require",
        "type" => "require",
        "reward_type" => "require|number",
        "reward" => "require",
        "times" => "require|number",
        "day_times" => "require|number",
    ];
    protected $message = [
        "title.require" => "活动名称必填",
        "start_time.require" => "活动开始时间必填",
        "end_time.require" => "活动结束时间必填",
        "type.require" => "活动类型必选",
        "reward_type.require" => "奖品类型必选",
        "reward.require" => "奖品必填",
        "times.require" => "用户可参与次数必填",
        "day_times.require" => "用户每天可参与次数必填",
    ];

}
