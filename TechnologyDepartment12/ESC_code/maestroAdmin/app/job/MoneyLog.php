<?php
namespace app\job;

use think\queue\Job;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Queue;
class MoneyLog {

    public function fire(Job $job, $data){
        $select = config("queue.select");
        $redis = Cache::store("redis{$select}");
        if ($redis->has('deleteQueue:money_log')) {
            $this->output("删除账变记录分表");
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
            $this->output("账变记录分表日期:{$date}; 等待下一天开始");
            Queue::later($t_time - time(), "MoneyLog", $data, 'deletedata');
            $job->delete();
            return;
        }
        $Ymd = timeToStr(strtotime($date), 'Ymd');
        $full_table_name = "qd_money_log";
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
        $this->output("账变记录分表日期:{$date}, ID:{$data['id']}");
        if (isArrayAndNotEmpty($dataSet) || isArrayAndNotEmpty($result)) {
            if (isArrayAndNotEmpty($dataSet)) {
                Db::table($full_table_name)->insertAll($dataSet);
            }
            $data = [
                'date' => $date,
                'id' => $id
            ];
            Queue::push("MoneyLog", $data, 'deletedata');
        }else{
            $count1 = Db::table($full_table_name)->count();
            $count2 = Db::name('money_log')->whereTime("create_time", 'between', ["{$date} 00:00:00", "{$date} 23:59:59"])->count();
            if ($count2 == 0 || $count1 == $count2) {
                $data = [
                    'date' => getDateStr(strtotime($date) + 86400),
                    'id' => 0
                ];
                if ($time >= $yesterday) {
                    Queue::later($t_time - time(), "MoneyLog", $data, 'deletedata');
                }else{
                    Queue::push("MoneyLog", $data, 'deletedata');
                    $redis->set("deleteMoneyLog:{$date}", 1);
                    Queue::later(10, "MoneyLog@delete", ['date' => $date], 'deletedata');
                }
            }else{
                $data = [
                    'date' => $date,
                    'id' => 0
                ];
                Queue::push("MoneyLog", $data, 'deletedata');
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
        if (!$redis->has("deleteMoneyLog:{$date}")) {
            $this->output("删除账变记录表数据，日期:{$date}, 未存在删除标示");
            $job->delete();
            return;
        }
        if ($time >= $yesterday) {
            $this->output("删除账变记录表数据，日期:{$date}, 不能删除当天数据");
            $job->delete();
            return;
        }

        $this->output("删除账变记录表数据，日期:{$date}");
        $max_log_id = Db::name("money_log")->whereTime("create_time", 'between', ["{$date} 00:00:00", "{$date} 23:59:59"])
                ->max('id');
        $count = 0;
        if ($max_log_id > 0) {
            while (true) {
                $ret = Db::name("money_log")->where("id", "<=", $max_log_id)
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
            $redis->delete("deleteMoneyLog:{$date}");
            $job->delete();
        }
    }


    private function get_data($id, $date) {
        return Db::name('money_log')
                ->where("id", ">", $id)
                ->whereTime("create_time", 'between', ["{$date} 00:00:00", "{$date} 23:59:59"])
                ->limit(10000)
                ->select();
    }

    private function get_min_date() {
        $create_time = Db::name('money_log')->min('create_time');
        return getDateStr($create_time);
    }

    private function output($msg) {
        echo $msg . ";,时间:" . timeToStr() . "\n";
    }
    public function failed($data){
    }

}
