<?php
// +----------------------------------------------------------------------
// | snake
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://baiyf.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: NickBai <1902822973@qq.com>
// +----------------------------------------------------------------------
namespace app\admin\validate;

use think\Validate;

class LevelValidate extends Validate
{
    protected $rule = [
        "qd_rate" => "require|number|elt:5000",
//        "income_rate" => "require|number|elt:1000",
        "money" => "requireIf:is_buy,1|number|gt:0",
    ];
    protected $message = [
        "qd_rate.require" => "抢单返佣比例不能为空",
        "qd_rate.number" => "抢单返佣比例只能是数字",
        "qd_rate.elt" => "抢单返佣比例不能大于50%",
        "income_rate.require" => "每天总收益率不能为空",
        "income_rate.number" => "每天总收益率只能是数字",
        "income_rate.elt" => "每天总收益率不能大于10%",
        "money.requireIf" => "购买金额必填",
        "money.number" => "购买金额只能是数字",
        "money.gt" => "购买金额必须大于0",
    ];

}
