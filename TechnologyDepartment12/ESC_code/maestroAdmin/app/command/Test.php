<?php

namespace app\command;

use app\common\model\TeamActiveRewardV2Model;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class Test extends Command
{

    protected function configure()
    {
        $this->setName('test')->setDescription('测试');
    }

    public function execute(Input $input, Output $output)
    {
        $TeamActiveRewardV2Model = new TeamActiveRewardV2Model();
        $data = $TeamActiveRewardV2Model->getConfig(2);
        print_r($data);

    }

}
