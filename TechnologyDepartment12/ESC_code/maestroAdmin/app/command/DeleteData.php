<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
class DeleteData extends Command {

    protected function configure() {
        $this->setName('deletedata')->setDescription('删除数据');
    }

    protected function execute(Input $input, Output $output) {
        $date = date("Y-m-d 00:00:00", strtotime("-30 day"));
        echo $date . "\n";
        $max_log_id = Db::name("money_log")->where("create_time", "<", strtotime($date))->max('id');
        $max_order_id = Db::name("order")->where("create_time", "<", strtotime($date))->max('id');
        echo $max_log_id . "\n";
        echo $max_order_id . "\n";
        while (true) {
            $ret = Db::name("money_log")->where("id", "<", $max_log_id)->limit(10000)->delete();
            if (!$ret) {
                break;
            }
            $output->info("删除中_money_log....." . date("Y-m-d H:i:s"));
            usleep(300*1000);
        }

        while (true) {
            $ret = Db::name("order")->where("id", "<", $max_order_id)->limit(10000)->delete();
            if (!$ret) {
                break;
            }
            $output->info("删除中_order....." . date("Y-m-d H:i:s"));
            usleep(300*1000);
        }
        usleep(500*1000);
        $output->info("done！");
    }
    

}
