<?php

namespace pay;

use app\admin\model\RechargeModel;
use app\common\model\AdPlanModel;
use app\common\model\ConfigModel;
use app\common\model\MoneyLogModel;
use app\common\model\PddTurntableCountModel;
use app\common\model\TurntableCountModel;
use app\common\model\UserMessageModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\RechargeRepository;
use app\event\Promotion;
use think\facade\Db;
use app\common\model\UserModel;
use think\facade\Log;

class Paybase {

    public function complete($order_id, $status, $remark, $trade_no, $amount_true = null) {
        if (!in_array($status, [2, 3])) {
            return 'error';
        }
//        Db::startTrans();
//        try {
            // 查询充值订单
            $order = Db::name('recharge')->where('order_id', $order_id)->lock(true)->find();
            if (!empty($order) && $order['status'] == 1) { //订单状态 1 待支付 2 通过 3 失效
                if (!is_null($amount_true) && $amount_true != $order['money']) {
                    $order['money'] = $amount_true;
                    $remark = '实付金额与订单金额不符，按实付金额上分';
                }
                // 充值成功，更新订单状态
                $update = [
                    "status" => $status,
                    "money" => $order['money'],
                    "remark" => $remark,
                    "pay_time" => timeToStr(),
                    "success_time" => time(),
                    'result' => json_encode(request()->param())
                ];
                if (!is_null($trade_no)) {
                    $update['trade_no'] = $trade_no;
                }
                Db::name('recharge')->where(['id' => $order['id']])->update($update);
                if ($status == 2) { //成功
                    $recharge = Db::table('qd_money_log')->where([
                        'order_id' => $order['id'],
                        'user_id' => $order['user_id'],
                        'type' => MoneyLogModel::TYPE_RECHARGE,
                    ])->find();
                    if (empty($recharge)) {
                        $user_id = $order['user_id'];
                        $amount = $order['money'];

                        // 判断是不是首充
                        // 用户可能点击多次充值，但是并未付款，以第一笔付款为首充
                        $first_recharge = RechargeModel::where('user_id', $user_id)
                            ->where('status', RechargeModel::STATUS_SUCCESS)
                            ->where('from', RechargeRepository::FROM_FRONT)
                            ->order('success_time')
                            ->find();
                        if ($first_recharge) {
                            if (!$first_recharge->is_first) {
                                $first_recharge->is_first = 1;
                                $first_recharge->save();
                                $order['is_first'] = 1;
                            }
                        }

                        $money_log = new MoneyLogModel();
                        $money_log->changeMoney($user_id, $amount, MoneyLogModel::TYPE_RECHARGE, 'add', '', '', 1, '', '', $order['id'],
                            false);
                        $money_log->changeMoney($user_id, $amount, MoneyLogModel::TYPE_USER, 'add', '用户充值', '用户充值_' . $amount,
                            1, '', '', $order['id']);

                        event('RechargeSuccess', $order);
//                        $user_model = new UserModel();
                        // 取消自动升级
//                        $user_model->vipUpgradeStatus($user_id, '', getConfig('level_upgrade_type'));
                        // 判断是否有分佣
                        $money_log->allotBrokerage($user_id, $amount);
                        //充值完成判断上级是否可以领取抽奖次数
//                        TurntableCountModel::isAddOnePCount($user_id,1,$amount);
                        //充值完成判断上级是否可以领取pdd抽奖次数
//                        PddTurntableCountModel::addLeader1Count($user_id, $amount);

                        //用户充值成功，判断是否有效如果有效给父级增加任务次数
                        UserModel::addRechargeTaskCount($user_id);


                        //给用户发信息
                        $fiat_currency_unit = ConfigModel::getConfigValue('fiat_currency_unit');
                        $param = [
                            'user_id' => $user_id,
                            'admin_id' => 0,
                            'type' => UserMessageModel::TYPE_RECHARGE,
                            'title' => '充值到账',
                            'content' => round($amount, 4) . $fiat_currency_unit,
                            'is_pop' => 0,
                        ];
                        (new UserMessageModel)->insertUserMessage($param);
                    }
                }
                return true;
            } else {
                return 'success';
            }
            // 提交事务
//            Db::commit();
//        } catch (\think\Exception $exc) {
//            Log::write($exc->getMessage() . "\n" . $exc->getTraceAsString(), "error");
//            // 回滚事务
//            Db::rollback();
//            return 'fail';
//        }
//        return true;
    }


    protected function redirect($message, $type = 'success') {
        $url = "/payed/{$type}/" . urlencode($message);
        return getWebUrl() . $url;
    }
    protected function success($message) {
        return $this->redirect($message, 'success');
    }
    protected function error($message) {
        return $this->redirect($message, 'error');
    }

}
