<?php

namespace app\job;

use app\common\model\ConfigModel;
use think\facade\Cache;
use think\facade\Db;
use think\queue\Job;

class AutoYuebao {

    public function fire(Job $job, $data) {
        $redis = Cache::store('redis');
        if (!isset($data['user_id'])) {
            $job->delete();
            return;
        }
        $userid = $data['user_id'];
        $configModel = new ConfigModel();
        $configInfo = $configModel->getConfig();
        //判断是否开启了自动存入余额宝
        if(!isset($configInfo['auto_yuebao_current_open']) ||
                $configInfo['auto_yuebao_current_open'] <= 0 ||
                $configInfo['auto_yuebao_rate_id'] <= 0){
            $redis->delete("auto_yuebao_current:{$userid}");
            $job->delete();
            return;
        }
        $yuebao_rate = Db::name('yuebao_rate')->where(['id' => $configInfo['auto_yuebao_rate_id']])->find();
        if (empty($yuebao_rate)) { //余额宝设置不存在
            $job->delete();
            return;
        }
        $cache_key = "autoyuebao_{$userid}";
        if (Cache::has($cache_key)) {
            $job->release(10);
            return;
        }
        Cache::set($cache_key, $userid, 60);
        $user = Db::name('home_user')->where('id', $userid)->lock()->find();
        if (empty($user) || $user['user_money'] <= 0) { //用户不存在或余额为0
            $redis->delete("auto_yuebao_current:{$userid}");
            $job->delete();
            return;
        }
        $time = isset($data['time']) ? $data['time'] : $user['last_login_time'];
        if (empty($time)) {
            $time = strtotime("-1 day");
        }
        $nowtime = time();
        $second = hour2second($configInfo['auto_yuebao_time']);
        if ($second <= 0) {
            $second = hour2second('02:00');
        }
        $delay = 0;
        if ($configInfo['auto_yuebao_current_open'] == 1) { //抢完单后N小时自动存入余额宝
            $order = $this->getLastOrderInfo($userid);
            if (empty($order) || (!empty($order) && ($nowtime - $order['create_time']) > $second)) {
                $this->deposit($user, $yuebao_rate);
            } else { //等待时间到了存入
                $delay = $order['create_time'] - $nowtime + $second;
                if ($delay < 1) {
                    $delay = 1;
                }
            }
        }elseif($configInfo['auto_yuebao_current_open'] == 2) { //第2天凌晨N点自动存入余额宝
            $tomorrow_time = strtotime(timeToStr($time, "Y-m-d 23:59:59")) + 1 + $second;
            if ($nowtime >= $tomorrow_time) { //当前时间大于设定时间，自动存入余额宝
                $this->deposit($user, $yuebao_rate);
            } else {
                $delay = $tomorrow_time - $nowtime;
                if ($delay < 1) {
                    $delay = 1;
                }
            }
        }

        if ($delay > 0) {
            //等待时间到了存入
            $this->output("用户ID：「{$user['id']}」,等待时间:{$delay}秒;");
            Cache::rm($cache_key);
            $job->release($delay);
            return;

        }

        $redis->delete("auto_yuebao_current:{$userid}");
        $job->delete();
    }

    private function deposit($user, $rate) {
        if (empty($user) || empty($rate)) {
            return false;
        }
        $gray = sprintf4f($user['user_money'] * $rate['rate'] / 10000);
        if ($gray <= 0) {
            return false;
        }
        $data = [
            'user_id' => $user['id'],
            'money' => $user['user_money'],
            'principal' => $user['user_money'],
            'rate' => $rate['rate'],
            'days' => $rate['days'],
            'gray' => 0,
            'create_time' => time(),
            'is_xuni' => $user['is_xuni'],
            'is_demo' => $user['is_demo']
        ];
        $ret = Db::name('yuebao')->insert($data);
        if ($ret) {
            addMoneyLog($user['id'], (-1 * $user['user_money']), 5, '', 1);
            checkLevel($user['id']); // 会员升级
            $this->output("用户ID：「{$user['id']}」,余额：「{$user['user_money']}」自动存入余额宝！");
            return true;
        }
        return false;
    }


    private function getLastOrderInfo($userid, $time = 'today') {
        $order = Db::name('order')
                ->where('user_id', $userid)->order("id DESC")->find();
        if ($time == 'today' && empty($order)) {
            return $this->getLastOrderInfo($userid, 'yesterday');
        }

        return $order;
    }


    private function output($msg) {
        echo $msg . ";,时间:" . timeToStr() . "\n";
    }

    public function failed($data) {

    }

}
