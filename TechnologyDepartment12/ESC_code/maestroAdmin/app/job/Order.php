<?php

namespace app\job;

use app\common\model\OrderModel;
use think\facade\Cache;
use think\queue\Job;

class Order {

    public function fire(Job $job, $data) {
        if (!isset($data['user_id']) || !isset($data['order_id'])) {
            $job->delete();
            return;
        }
        $order_id = $data['order_id'];
        $userid = $data['user_id'];
        $date = $data['date'];
        $cache_key = "order_{$userid}_{$order_id}";
        if (Cache::has($cache_key)) {
            $job->release(10);
            return;
        }
        Cache::set($cache_key, $userid, 60);
        if ($date) {
            $Ymd = timeToStr(strtotime($date), 'Ymd');
        }else{
            $Ymd = timeToStr(time(), 'Ymd');
        }
        $order = OrderModel::where('id', $data['order_id'])->where("status", 5)->find();
        if (empty($order)) {
            $job->delete();
            return;
        }
        echo "用户ID：{$userid}, 订单ID：{$order_id}, 返佣：{$order['gray']}, 时间：" . date("Y-m-d H:i:s") . PHP_EOL;
        addMoneyLog($userid, $order['gray'], 4, '返佣', 0, 0, 0, $order_id); // 返佣
        OrderModel::where('id', $order_id)->update(['status' => 2]);
        checkLevel($userid, true);             // 会员升级
        fenxiao($userid, $order_id);        // 分销
        $job->delete();
    }

    public function failed($data) {

    }

}
