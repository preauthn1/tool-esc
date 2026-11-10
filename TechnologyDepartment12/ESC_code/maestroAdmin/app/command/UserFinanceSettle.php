<?php

namespace app\command;

use app\common\repository\ActivityNewRecordRepository;
use app\common\repository\HomeUserBrokeragePoolRepository;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\InvestmentProductOrderRepository;
use app\common\repository\PromotionRewardRepository;
use app\common\repository\WithdrawalRepository;
use think\facade\Cache;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class UserFinanceSettle extends Command
{
    protected function configure()
    {
        // 正常执行指令 php think userfinancesettle
        $this->setName('userfinancesettle')->setDescription('用户投资结算');
    }

    protected function execute(Input $input, Output $output)
    {
        $apiRedis = Cache::store("redis");
        $FinanceRedisKey = 'finance_product_order_repository_settlement';

        if ($apiRedis->get($FinanceRedisKey, 0) == 0) {
            $apiRedis->set($FinanceRedisKey, 1, 350);
//            sleep(10);
            //广告位库存结算
            (new FinanceProductOrderRepository())->settlement();
            $output->info('广告位库存结算成功');

            (new WithdrawalRepository)->movieToWait();
            $output->info('提现状态更新成功');

            (new InvestmentProductOrderRepository())->settlement();
            $output->info('AI智能托管结算成功');

            (new HomeUserBrokeragePoolRepository())->settlement();
            $output->info('佣金池结算');

            (new PromotionRewardRepository())->addReward();
            $output->info('连续推广奖励');

            (new ActivityNewRecordRepository())->trialBonusReturn();
            $output->info('体验金退回');

            $apiRedis->set($FinanceRedisKey, 0, 1);
        }

//        (new WithdrawalRepository)->movieToWait();
//        $output->info('提现状态更新成功');
//
//        (new InvestmentProductOrderRepository())->settlement();
//        $output->info('AI智能托管结算成功');

    }
}
