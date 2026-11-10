<?php

namespace upload;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use think\Config;
use think\facade\Log;

/**
 * AWS S3操作类
 * Class S3
 * @package app\libs
 */
class S3
{
    /**
     * 获取预签名上传URL
     * @param string $folder
     * @param string $filename
     * @param bool $short_name 是否短路径
     * @return array
     */
    public static function getPresignedUrl(string $folder = '', string $filename = '', bool $short_name = false, int $position = 1): array
    {
        try {
            $config = config('app.aws_s3');
            if ($position == 2) {
                $config = config('app.new_aws_s3');
            }

            $s3Client = new S3Client([
                'version' => 'latest',
                'region' => $config['region'],
                'credentials' => [
                    'key' => $config['credentials_key'],
                    'secret' => $config['credentials_secret'],
                ]
            ]);
            $key = $folder . '/' . date("Y-m-d") . "/" . sha1(date('YmdHis', time()) . uniqid()) . '.' . pathinfo(
                    $filename,
                    PATHINFO_EXTENSION
                );
            if ($short_name) {
                $key = $folder . "/" . $filename;
            }

            $cmd = $s3Client->getCommand('PutObject', [
                'Bucket' => $config['bucket'],
                'Key' => $key,
            ]);

            $presignedUrl = $s3Client->createPresignedRequest($cmd, '+1 hour')->getUri();
            return [
                'code' => 1,
                'data' => [
                    'url' => (string)$presignedUrl,
                    'key' => $key,
                    'base_url' => $config['url']
                ]
            ];
        } catch (AwsException $e) {
            return ['code' => 3008, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 上传文件
     * @param string $folder
     * @param string $uid
     * @return array
     */
    public static function uploadFile($folder = '', $uid = '')
    {
        $files = request()->file('files');
        if (empty($files)) {
            $files = request()->file();
        }
        if (!is_array($files)) {
            $files = [$files];
        }

        $fileArr = [];
        if (!empty($files) && count($files) > 0) {
            try {
                $config = config('app.aws_s3');
                $s3Client = new S3Client([
                    'version' => 'latest',
                    'region' => $config['region'],
                    'credentials' => [
                        'key' => $config['credentials_key'],
                        'secret' => $config['credentials_secret'],
                    ]
                ]);
                foreach ($files as $file) {
                    $fileName = $folder . '/' . date("Y-m-d") . "/" . sha1(date(
                                'YmdHis',
                                time()
                            ) . uniqid()) . '.' . $file->extension();

                    $result = $s3Client->putObject([
                        'Bucket' => $config['bucket'],
                        'Key' => $fileName,
                        'Body' => fopen($file->getRealPath(), 'rb'),
                        '@http' => [
                            'proxy' => $config['proxy']
                        ]
                    ]);

                    if ($result['@metadata']['statusCode'] === 200) {
                        if ($result['ObjectURL']) {
                            $url = $config['url'] . "/" . $fileName;
                        } else {
                            $url = "";
                        }
                        $fileArr[] = [
                            'name' => $fileName,
                            'url' => $url,
                            'uid' => $uid,
                            'type' => $file->extension()
                        ];
                    }
                }
            } catch (AwsException $e) {
                return ['code' => 3003, 'msg' => $e->getMessage()];
            }
        } else {
            return ['code' => 3004];
        }

        return ['code' => 1, 'data' => $fileArr];
    }

    /**
     * 删除文件
     * @param string $url
     * @return array
     */
    public static function delFile($url)
    {
        if (empty($url)) {
            return ['code' => 3005];
        }

        try {
            $config = config('app.aws_s3');
            if (strpos($url, $config['url']) !== false) {
                $urlArr = explode($config['url'] . '/', $url);
                if (isset($urlArr[1])) {
                    $s3Client = new S3Client([
                        'version' => 'latest',
                        'region' => $config['region'],
                        'credentials' => [
                            'key' => $config['credentials_key'],
                            'secret' => $config['credentials_secret'],
                        ]
                    ]);

                    $s3Client->deleteObject([
                        'Bucket' => $config['bucket'],
                        'Key' => $urlArr[1]
                    ]);

                    return ['code' => 1];
                }
            }
            return ['code' => 3006];
        } catch (AwsException $e) {
            return ['code' => 992, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 移动/复制文件
     * @param string $source
     * @param string $destination
     * @return array
     */
    public static function moveFile($source, $destination)
    {
        if (empty($source) || empty($destination)) {
            return ['code' => 0, 'msg' => '源文件或目标路径不能为空'];
        }

        try {
            $config = config('app.aws_s3');
            $s3Client = new S3Client([
                'version' => 'latest',
                'region' => $config['region'],
                'credentials' => [
                    'key' => $config['credentials_key'],
                    'secret' => $config['credentials_secret'],
                ],
            ]);

            $s3Client->copyObject([
                'Bucket' => $config['bucket'],
                'Key' => $destination,
                'CopySource' => "{$config['bucket']}/{$source}",
            ]);

            return [
                'code' => 1,
                'url' => $config['url'] . '/' . $destination,
                'msg' => '移动成功'
            ];
        } catch (AwsException $e) {
            return ['code' => 0, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 通过文件名获取文件URL
     * @param string $filename
     * @param bool $pre_signed 是否返回预签名URL，默认false
     * @return array
     */
    public static function getFileUrl(string $filename, bool $pre_signed = false): array
    {
        if (empty($filename)) {
            return ['code' => 0, 'msg' => '文件名不能为空'];
        }

        try {
            $config = config('app.aws_s3');

            if (!$pre_signed) {
                // 返回直接的URL（需要bucket配置为公开访问）
                return [
                    'code' => 1,
                    'data' => [
                        'url' => $config['url'] . '/' . $filename
                    ]
                ];
            }

            // 如果需要预签名URL
            $s3Client = new S3Client([
                'version' => 'latest',
                'region' => $config['region'],
                'credentials' => [
                    'key' => $config['credentials_key'],
                    'secret' => $config['credentials_secret'],
                ]
            ]);

            $cmd = $s3Client->getCommand('GetObject', [
                'Bucket' => $config['bucket'],
                'Key' => $filename
            ]);

            $request = $s3Client->createPresignedRequest($cmd, '+1 hour');
            $presignedUrl = (string)$request->getUri();

            return [
                'code' => 1,
                'data' => [
                    'url' => $presignedUrl
                ]
            ];
        } catch (AwsException $e) {
            return ['code' => 0, 'msg' => $e->getMessage()];
        }
    }
}
