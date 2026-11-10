<?php

namespace app\admin\validate;

use think\Validate;

class PaymentPlatformValidate extends Validate
{

    protected $rule = [
        "name"               => "require",
        "min_withdraw_money" => "require",
        "max_withdraw_money" => "require",
        "min_recharge_money" => "require",
    ];

    protected $message = [
        "name.require"               => "请输入通道名称",
        "min_withdraw_money.require" => "请输入最小提现金额",
        "max_withdraw_money.require" => "请输入最大提现金额",
        "min_recharge_money.require" => "请输入小充值金额",
    ];

}
