<?php
namespace app\job;

use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use think\helper\Str;
use think\queue\Job;

class Agentpay {
    public function fire(Job $job, $id){
        $order = Db::name("withdrawals")->where("id", $id)->lock()->where("status", 4)->find();
        if(empty($order)) {
            $job->delete(); //删除
            return;
        }

        $className = "\\transfer\\" . Str::studly($order['channel']);
        $pays = config("pay");
        if (!isset($pays[$order['channel']]) || !class_exists($className)) {
            $r = $this->update_record($id, 3, '未设置代付渠道');
            $job->delete(); //删除
            return;
        }
        $config = $pays[$order['channel']];
        $config['notify_url'] = getWebUrl() . "/notify/transfer/{$order['channel']}";

        try {
            $pay = new $className($config);
            $transfer_result = $pay->pay($order);
            if ($transfer_result && isset($config['transfer_check']) && $config['transfer_check'] == 1) {
                $cache = Cache::store("redis");
                if (!$cache->has("transfer_check_{$id}")) {
                    $cache->set("transfer_check_{$id}", 1, 600);
                    Queue::later(180, "Agentpay@query", $id, 'agentpay');
                }
            }
        } catch (\Exception $exc) {
            Log::write($exc->getMessage(), 'error');
            Log::write($exc->getTraceAsString(), 'error');
            $r = $this->update_record($id, 3, $exc->getMessage());
        }

        $job->delete(); //删除
    }

    //查询代付状态
    public function query(Job $job, $id){
        $order = Db::name("withdrawals")->where("id", $id)->lock()->where("status", 4)->find();
        if(empty($order)) {
            $job->delete(); //删除
            return;
        }

        $className = "\\transfer\\" . Str::studly($order['channel']);
        $pays = config("pay");
        if (!isset($pays[$order['channel']]) || !class_exists($className)) {
            $r = $this->update_record($id, 3, '未设置代付渠道');
            $job->delete(); //删除
            return;
        }
        $config = $pays[$order['channel']];

        try {
            $pay = new $className($config);
            $notify = $pay->notify($order['order_id']);
            if ($notify === 'continue') {
                $job->release(30); //30秒后重试
            }else{
                $job->delete(); //删除
            }
        } catch (\Exception $exc) {
            Log::write($exc->getMessage(), 'error');
            Log::write($exc->getTraceAsString(), 'error');
            $r = $this->update_record($id, 3, $exc->getMessage());
            $job->delete(); //删除
        }

    }

    /**
     * 处理提现记录
     * @param type $id
     * @param type $status
     * @param array $remark
     */
    private function update_record($id, $status, $remark = '') {
        Db::startTrans();
        try {
            $order = Db::name('withdrawals')->where('id', $id)->lock(true)->find();
            if (!empty($order) && $order['status'] == 4) {
                $money = $order['money'] + $order['taxfee'];
                if ($status == 2) { //成功
                    Db::name('home_user')->where(['id' => $order['user_id']])->setInc('frozen_money', (-1 * $money));
                }else{ //失败
                    addMoneyLog($order['user_id'], $money, 8, '提现转账失败');
                    Db::name('home_user')->where(['id' => $order['user_id']])->setInc('frozen_money', (-1 * $money));
                }
                Db::name('withdrawals')->where(['id' => $order['id']])->update([
                    'status' => $status,
                    'remark' => $remark,
                    'check_time' => time(),
                ]);
            }
            // 提交事务
            Db::commit();
        } catch (\think\Exception $exc) {
            Log::write($exc->getMessage() . "\n" . $exc->getTraceAsString(), "error");
            // 回滚事务
            Db::rollback();
        }
        return true;
    }

    public function failed($data){
    }

}
