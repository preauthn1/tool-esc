<?php

namespace app\api\controller;

use app\common\model\UploadModel;
use think\Request;
use think\response\Json;
use upload\S3;

class Upload extends Base
{
    /**
     * 上传
     */
    public function upload(Request $request)
    {
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


    /**
     * 获取预签名上传URL
     * @param  Request  $request
     * @return Json
     */
    public function getPresignedUrl(Request $request): Json
    {
        $folder = $request->param('folder', 'file');
        $filename = $request->param('filename', '');
        $result = S3::getPresignedUrl($folder, $filename);

        if ($result['code'] == 1) {
            return json(msg(1, $result['data'], '获取上传链接成功'));
        } else {
            return json(msg(-1, '', $result['msg'] ?? '获取上传链接失败'));
        }
    }
}
