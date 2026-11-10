<?php

namespace app\api\validate;

class HomeUserBalanceValidate extends BaseValidate
{
    protected $rule = [
        'money' => 'require|number|gt:0|currency'
    ];

    protected $message = [
        'money.require'  => '请输入金额',
        'money.currency' => '金额格式错误',
        'money.gt'       => '请输入正确的金额',
    ];


    protected $scene = [

    ];
}
