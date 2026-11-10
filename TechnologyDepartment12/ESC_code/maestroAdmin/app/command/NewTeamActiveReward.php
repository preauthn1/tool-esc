<?php

namespace app\command;

use app\common\model\TeamActiveRewardModel;
use app\common\model\TeamActiveRewardV2Model;
use think\facade\Cache;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class NewTeamActiveReward extends Command
{
    protected function configure()
    {
        // 正常执行指令 php think newteamactivereward
        $this->setName('newteamactivereward')->setDescription('统计团队活跃奖励-2025年10月13日版本');
        $this->addOption('is_clear', 'r', Option::VALUE_REQUIRED, '是否清除缓存');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始统计团队活跃奖励');
        $apiRedis = Cache::store("redis");
        $redisKey = 'new_team_active_reward_command';

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
            $output->writeln('团队活跃奖励计算中');
            return false;
        }
        $startTime = time();
        $apiRedis->set($redisKey, $startTime, 50);
        do {
            (new TeamActiveRewardV2Model())->summaryActive($output);
            $output->info('团队活跃奖励统计');
            //休眠等待下次执行
            $sleepTime = 10;
            $endTime = time();
            $output->info("休眠{$sleepTime}秒,执行开始时间" . date('Y-m-d H:i:s', $startTime) . ",执行结束时间" . date('Y-m-d H:i:s', $endTime) . "本次执行时间(s)：" . (time() - $startTime));
            sleep($sleepTime);
            $redisValue = $apiRedis->get($redisKey);
        } while ($redisValue);
        $output->info('团队活跃奖励执行完毕');
    }
}
