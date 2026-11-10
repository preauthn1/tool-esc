<?php

namespace transfer;

use app\common\model\MoneyLogModel;
use think\facade\Db;
use think\facade\Log;
use think\Model;

class Transferbase {

    public function complete($order_id, $status, $remark, $trade_no = null) {
        if (!in_array($status, [2, 3])) {
            return 'error';
        }

        Db::startTrans();
        try {
            $order = Db::name('withdrawals')->where('order_id', $order_id)->lock(true)->find();
            if (!empty($order) && $order['status'] == 4) {
                $money = $order['money'] + $order['taxfee'];
                if ($status == 2) { //成功
                    Db::name('home_user')->where(['id' => $order['user_id']])->setInc('frozen_money', (-1 * $money));
                }else{ //失败
                    addMoneyLog($order['user_id'], $money, 8, '提现转账失败');
                    Db::name('home_user')->where(['id' => $order['user_id']])->setInc('frozen_money', (-1 * $money));
                }
                $update = [
                    'status' => $status,
                    'remark' => $remark,
                    "trade_no" => $trade_no,
                    "pay_time" => timeToStr(),
                ];
                if (!is_null($trade_no)) {
                    $update['trade_no'] = $trade_no;
                }
                Db::name('withdrawals')->where(['id' => $order['id']])->update($update);
            }
            // 提交事务
            Db::commit();
        } catch (\think\Exception $exc) {
            Log::write($exc->getMessage() . "\n" . $exc->getTraceAsString(), "error");
            // 回滚事务
            Db::rollback();
            return 'fail';
        }
        return true;
    }

    public function completeDcpay($order_id, $status, $remark, $trade_no, $result) {
        Db::startTrans();
        try {
            $order = Db::name('withdrawals')->where('order_id', $order_id)->lock(true)->find();
            if (!empty($order)) {
                $money = $order['original_money'] + $order['handling_fee'];//手续费+提现金额
                // 订单状态 “1” 交易中 “2”： 成功 “3”：失败 5：“退款， 成功转失败”
                $money_log = new MoneyLogModel();
                $user_id = $order['user_id'];
                // 提现冻结钱包减
                $title = '提现成功';
                if ($status == 2) { //成功
                    // 提现钱包加
                    $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_WITHDRAWAL, 'add', $title, '', 2);
                } else {
                    $title = '提现失败';
                    // 提现失败，基础钱包加
                    $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_USER, 'add', $title, '用户提现失败，退回基础余额', 2);
                }
                // 提现冻结钱包减
                $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_FROZEN, 'sub', $title,$remark, 2);
                $update = [
                    'status' => $status,
                    'remark' => $remark,
                    "trade_no" => $trade_no,
                    "pay_time" => timeToStr(),
                    "result" => $result,
                ];
                if (!is_null($trade_no)) {
                    $update['trade_no'] = $trade_no;
                }
                Db::name('withdrawals')->where(['id' => $order['id']])->update($update);
            }
            // 提交事务
            Db::commit();
        } catch (\think\Exception $exc) {
            Log::write($exc->getMessage() . "\n" . $exc->getTraceAsString(), "error");
            // 回滚事务
            Db::rollback();
            return 'fail';
        }
        return true;
    }

}
