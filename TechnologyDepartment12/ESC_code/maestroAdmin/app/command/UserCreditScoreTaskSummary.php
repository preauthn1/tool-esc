<?php

namespace app\command;

use app\common\repository\CreditScoreTaskSummaryRepository;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class UserCreditScoreTaskSummary extends Command
{
    protected function configure()
    {
        // 正常执行指令 php think usercreditscoretasksummary
        $this->setName('usercreditscoretasksummary')->setDescription('信用分-统计用户任务汇总');
    }

    protected function execute(Input $input, Output $output)
    {
        //推广任务汇总
        (new CreditScoreTaskSummaryRepository())->summary();
        $output->info('信用分-统计用户任务汇总成功');
    }
}
