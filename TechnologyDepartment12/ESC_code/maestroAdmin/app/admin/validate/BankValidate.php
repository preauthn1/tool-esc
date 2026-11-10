<?php

namespace app\admin\validate;

use think\Validate;

class BankValidate extends Validate {

    protected $rule = [
        "lang" => "require",
        "name" => "require|unique:banks",
        'bank_code' =>  'require|unique:banks',
    ];
    protected $message = [
        "lang.require" => "国家不能为空",
        "name.require" => "银行名称不能为空",
        "name.unique" => "银行名称已存在",
        "bank_code.require" => "银行编码不能为空",
        "bank_code.unique" => "银行编码已存在",
    ];

}
