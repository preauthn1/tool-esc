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
namespace app\common\validate;

use think\Validate;

class HomeValidate extends Validate
{
    protected $rule = [
        "account" => "require",
        "password" => "require",
//        ['mobile', 'require', '手机号不能为空'],
//        ['password', 'require', '手机号不能为空'],
//        ['code', 'require', '验证码不能为空']
    ];
    protected $message = [
        "account.require" => "账号不能为空",
        "password.require" => "密码不能为空",
    ];

}
