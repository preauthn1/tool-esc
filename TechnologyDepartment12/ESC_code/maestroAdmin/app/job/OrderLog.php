<?php
namespace app\job;

use think\queue\Job;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Queue;
class OrderLog {

    public function fire(Job $job, $data){
        $select = config("queue.select");
        $redis = Cache::store("redis{$select}");
        if ($redis->has('deleteQueue:order_log')) {
            $this->output("删除抢单订单记录分表");
            $job->delete();
            return;
        }
        if (!is_array($data) || empty($data)) {
            $data = [
                'date' => $this->get_min_date(),
                'id' => 0,
            ];
        }
        $date = getDateStr(strtotime($data['date']));
        $id = intval($data['id']);
        $today = getDateStr();
        $time = strtotime("{$date} 00:00:00");
        $yesterday = strtotime(getDateStr(strtotime("-1 days")) . " 00:00:00");
        $t_time = strtotime(date("Y-m-d 00:05:00", strtotime("+1 days")));
        if ($time >= $yesterday) {
            $this->output("抢单订单记录分表日期:{$date}; 等待下一天开始");
            Queue::later($t_time - time(), "OrderLog", $data, 'deletedata');
            $job->delete();
            return;
        }
        $Ymd = timeToStr(strtotime($date), 'Ymd');
        $full_table_name = "qd_order_{$Ymd}";
        //创建当日变帐统计表
        if (!existTable($full_table_name)) {
            $sql = <<<EOC
CREATE TABLE `{$full_table_name}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_xuni` tinyint(1) DEFAULT '0' COMMENT '虚拟用户',
  `user_id` int(10) NOT NULL DEFAULT '0' COMMENT '用户ID',
  `goods_id` int(10) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_name` varchar(120) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '商品名称',
  `pic` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '商品上传原始图',
  `price` decimal(20,4) DEFAULT NULL COMMENT '价格',
  `gray` decimal(20,4) DEFAULT NULL COMMENT '佣金',
  `order_sn` varchar(20) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '订单编号',
  `consignee` varchar(60) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '收货人',
  `mobile` varchar(60) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '手机',
  `area` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '0' COMMENT '地区',
  `address` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '地址',
  `zipcode` varchar(60) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '邮政编码',
  `status` tinyint(2) unsigned NOT NULL DEFAULT '1' COMMENT '状态 1 待支付 2 已完成 3 已失效',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `is_demo` tinyint(2) unsigned NOT NULL DEFAULT '0' COMMENT '是否是DEMO',
  `is_free` tinyint(1) DEFAULT '0' COMMENT '是否是免费商城',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE,
  KEY `idx_status_time` (`status`,`create_time`),
  KEY `is_xuni` (`is_xuni`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_bin ROW_FORMAT=FIXED;
EOC;
            Db::execute($sql);
        }
        $result = $this->get_data($id, $date);
        $dataSet = [];
        if (isArrayAndNotEmpty($result)) {
            foreach ($result as $item) {
                $id = $item['id'];
                $info = Db::table($full_table_name)->where([
                    'id' => $item['id'],
                ])->find();
                if (empty($info)) {
                    $dataSet[] = $item;
                }else{
                    continue;
                }
            }
        }
        $this->output("抢单订单记录分表日期:{$date}, ID:{$data['id']}");
        if (isArrayAndNotEmpty($dataSet) || isArrayAndNotEmpty($result)) {
            if (isArrayAndNotEmpty($dataSet)) {
                Db::table($full_table_name)->insertAll($dataSet);
            }
            $data = [
                'date' => $date,
                'id' => $id
            ];
            Queue::push("OrderLog", $data, 'deletedata');
        }else{
            $count1 = Db::table($full_table_name)->count();
            $count2 = Db::name('order')->whereTime("create_time", 'between', ["{$date} 00:00:00", "{$date} 23:59:59"])->count();
            if ($count2 == 0 || $count1 == $count2) {
                $data = [
                    'date' => getDateStr(strtotime($date) + 86400),
                    'id' => 0
                ];
                if ($time >= $yesterday) {
                    Queue::later($t_time - time(), "OrderLog", $data, 'deletedata');
                }else{
                    Queue::push("OrderLog", $data, 'deletedata');
                    $redis->set("deleteOrderLog:{$date}", 1);
                    Queue::later(10, "OrderLog@delete", ['date' => $date], 'deletedata');
                }
            }else{
                $data = [
                    'date' => $date,
                    'id' => 0
                ];
                Queue::push("OrderLog", $data, 'deletedata');
            }
        }
        $job->delete();
    }

    public function delete(Job $job, $data) {
        $select = config("queue.select");
        $redis = Cache::store("redis{$select}");
        if (!isset($data['date'])) {
            $job->delete();
            return;
        }

        $date = getDateStr(strtotime($data['date']));
        $today = getDateStr();
        $time = strtotime("{$date} 00:00:00");
        $yesterday = strtotime(getDateStr(strtotime("-1 days")) . " 00:00:00");
        if (!$redis->has("deleteOrderLog:{$date}")) {
            $this->output("删除抢单订单记录表数据，日期:{$date}, 未存在删除标示");
            $job->delete();
            return;
        }
        if ($time >= $yesterday) {
            $this->output("删除抢单订单记录表数据，日期:{$date}, 不能删除当天数据");
            $job->delete();
            return;
        }

        $this->output("删除抢单订单记录表数据，日期:{$date}");
        $max_log_id = Db::name("order")->whereTime("create_time", 'between', ["{$date} 00:00:00", "{$date} 23:59:59"])
                ->max('id');
        $count = 0;
        if ($max_log_id > 0) {
            while (true) {
                $ret = Db::name("order")->where("id", "<=", $max_log_id)
                        ->whereTime("create_time", 'between', ["{$date} 00:00:00", "{$date} 23:59:59"])
                        ->limit(10000)->delete();
                if (!$ret) {
                    break;
                }
                if ($count > 30) {
                    break;
                }
                $count++;
            }
        }
        if ($count > 30) {
            $job->release(1);
        }else{
            $redis->delete("deleteOrderLog:{$date}");
            $job->delete();
        }
    }

    private function get_min_date() {
        $create_time = Db::name('order')->min('create_time');
        return getDateStr($create_time);
    }

    private function get_data($id, $date) {
        return Db::name('order')
                ->where("id", ">", $id)
                ->whereTime("create_time", 'between', ["{$date} 00:00:00", "{$date} 23:59:59"])
                ->limit(10000)->select();
    }

    private function output($msg) {
        echo $msg . ";,时间:" . timeToStr() . "\n";
    }
    public function failed($data){
    }

}
