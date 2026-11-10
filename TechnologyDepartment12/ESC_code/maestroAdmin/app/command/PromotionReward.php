<?php

namespace app\command;

use app\common\model\PromotionModel;
use app\common\repository\PromotionRewardRepository;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Cache;

class PromotionReward extends Command
{
    protected function configure()
    {
        // 正常执行指令 php think promotionreward
        $this->setName('promotionreward')->setDescription('连续推广奖励');
        $this->addOption('is_clear', 'r', Option::VALUE_REQUIRED, '是否清除缓存');
    }

    protected function execute(Input $input, Output $output)
    {
        $apiRedis = Cache::store("redis");
        $redisKey = 'promotion_reward_command';

        $status = PromotionModel::where("status","<>", 1)
            ->isExists();
        if ($status) {
            $output->writeln('活动已关闭');
            return false;
        }
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
            $output->writeln('连续推广奖励计算中');
            return false;
        }
        $startTime = time();
        $apiRedis->set($redisKey, $startTime, 50);
        do {
            $output->info('连续推广奖励');
            (new PromotionRewardRepository())->addReward();
            //休眠等待下次执行
            $sleepTime = 10;
            $endTime = time();
            $output->info("休眠{$sleepTime}秒,执行开始时间" . date('Y-m-d H:i:s', $startTime) . ",执行结束时间" . date('Y-m-d H:i:s', $endTime) . "本次执行时间(s)：" . (time() - $startTime));
            sleep($sleepTime);
            $redisValue = $apiRedis->get($redisKey);
        } while ($redisValue);
        $output->info('连续推广奖励执行完毕');
    }
}
