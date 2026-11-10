<?php

namespace app\admin\controller;

use app\common\model\UploadModel;
use think\Request;
use think\response\Json;
use upload\S3;

class Upload extends Base
{
    /**
     * 上传
     * @author jry <598821125@qq.com>
     */
    public function upload(Request $request)
    {
        if (request()->isAjax()) {
            $UploadModel = new UploadModel();
            $type = $request->param('type', 'image');
            $ret = $UploadModel->Upload('file', $type);
            if ($ret) {
                $ret['data']['src'] = toMedia($ret['data']['path']);
                $ret['data']['url'] = toMedia($ret['data']['path']);
                return json(msg($ret['code'], $ret['data'], $ret['msg']));
            } else {
                return json(msg(-1, '', '上传失败'));
            }
        }
    }

    // 编辑头像
    public function upavatar()
    {
        if (request()->isAjax()) {
            $UploadModel = new UploadModel();
            $ret = $UploadModel->Upload('file', 'head');
            if ($ret) {
                $ret['data']['src'] = toMedia($ret['data']['path']);
                $ret['data']['url'] = toMedia($ret['data']['path']);
                return json(msg($ret['code'], $ret['data'], $ret['msg']));
            } else {
                return json(msg(-1, '', '上传失败'));
            }
        }
    }

    // 上传文件
    public function uploadFile()
    {
        if (request()->isAjax()) {
            $UploadModel = new UploadModel();
            $ret = $UploadModel->UploadFile('file');
            if ($ret) {
                $ret['data']['src'] = toMedia($ret['data']['path']);
                $ret['data']['url'] = toMedia($ret['data']['path']);
                return json(msg($ret['code'], $ret['data'], $ret['msg']));
            } else {
                return json(msg(-1, '', '上传失败'));
            }
        }
    }

    /**
     * 获取预签名上传URL
     * @param  Request  $request
     * @return Json
     */
    public function getPresignedUrl(Request $request): Json
    {
        if (request()->isAjax()) {
            $folder = $request->param('folder', 'video');
            $filename = $request->param('filename', '');
            $short_name = $request->param('short_name', '');
            $position = $request->param('position', '1');//上传位置 1原来的，2是新的、
            $result = S3::getPresignedUrl($folder, $filename,$short_name, $position);

            if ($result['code'] == 1) {
                return json(msg(1, $result['data'], '获取上传链接成功'));
            } else {
                return json(msg(-1, '', $result['msg'] ?? '获取上传链接失败'));
            }
        }
        return json(msg(-1, '', '请求方式错误'));
    }
}
