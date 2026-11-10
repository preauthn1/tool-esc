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

namespace app\common\model;

use think\exception\ValidateException;
use think\facade\Filesystem;
use think\facade\Log;
use think\Model;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use think\facade\Env;

//use think\Image;

class UploadModel extends Model
{

    // 确定链接表名
    protected $name = 'upload';

    public function Upload($postname = 'image', $type = 'image')
    {
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file($postname);
        try {
            validate(['fileExt' => 'jpg,png,gif,jpeg,mp4,apk,mobileconfig,mp3'])
                ->check([$file]);
            $save_path = Filesystem::putFile($type, $file);
            if ($file->getOriginalExtension() == "apk") {
                $filename = $file->getFilename();
                $save_path = Filesystem::putFileAs($type, $file, $filename);
            }
        } catch (ValidateException $e) {
            // 上传失败获取错误信息
            return msg(-1, '', $e->getMessage());
        }
        // 移动到框架应用根目录/public/uploads/ 目录下
        if ($file) {
            // 友情提醒
            $upload_max_filesize = substr(ini_get('upload_max_filesize'), 0, -1);
            $post_max_size = substr(ini_get('post_max_size'), 0, -1);
            if ($post_max_size < $upload_max_filesize) {
                return msg(-1, '', '警告：php.ini里post_max_size值应该设置比upload_max_filesize大');
            }

            $filename = $file->getFilename();
            $path = $save_path;
            $ext = $file->getExtension();
            $size = $file->getSize();
            $md5 = $file->md5();
            $sha1 = $file->sha1();
            $create_time = time();

            $data = [
                'name' => $filename,
                'path' => $path,
                'ext' => substr($ext, 0, 4),
                'size' => $size,
                'md5' => $md5,
                'sha1' => $sha1,
                'create_time' => $create_time,
                'update_time' => $create_time,
                'status' => 1
            ];

            $s3_url = $this->uploadS3($path);
            if ($s3_url !== false) {
                $data['path'] = $s3_url;
                @unlink(toMedia($path, 2));
            } else {
                return msg(-1, '', '上传失败');
            }
            $ret = $this->insertGetId($data);
            if ($ret) {

                $data['id'] = $ret;
                return msg(200, $data, '上传成功');
            } else {
                return msg(-1, '', '上传失败');
            }
        }
    }

    public function UploadResize($postname = 'image', $type = 'image')
    {
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file($postname);
        // 移动到框架应用根目录/public/uploads/ 目录下
        $savePath = config('upload.rootPath') . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR;
        if ($file) {
            // 友情提醒
            $upload_max_filesize = substr(ini_get('upload_max_filesize'), 0, -1);
            $post_max_size = substr(ini_get('post_max_size'), 0, -1);
            if ($post_max_size < $upload_max_filesize) {
                return msg(-1, '', '警告：php.ini里post_max_size值应该设置比upload_max_filesize大');
            }

            $info = $file->rule(config('upload.saveName'))->validate([
                'size' => config('upload.maxSize'), 'ext' => 'jpg,png,gif,jpeg,mp4,apk,mobileconfig'
            ])->move($savePath);
            if ($info && $info->getPathname()) {
                $image = Image::open($info->getRealPath());

                // 绝对路径加上文件名
                $save = $savePath . DIRECTORY_SEPARATOR . (date('Ymd')) . DIRECTORY_SEPARATOR . $info->getFilename();
                list($width, $height) = getimagesize($info->getPathname());
                $width = $width * 0.5;
                $height = $height * 0.5;
                $image->thumb($width, $height, Image::THUMB_CENTER)->save($save);

                $filename = $info->getFilename();
                $path = $type . DIRECTORY_SEPARATOR . $info->getSaveName();
                $ext = $info->getExtension();
                $size = $info->getSize();
                $md5 = $info->md5();
                $sha1 = $info->sha1();
                $create_time = time();

                $data = [
                    'name' => $filename,
                    'path' => $path,
                    'ext' => substr($ext, 0, 4),
                    'size' => $size,
                    'md5' => $md5,
                    'sha1' => $sha1,
                    'create_time' => $create_time,
                    'update_time' => $create_time,
                    'status' => 1
                ];

                $s3_url = $this->uploadS3($path);
                if ($s3_url !== false) {
                    $data['path'] = $s3_url;
                    @unlink(toMedia($path, 2));
                }
                $ret = $this->insertGetId($data);
                if ($ret) {

                    $data['id'] = $ret;
                    return msg(200, $data, '上传成功');
                } else {
                    return msg(-1, '', '上传失败');
                }
            } else {
                // 上传失败获取错误信息
                return msg(-1, '', $file->getError());
            }
        }
    }

