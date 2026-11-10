<?php
namespace app\job;

use think\queue\Job;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Queue;
use app\admin\model\DayReportModel;
class UserReport {

    public function fire(Job $job, $data){
        $select = config("queue.select");
        $redis = Cache::store("redis{$select}");
        if ($redis->has('deleteQueue:user_report')) {
            $this->output("删除用户报表队列:{$data['uid']}");
            $key1 = "user_report:uid_".$data['uid'];
            $redis->delete($key1);
            $job->delete();
            return;
        }
        $date = $data['date'];
        $uid = $data['uid'];
        if (empty($date)) {
            $date = getDateStr();
        }else{
            $date = getDateStr(strtotime($date));
        }
        $user = Db::name('home_user')->where('id', $uid)->where('status', 1)->find();
        if (empty($user)) {
            $this->output("用户不存在，删除用户报表队列:{$data['uid']}");
            $key1 = "user_report:uid_".$data['uid'];
            $redis->delete($key1);
            $job->delete();
            return;
        }
        $nowdate = getDateStr();
        $t_time = strtotime(date("Y-m-d 02:05:00", strtotime("+1 days")));
        if ($nowdate == $date) {
            $this->output("统计用户:{$uid},日期:{$date}");
            Queue::later($t_time - time(), "UserReport", $data, 'userreport');
            $job->delete();
            return;
        }
        $result = $this->get_money_data($uid, $date);
        $dataSet = [];
        if (isArrayAndNotEmpty($result)) {
            foreach ($result as $item) {
                $row = [
                    'user_id' => $uid,
                    'is_xuni' => $user['is_xuni'],
                    'stat_date' => $date,
                    'type' => $item['type'],
                    'total' => $item['total'],
                ];
                $report = Db::name('user_report')->where([
                    'user_id' => $uid,
                    'stat_date' => $date,
                    'type' => $item['type']
                ])->find();
                if (empty($report)) {
                    $row['create_time'] = timeToStr();
                    $row['update_time'] = timeToStr();
                    $dataSet[] = $row;
                }else{
                    $row['update_time'] = timeToStr();
                    Db::name('user_report')->where(['id' => $report['id']])->update($row);
                }
            }
        }
        $order_count = $this->get_order_count($uid, $date);
        if($order_count > 0) {
            $row = [
                'user_id' => $uid,
                'is_xuni' => $user['is_xuni'],
                'stat_date' => $date,
                'type' => 9999,
                'total' => $order_count,
            ];
            $report = Db::name('user_report')->where([
                'user_id' => $uid,
                'stat_date' => $date,
                'type' => 9999
            ])->find();
            if (empty($report)) {
                $row['create_time'] = timeToStr();
                $row['update_time'] = timeToStr();
                $dataSet[] = $row;
            }else{
                $row['update_time'] = timeToStr();
                Db::name('user_report')->where(['id' => $report['id']])->update($row);
            }
        }
        if (isArrayAndNotEmpty($dataSet)) {
            Db::name('user_report')->insertAll($dataSet);
        }
        $data['date'] = getDateStr(strtotime($date) + 86400);
        if ($date != $nowdate) {
            Queue::push("UserReport", $data, 'userreport');
            if (isset($user['leader3']) && $user['leader3'] == 0) {
                $this->create_day_queue([
                    'uid' => $uid,
                    'date' => $date,
                ]);
            }
        }else{
            Queue::later($t_time - time(), "UserReport", $data, 'userreport');
        }
        $this->output("统计用户:{$uid},日期:{$date}");
        $job->delete();
    }

    private function create_day_queue($data) {
        Queue::push("UserReport@day", [
                'uid' => $data['uid'],
                'date' => $data['date'],
                'key' => 'all'
            ], 'userreport');
    }


    public function day(Job $job, $data){
        $uid = $data['uid'];
        $key = isset($data['key']) ? $data['key'] : null;
        $date = getDateStr(strtotime($data['date']));
        $dayReportModel = new DayReportModel();
        $dayReportModel->agent_stat($date, true, $uid, $key);
        $this->output("统计用户日报表:{$uid},日期:{$date}");
        $job->delete();
    }

    private function get_money_min_date($uid, $date) {
        $create_time = Db::name('money_log')
                ->where("user_id", $uid)
                ->whereTime("create_time", ">=", $date)
                ->min('create_time');
        return getDateStr($create_time);
    }

    private function get_money_data($uid, $date) {
        return Db::name('money_log')
            ->where("user_id", $uid)
            ->whereTime("create_time", 'between', ["{$date} 00:00:00", "{$date} 23:59:59"])
            ->field("sum(money) AS total, type")->group("type")
            ->select();
    }
    private function get_order_count($uid, $date) {
        $full_table_name = "qd_order_" . timeToStr(strtotime($date), "Ymd");
        if (existTable($full_table_name)) {
            return Db::table($full_table_name)
                    ->where("user_id", $uid)
                    ->whereTime("create_time", 'between', ["{$date} 00:00:00", "{$date} 23:59:59"])
                    ->count();
        }else{
            return Db::name('order')
                    ->where("user_id", $uid)
                    ->whereTime("create_time", 'between', ["{$date} 00:00:00", "{$date} 23:59:59"])
                    ->count();
        }
    }

    public function create(Job $job, $data) {
        $uid = isset($data['uid']) ? $data['uid'] : 0;
        $date = isset($data['date']) ? $data['date'] : null;
        $select = config("queue.select");
        $redis = Cache::store("redis{$select}");
        if ($redis->has('deleteQueue:user_report')) {
            $this->output("删除用户报表队列:create");
            $job->delete();
            return;
        }
        $map = [];
        if ($uid > 0) {
            $map['id'] = $uid;
        }
        $user = db("home_user")->where('status', 1)->where($map)->field("id,create_time")->select();
        foreach ($user as $val) {
            if(!$date){
                $created_time = getDateStr(getMkTime($val['create_time']));
            }else{
                $created_time = getDateStr(getMkTime($date));
            }

            $time = strtotime($created_time . " 00:00:00");
            $key1 = "user_report:uid_".$val['id'];
            if (!$redis->has($key1)) {
                if ($time > time()) {//大于当前日期时延迟到指定日期当天执行任务
                    $delay = $time - time();
                    Queue::later($delay, "UserReport", ["uid" => $val['id'], "date" => $created_time], 'userreport');
                }else{
                    Queue::push("UserReport", ["uid" => $val['id'], "date" => $created_time], 'userreport');
                }
                $redis->set($key1, 1);
            }
        }
        //每天执行一次
        $t_time = strtotime(date("Y-m-d 02:00:00", strtotime("+1 days")));
        if (empty($uid)) {
            Queue::later($t_time - time(), "UserReport@create", [], 'userreport');
        }
        $job->delete();
    }



    private function output($msg) {
        echo $msg . ";,时间:" . timeToStr() . "\n";
    }
    public function failed($data){
    }

}
