<?php

namespace app\command;

use app\common\model\CreditScoreLogModel;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Cache;

class CreditScoreCount extends Command
{
    protected function configure()
    {
        // 正常执行指令 php think creditscorecount
        $this->setName('creditscorecount')->setDescription('用户信用分加减');
    }

    protected function execute(Input $input, Output $output)
    {
        $apiRedis = Cache::store("redis");
        $CreditScoreCountRedisKey = 'credit_score_count_command';

        if ($apiRedis->get($CreditScoreCountRedisKey, 0) == 0) {
            $apiRedis->set($CreditScoreCountRedisKey, 1, 60);
            $output->info('下级活跃给上级增加信用分');
            (new CreditScoreLogModel())->activeAddCreditScoreCount();
            $output->info('新用户给上级增加信用分');
            (new CreditScoreLogModel())->newAddCreditScoreCount();
            $output->info('交易金额给用户增加信用分');
            (new CreditScoreLogModel())->tradeAddCreditScoreCount();
            $output->info('逾期给用户增扣信用分');
            (new CreditScoreLogModel())->overdueCreditScoreCount();
            $apiRedis->set($CreditScoreCountRedisKey, 0, 1);
        } else {
            $output->writeln('用户信用分加减 休眠中');
        }
    }
}