    public function UploadFile($postname = 'file')
    {

        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file($postname);
        // 移动到框架应用根目录/public/uploads/ 目录下
        try {
            validate(['fileSize' => config('upload.maxSize'), 'fileExt' => 'txt,doc,wps,apk'])
                ->check([$file]);
            $save_path = Filesystem::putFile('files', $file);

        } catch (ValidateException $e) {
            // 上传失败获取错误信息
            return msg(-1, '', $e->getMessage());
        }
        if ($file) {
            // 友情提醒
            $upload_max_filesize = substr(ini_get('upload_max_filesize'), 0, -1);
            $post_max_size = substr(ini_get('post_max_size'), 0, -1);
            if ($post_max_size < $upload_max_filesize) {
                return msg(-1, '', '警告：php.ini里post_max_size值应该设置比upload_max_filesize大');
            }

            $filename = $file->getFilename();
            $path = $save_path;
            $ext = $file->getExtension();
            $size = $file->getSize();
            $md5 = $file->md5();
            $sha1 = $file->sha1();
            $create_time = time();

            $data = [
                'name' => $filename,
                'path' => $path,
                'ext' => $ext,
                'size' => $size,
                'md5' => $md5,
                'sha1' => $sha1,
                'create_time' => $create_time,
                'update_time' => $create_time,
                'status' => 1
            ];
            $s3_url = $this->uploadS3($path);
            if ($s3_url !== false) {
                $data['path'] = $s3_url;
                @unlink(toMedia($path, 2));
            }
            $ret = $this->insertGetId($data);
            if ($ret) {
                $data['id'] = $ret;
                return msg(200, $data, '上传成功');
            } else {
                return msg(-1, '', '上传失败');
            }
        }
    }


    public function uploadS3($file)
    {
        $s3_url = Env::get('S3.URL');
        $s3_config = [
            'version' => 'latest',
            'region' => Env::get('S3.REGION'),
            'credentials' => [
                'key' => Env::get('S3.CREDENTIALS_KEY'),
                'secret' => Env::get('S3.CREDENTIALS_SECRET'),
            ]
        ];
        $s3_bucket = Env::get('S3.BUCKET');
        if (strexists($file, root_path())) {
            $file = str_replace(root_path(), "", $file);
        }
        $file_path = toMedia($file, 2);
        $object_key = str_replace(root_path() . 'public/storage/', "", $file_path);
        if ($s3_bucket && isset($s3_config['credentials']['key']) && $s3_config['credentials']['key'] != '') {
            $s3 = S3Client::factory($s3_config);
            try {
                $result = $s3->putObject([
                    'Bucket' => $s3_bucket,
                    'Key' => $object_key,
                    'Body' => fopen($file_path, 'r'),
//                    'ACL'    => 'public-read',
                    '@http' => [
                        'proxy' => Env::get('S3.PROXY')
                    ]
                ]);
                if ($result['ObjectURL']) {
                    return $s3_url . "/" . $object_key;
                } else {
                    Log::write($result, 'error');
                    return false;
                }
            } catch (S3Exception $e) {
                Log::write($e->getAwsErrorMessage(), 'error');
            }
        }

        return false;
    }
}
