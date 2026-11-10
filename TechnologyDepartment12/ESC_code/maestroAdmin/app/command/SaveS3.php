<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Env;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
class SaveS3 extends Command {

    protected function configure() {
        $this->setName('saves3')->setDescription('保存S3图片到本地');
    }

    protected function execute(Input $input, Output $output) {
        $list = Db::name('upload')->where('path', "LIKE", "%amazonaws.com%")->select();
        if (!empty($list)) {
            $curr = 1;
            $total = count($list);
            foreach ($list as $item) {
                echo "{$curr}/{$total};";
                $parse = parse_url($item['path']);
                if (isset($parse['host'])) {
                    if ($this->save($parse['path'])) {
                        $output->info($parse['path']);
                    }else{
                        $output->error($item['path']);
                    }
                }
                $curr++;
            }
        }
        $output->info("done！");
    }

    public function save($key) {
        $s3_config = [
            'version' => 'latest',
            'region' => Env::get('S3.REGION'),
            'credentials' => [
                'key' => Env::get('S3.CREDENTIALS_KEY'),
                'secret' => Env::get('S3.CREDENTIALS_SECRET'),
            ],
        ];
        $s3_bucket = Env::get('S3.BUCKET');
        $file_path = toMedia($key, '/', 2);
        if (file_exists($file_path)) {
            return true;
        }
        $pathinfo = pathinfo($file_path);
        if (!file_exists($pathinfo['dirname'])) {
            $a = mkdir($pathinfo['dirname']);
        }
        if ($s3_bucket && isset($s3_config['credentials']['key']) && $s3_config['credentials']['key'] != '') {
            $s3 = S3Client::factory($s3_config);
            try {
                $result = $s3->getObject([
                    'Bucket' => $s3_bucket,
                    'Key' => ltrim($key, '/'),
                ]);

                file_put_contents($file_path, $result['Body']);
            } catch (S3Exception $e) {
                echo $e->getMessage();
                return false;
            }
        }

        return true;
    }

}
