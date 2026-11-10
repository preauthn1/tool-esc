<?php

namespace app\command;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class CreateAwsInstances extends Command
{

    protected function configure()
    {
        $this->setName('create_aws_instances')->setDescription('测试');
        $this->addOption('num', '', Option::VALUE_OPTIONAL, '实例数量');
    }


    protected function execute(Input $input, Output $output)
    {
        $num = 1;
        if ($input->hasOption("num")) {
            $num = $input->getOption("num");
        }
        $client = new Client();
        $url = 'http://38.181.219.8:5000/api/create-instances';
        for ($i = 1; $i <= $num; $i++) {
            $headers = [
                'Content-Type' => 'application/json'
            ];
            $body = '{
               "instanceId": "string"
            }';
            $request = new Request('POST', $url, $headers, $body);
            $res = $client->sendAsync($request)->wait();
            echo $res->getBody() . "\n";

        }
    }
}
