<?php

namespace app\common\validate;

use think\Validate;

class DomainValidate extends Validate {

    protected $rule = [
        "domain" => "require",
        "title" => "require",
        'logo' =>  'require',
    ];
    protected $message = [
        "domain.require" => "域名不能为空",
        "title.require" => "标题不能为空",
        "logo.require" => "logo不能为空",
    ];

}
