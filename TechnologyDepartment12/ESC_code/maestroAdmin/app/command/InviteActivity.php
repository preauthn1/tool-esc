<?php

namespace app\command;

use app\common\model\ActivitynewModel;
use think\facade\Cache;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class InviteActivity extends Command
{
    protected function configure()
    {
        // 正常执行指令 php think inviteactivity
        $this->setName('inviteactivity')->setDescription('统计用户任务汇总结算');
        $this->addOption('is_clear', 'r', Option::VALUE_REQUIRED, '是否清除缓存');
    }

    protected function execute(Input $input, Output $output)
    {
        $apiRedis = Cache::store("redis");
        $redisKey = 'invite_activity_command';

        //是否清除缓存
        if ($input->hasOption("is_clear")) {
            $isClear = $input->getOption("is_clear");
        } else {
            $isClear = 0;
        }
        if ($isClear == 1) {
            $apiRedis->set($redisKey, 0, 1);
            $output->writeln('缓存已清除，等待下次执行');
            return false;
        }

        if ($apiRedis->get($redisKey) > 0) {
            $output->writeln('邀请活动统计算中');
            return false;
        }
        $startTime = time();
        $apiRedis->set($redisKey, $startTime, 50);
        do {
            (new ActivitynewModel())->summaryInviteActivity($output);
            $output->info('邀请活动统计');
            //休眠等待下次执行
            $sleepTime = 10;
            $endTime = time();
            $output->info("休眠{$sleepTime}秒,执行开始时间" . date('Y-m-d H:i:s', $startTime) . ",执行结束时间" . date('Y-m-d H:i:s', $endTime) . "本次执行时间(s)：" . (time() - $startTime));
            sleep($sleepTime);
            $redisValue = $apiRedis->get($redisKey);
        } while ($redisValue);
        $output->info('邀请活动统执行完毕');
    }
}
