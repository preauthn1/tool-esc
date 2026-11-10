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

class CommentValidate extends Validate
{
    protected $rule = [
        ['user_id', 'require', '被评论用户ID不能为空'],
        ['from_id', 'require', '评论用户ID不能为空'],
        ['from_order_id', 'require', '来自订单ID不能为空'],
        ['score', 'require', '分数不能为空'],
        // ['content', 'require', '评论内容不能为空']
    ];

}
