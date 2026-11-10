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

class MemberValidate extends Validate
{
    protected $rule = [
//        'mobile'      => 'unique:home_user',
        'invite_code' => 'unique:home_user'
    ];

    protected $message = [
//        'mobile.unique'      => '用户已经存在',
        'invite_code.unique' => '邀请码已存在'
    ];

}
