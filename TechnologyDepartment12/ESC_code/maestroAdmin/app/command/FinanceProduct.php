<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Cache;
use think\facade\Config;
use util\RedisSyncPool;

// 使用方法: php think finance_product -g 1
class FinanceProduct extends Command
{

    protected function configure()
    {
        $this->setName('finance_product')->setDescription('匹配队列处理');
        $this->addOption('group_id', 'g', Option::VALUE_REQUIRED, '分组ID');
    }


    protected function execute(Input $input, Output $output)
    {
        $apiRedis = Cache::store("redis");
        $redisKey = 'finance_product_command';
        //是否清除缓存
        if ($input->hasOption("group_id")) {
            $group_id = $input->getOption("group_id");
        } else {
            echo "请输入分组ID\n";
            return false;
        }
        $group_id = intval($group_id);
        $queue_number = Config::get('app.queue_number', 6);
        if ($group_id < 1 || $group_id > $queue_number) {
            echo "请输入分组ID\n";
            return false;
        }
        $group_id = intval($group_id);
        $redisKey = $redisKey . '_' . $group_id;
        if ($apiRedis->get($redisKey) == 1) {
            echo "匹配队列处理中\n";
            return false;
        }
        $WorkerT = new WorkerTimer();
        $redisConfig = config('cache.stores.redis');
        $redisPool = new RedisSyncPool($redisConfig, 1, 100);
        $limit = 50;
        $apiRedis->set($redisKey, 1, 60);
        $WorkerT->executeQueue($redisPool, $group_id, $limit);
//        echo "执行{$group_id}\n";
//        $group_arr = [
//            1 => [1, 2],
//            2 => [3, 4],
//            3 => [5, 6],
//        ];
//        foreach ($group_arr[$group_id] as $key) {
//            $WorkerT->exitQueue($redis, $redisPool, $key, $limit, $LevelRepository);
//            echo "执行\n";
//        }
        $apiRedis->set($redisKey, 2, 20);
    }
}
