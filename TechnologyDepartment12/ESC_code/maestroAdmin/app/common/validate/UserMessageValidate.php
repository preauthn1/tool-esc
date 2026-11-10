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

class UserMessageValidate extends Validate
{
    protected $rule = [
        'batch_edit_type' => 'require',
        'is_pop'          => 'require',
        'title'           => 'require',
        'content'         => 'require',
    ];

    protected $message = [
        "batch_edit_type.require" => "请选择发送方式",
        "is_pop.require"          => "请选择是否弹窗",
        "title.require"           => "请输入标题",
        "content.require"         => "请输入内容",
    ];

    protected $scene = [
        'add'  =>  ['title', 'content'],
    ];
}
