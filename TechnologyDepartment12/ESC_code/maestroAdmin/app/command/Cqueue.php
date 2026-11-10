<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\input\Option;
use think\facade\Queue;
use think\facade\Cache;
class Cqueue extends Command {
    private $jobs = ['listen', 'money_log', 'order_log'];
    protected function configure() {
        $this->setName('queue:create')->setDescription('创建队列任务,请勿重复执行');
        $this->addArgument('job', Argument::OPTIONAL, "可选参数：" . implode(",", $this->jobs))
            ->addOption('delete', 'd', Option::VALUE_NONE, '删除队列');
    }

    protected function execute(Input $input, Output $output) {
        $job = $input->getArgument("job");
        if(!is_null($job)) {
            if (!in_array($job, $this->jobs)) {
                throw new \InvalidArgumentException(sprintf('参数 "%s" 不在可选参数范围内：' .  implode(",", $this->jobs), (string) $job));
            }
        }else{
            throw new \InvalidArgumentException('请填写参数，可选参数：' .  implode(",", $this->jobs));
        }
        $select = config("queue.select");
        if ($job == "listen") {
            if ($input->hasOption("delete")) {
                Cache::store("redis")->set("deleteQueue:Listen", true, 7*86400);
                $output->writeln("创建删除代付监听任务成功");
            }else{
                Cache::store("redis")->delete("deleteQueue:Listen");
                Queue::push("Listen", "", 'agentpay');
                $output->writeln("创建同步代付监听任务成功");
            }
        }
        if ($job == "money_log") {
            if ($input->hasOption("delete")) {
                Cache::store("redis{$select}")->delete("deleteQueue:money_log", true, 7*86400);
                $output->writeln("创建删除账变记录表分表任务成功");
            }else{
                Cache::store("redis{$select}")->delete("deleteQueue:money_log");
                Queue::push("MoneyLog", "", 'deletedata');
                $output->writeln("创建账变记录表分表任务成功");
            }
        }
        if ($job == "order_log") {
            if ($input->hasOption("delete")) {
                Cache::store("redis{$select}")->set("deleteQueue:order_log", true, 7*86400);
                $output->writeln("创建删除抢单订单记录表分表任务成功");
            }else{
                Cache::store("redis{$select}")->delete("deleteQueue:order_log");
                Queue::push("OrderLog", "", 'deletedata');
                $output->writeln("创建抢单订单记录表分表任务成功");
            }
        }
    }

}
