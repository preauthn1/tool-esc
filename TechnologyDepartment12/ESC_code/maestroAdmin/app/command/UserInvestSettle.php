<?php

namespace app\command;

use app\common\model\HomeUserBalanceModel;
use app\common\repository\FinanceProductOrderRepository;
use think\facade\Cache;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class UserInvestSettle extends Command
{
    protected function configure()
    {
        // 正常执行指令 php think userinvestsettle
        // 清除缓存指令 php think userinvestsettle -r 1
        $this->setName('userinvestsettle')->setDescription('用户投资与余额宝收益结算队列任务,请勿重复执行');
        $this->addOption('is_clear', 'r', Option::VALUE_REQUIRED, '是否清除缓存');
    }

    protected function execute(Input $input, Output $output)
    {
        $apiRedis = Cache::store("redis");
        $redisKey = 'user_invest_settle_command';

        //是否清除缓存
        if ($input->hasOption("is_clear")) {
            $isClear = $input->getOption("is_clear");
        } else {
            $isClear = 0;
        }
        if ($isClear == 1) {
            $apiRedis->set($redisKey, 0, 1);
            $apiRedis->clear($redisKey);
            $output->writeln('缓存已清除，等待下次执行');
            return false;
        }

        if ($apiRedis->get($redisKey) > 0) {
            $output->writeln('用户投资与余额宝收益执行中');
            return false;
        }
        $startTime = time();
        $apiRedis->set($redisKey, $startTime, 100);
        do {
            //死循环执行
            //余额宝收益结算
            $ret = HomeUserBalanceModel::settleBalanceTime();
            if ($ret) {
                $output->info('余额宝结算成功');
            }
            //休眠等待下次执行
            $sleepTime = 10;
            $endTime = time();
            $output->info("休眠{$sleepTime}秒,执行开始时间" . date('Y-m-d H:i:s', $startTime) . ",执行结束时间" . date('Y-m-d H:i:s', $endTime) . "本次执行时间(s)：" . (time() - $startTime));
            sleep($sleepTime);
            $redisValue = $apiRedis->get($redisKey);
        } while ($redisValue);
        $output->info('用户投资与余额宝收益结算队列任务执行完毕');
    }
}
