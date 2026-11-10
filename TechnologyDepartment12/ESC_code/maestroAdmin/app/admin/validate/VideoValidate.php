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

class VideoValidate extends Validate
{
    protected $rule = [
        'cate' => 'require',
        'pic' => 'require',
        'name' => 'require',
        'video_url' => 'require',
        'duration' => 'require',
//        'director'    => 'require',
        'description' => 'require',
    ];

    protected $message = [
        "cate.require" => "请选择分类",
        "pic.require" => "请上传视频封面",
        "name.require" => "请输入视频名称",
        "video_url.require" => "请上传视频",
        "duration.require" => "请输入视频时长",
//        "director.require"    => "请输入导演",
        "description.require" => "请输入视频详情",
    ];
}
