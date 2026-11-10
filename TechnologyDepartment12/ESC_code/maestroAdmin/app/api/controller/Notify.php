<?php

namespace app\api\controller;

use app\admin\model\CollectionDetailModel;
use app\admin\model\CollectionModel;
use app\admin\model\RechargeModel;
use app\admin\model\WithdrawModel;
use app\common\model\AdPlanModel;
use app\common\model\ConfigModel;
use app\common\model\MoneyLogModel;
use app\common\model\PaymentPlatformModel;
use app\common\model\PddTurntableCountModel;
use app\common\model\TurntableCountModel;
use app\common\model\UserMessageModel;
use app\common\model\UserModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\RechargeRepository;
use app\event\Promotion;
use app\exception\UpayException;
use pay\NOWPayments;
use pay\Odpay;
use pay\Shpay;
use pay\Uupay;
use pay\Vortaqpay;
use think\facade\Cache;
use app\BaseController;
use think\facade\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\facade\Log;
use think\facade\Request;
use think\response\Json;
use think\App;
use transfer\Vipdcpay;

class Notify extends BaseController
{
    public function __construct(App $app = null)
    {
        parent::__construct($app);
        // 记录日志
        Log::write("Notify Start", "pay");
        Log::write(request()->param(), "pay");
    }

    /**
     * 充值回调处理方法
     * @return Json
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws DataNotFoundException|Exception
     */
    public function recharge(): Json
    {

        $param = $this->request->param();
        $chain = $param['chain'];
        $address = $param['address'];
        $trade_no = $param['orderNo'];

        $redis = Cache::store('redis');
        $ret = $redis->get('recharge_' . $trade_no);
        if (empty($ret)) {
            $redis->set('recharge_' . $trade_no, 1, 10);
        } else {
            Log::write($param, "upay");
            $msg = lang('充值订单：%s，正在处理中，请勿重复提交', [$trade_no]);
            Log::write($msg, "upay");
            return json(['msg' => $msg]);
        }

        // 如果状态不是成功，不处理
        /** 状态 1待确认2成功3失效*/
        if ($param['status'] != 2) {
            return json(['code' => 0, 'msg' => lang('状态不正确')]);
        }

        // 不做参数校验
        $platform = PaymentPlatformModel::where('name', 'like', $param['coin'] . '%')->find();
        if (!$platform) {
            $platform = PaymentPlatformModel::where('name', 'like', $chain . '%')->find();
        }
        if (!$platform) {
            return json(['code' => 0, 'msg' => lang('币种不存在')]);
        }

        // 判断金额是否正确
        if ($param['amount'] < $platform->min_recharge_money) {
            $msg = "充值金额{$param['amount']}小于最小充值金额{$platform->min_recharge_money}";
            Log::write($msg, "upay");
            return json(['code' => 1, 'msg' => $msg]);
        }
//        $config = json_decode($platform->config, true);
//        $key = $config['key'];
//        unset($data['sign']);
//        $sign = PaymentPlatformModel::generateSign($data, $key);

        // 查找用户
        switch ($chain) {
            case 'TRC20':
                $user = UserModel::where('recharge_address', $address)->find();
                if (!$user) {
                    return json(['code' => 0, 'msg' => lang('用户不存在')]);
                }
                $user_address = $user->recharge_address;
                break;
            case 'BEP20':
                $user = UserModel::where('bsc_address', $address)->find();
                if (!$user) {
                    return json(['code' => 0, 'msg' => lang('用户不存在')]);
                }
                $user_address = $user->bsc_address;
                break;
            default:
                return json(['code' => 0, 'msg' => lang('用户不存在')]);

        }

        $user_id = $user->id;
        // 判断订单是否重复
        $exist = RechargeModel::where('trade_no', $trade_no)->find();
        if ($exist) {
            return json(['code' => 1, 'msg' => lang('充值订单已存在')]);
        }

        // 判断是不是首充
        $recharge_count = RechargeModel::where('user_id', $user_id)
            ->where('status', RechargeModel::STATUS_SUCCESS)
            ->where('from', RechargeRepository::FROM_FRONT)
            ->count();
        $is_first = false;
        if ($recharge_count == 0) {
            $is_first = true;
        }
        $amount = $param['amount'] * $platform->exchange_rate;
        // 生成充值订单
        $data = [
            'user_id' => $user_id,
            'original_money' => $param['amount'],
            'exchange_rate' => $platform->exchange_rate,
            'money' => $amount,
            'type' => RechargeModel::TYPE_CONTRACT,
            'status' => $param['status'],
            'trade_no' => $trade_no,
            'pay_account' => $param['from'],
            'pay_time' => timeToStr(),
            'from_address' => $param['from'],
            'to_address' => $address,
            'is_first' => $is_first,
            'hash' => $param['hash'],
            'success_time' => time(),
            'pay_id' => $platform->id,
            'result' => json_encode($param),
            'txid' => $param['txid'],
            'from' => RechargeRepository::FROM_FRONT
        ];
        Db::startTrans();
        try {
            $order = RechargeModel::create($data);// 修改用户余额
            $money_log = new MoneyLogModel();
            $money_log->changeMoney($user_id, $amount, MoneyLogModel::TYPE_RECHARGE, 'add', '', '', 1, '', '', $order->id,
                false);
            $money_log->changeMoney($user_id, $amount, MoneyLogModel::TYPE_USER, 'add', '用户充值', '用户充值_' . $amount,
                1, '', '', $order->id);
            Db::commit();
            // 取消自动升级
//            $user_model = new UserModel();
//            $user_model->vipUpgradeStatus($user_id, '', getConfig('level_upgrade_type'));
            // 判断是否有分佣
            $money_log->allotBrokerage($user_id, $amount);
            //充值完成判断上级是否可以领取抽奖次数
//            TurntableCountModel::isAddOnePCount($user_id,1,$amount);
            //充值完成判断上级是否可以领取pdd抽奖次数
//            PddTurntableCountModel::addLeader1Count($user_id, $amount);

            //用户充值成功，判断是否有效如果有效给父级增加任务次数
            UserModel::addRechargeTaskCount($user_id);
            // 发起归集
            $this->swapSingle($chain, $user_address, $param, $order);

        } catch (\Exception $e) {
            Log::write(lang('充值失败'), "upay");
            Log::write($e->getMessage() . "\n" . $e->getTraceAsString(), "upay");
            Db::rollback();
            return json(['code' => 0, 'msg' => lang('未知错误')]);
        }
        if ($order) {
            // 充值成功
            event('RechargeSuccess', $order);

            //给用户发信息
            $param = [
                'user_id' => $user_id,
                'admin_id' => 0,
                'type' => UserMessageModel::TYPE_RECHARGE,
                'title' => '充值到账',
                'content' => round($amount, 4) . 'USDT',
                'is_pop' => 0,
            ];
            (new UserMessageModel)->insertUserMessage($param);
            return json(['code' => 1]);
        } else {
            return json(['code' => 0, 'msg' => lang('未知错误')]);
        }
    }

    /**
     * 提现回调处理方法
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function withdraw(): Json
    {
        $param = $this->request->param();
        // $param['status'] /** 状态 1待转2待确认3成功4失败*/

        // 不做参数校验
        $platform = PaymentPlatformModel::where('name', 'like', $param['coin'] . '%')->find();
        if (!$platform) {
            $platform = PaymentPlatformModel::where('name', 'like', $param['chain'] . '%')->find();
        }
        if (!$platform) {
            return json(['code' => 0, 'msg' => '币种不存在']);
        }
//        $config = json_decode($platform->config, true);
//        $key = $config['key'];
//        unset($data['sign']);
//        $sign = PaymentPlatformModel::generateSign($data, $key);

        // 查找用户
        $user = UserModel::where('withdraw_address', $param['address'])->find();
        if (!$user) {
            return json(['code' => 0, 'msg' => lang('用户不存在')]);
        }

        $user_id = $user->id;
        // 判断订单是否重复
        $order = WithdrawModel::where('order_id', $param['orderNo'])->find();

        if (!$order) {
            return json(['code' => 1, 'msg' => lang('提现订单不存在')]);
        }
        if ($order && $order->status == 4) { // 4 已出款
            return json(['code' => 1, 'msg' => lang('提现订单已出款')]);
        }

        $amount = $param['amount'] * $platform->exchange_rate;
        // 修改提现订单状态
        WithdrawModel::startTrans();
        try {
            $type = '';
            switch ($param['status']) {
                case UserMessageModel::TYPE_WITHDRAW:  // 转账成功
                    $order->status = 4;
                    $money_log = new MoneyLogModel();
                    $money_log->changeMoney($user_id, $amount, MoneyLogModel::TYPE_FROZEN, 'sub', '提现成功', '', 2);

                    $order->trade_no = $param['txid'];
                    $type = UserMessageModel::TYPE_WITHDRAW;
//            $order->txid = $param['txid'];
                    $order->from_address = $param['from'];
                    $order->convert_money = $param['amount'];
                    $order->pay_time = timeToStr();
                    $order->result = json_encode($param, JSON_UNESCAPED_UNICODE);
                    $order->save();
                    break;
                case UserMessageModel::TYPE_WITHDRAW_FAIL:  // 转账失败
                    $order->status = 5;
                    $type = UserMessageModel::TYPE_WITHDRAW_FAIL;

                    $money_log = new MoneyLogModel();
                    $res = $money_log->changeMoney($user_id, $amount, MoneyLogModel::TYPE_FROZEN, 'sub', '提现失败', '', 2);
                    if ($res['code'] == 1) {
                        $money_log->changeMoney($user_id, $amount, MoneyLogModel::TYPE_BROKERAGE, 'add', '提现失败退回', '', 2);
                        if ($order['handling_fee'] > 0) {
                            $money_log->changeMoney($user_id, $order['handling_fee'], MoneyLogModel::TYPE_BROKERAGE,
                                'add', '用户提现手续费', '用户提现手续费', 2);
                        }
                    }

                    $order->result = json_encode($param, JSON_UNESCAPED_UNICODE);
                    $order->save();
                    break;
                default:
                    return json(['code' => 0, 'msg' => lang('未知错误')]);

            }

            // 给用户发信息
            $param = [
                'user_id' => $user_id,
                'admin_id' => 0,
                'type' => $type,
                'title' => UserMessageModel::$typeMap[$type],
                'content' => $amount . 'USDT',
                'is_pop' => 0,
            ];

            (new UserMessageModel)->insertUserMessage($param);

            WithdrawModel::commit();

        } catch (\Exception $e) {
            WithdrawModel::rollback();
            return json(['code' => 0, 'msg' => lang('未知错误')]);
        }
        return json(['code' => 1]);
    }

    /**
     * 归集回调
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function swap()
    {
        $param = $this->request->param();

        // 查找归集订单
        $collection = CollectionModel::where('batch_no', $param['batchNo'])->find();
        if (!$collection) {
            return json(['code' => 1, 'msg' => lang('归集订单不存在')]);
        }
        $collection_id = $collection->id;

        // 查询归集详情
        $collection_detail = CollectionDetailModel::where('collection_id', $collection_id)
            ->where('from_address', $param['from'])
            ->where('to_address', $param['address'])
            ->find();

        if ($collection_detail && $collection_detail->status === 3) {
            return json(['code' => 1, 'msg' => lang('订单已归集')]);
        }


        $amount = $param['amount'];

        $collection_detail_data = [
            'amount' => $amount,
            'chain' => $collection['chain'],
            'coin' => $collection['coin'],
            'from_address' => $param['from'],
            'to_address' => $param['address'],
            'hash' => $param['hash'],
            'status' => $param['status'], // 1-处理中 3-成功 4-失败
        ];

        if (!$collection_detail) {
            $collection_detail_data['collection_id'] = $collection_id;
            $collection_detail_data['create_time'] = time();
            CollectionDetailModel::create($collection_detail_data);
        } else {
            // 更新
            $collection_detail_data['update_time'] = time();
            CollectionDetailModel::where('collection_id', $collection_id)
                ->update($collection_detail_data);
        }

        if ($param['status'] == 3) {
            $collection->success = 1;
            $collection->pending = 0;
            $collection->fail = 0;
        } else {
            $collection->success = 0;
            $collection->pending = 0;
            $collection->fail = 1;
        }

        $collection->money = $amount;
        $collection->finish_time = time();
        $collection->status = 1; // 已完成
        $collection->save();

        return json(['code' => 1]);
    }

    /**
     * 发起归集
     * @param $chain
     * @param $user_address
     * @param $param
     * @param RechargeModel $order
     * @return void
     */
    public function swapSingle($chain, $user_address, $param, RechargeModel $order): void
    {
        try {
            $address = PaymentPlatformModel::getCollectionAddress($chain);
            $res = PaymentPlatformModel::swapSingle($address, $user_address, $param['amount'], $param['coin'],
                $chain);

            $data = $res['data'];

            // 记录归集订单号 $data['batchNo']
            $order->batch_no = $data['batchNo'];
            $order->save();

            CollectionModel::singleSwap($data, $user_address, $address, $order->order_id);
        } catch (UpayException | \Exception $e) {
            // 归集失败, 记录日志，不影响充值
            Log::write($e->getMessage(), 'upay');
        }
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function QeawapayRecharge()
    {
        $params = request()->param();
        if (!isset($params['mchId'])) {
            return 'requestParams error';
        }
        $platform = PaymentPlatformModel::json(['config'])
            ->where('config->mch_id', $params['mchId'])
            ->where('status', 1)
            ->find();
        if (!$platform) {
            return 'requestParams error';
        }
        $config = json_decode($platform->config, true);
        exit((new \pay\Qeawapay($config))->notify());
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function WatchpayRecharge()
    {
        $params = request()->param();
        if (!isset($params['mchId'])) {
            return 'requestParams error';
        }
        $platform = PaymentPlatformModel::json(['config'])
            ->where('config->class_name', 'Watchpay')
            ->where('status', 1)
            ->find();
        if (!$platform) {
            return 'requestParams error';
        }
        $config = json_decode($platform->config, true);
        exit((new \pay\Watchpay($config))->notify());
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function GctpkpayRecharge()
    {
        $params = request()->param();
        if (!isset($params['busiCode'])) {
            return 'requestParams error';
        }
        $platform = PaymentPlatformModel::json(['config'])
            ->where('config->payin_busi_code', $params['busiCode'])
            ->where('status', 1)
            ->find();
        if (!$platform) {
            return 'requestParams error';
        }
        $config = json_decode($platform->config, true);
        return (new \pay\Gctpkpay($config))->notify();
    }


    /**
     * @throws DbException
     * @throws Exception
     */
    public function UupayRecharge()
    {
        $params = request()->param();
        if (!isset($params['callbackParams'])) {
            return 'requestParams error';
        }
        $platform = PaymentPlatformModel::json(['config'])
            ->where('config->class_name', 'Uupay')
            ->where('status', 1)
            ->find();
        if (!$platform) {
            return 'requestParams error';
        }
        $config = json_decode($platform->config, true);
        return (new Uupay($config))->notify();
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function VortaqpayRecharge()
    {
        $params = request()->param();
        if (!isset($params['signature']) && !isset($params['payment_id'])) {
            return 'requestParams error';
        }
        // 查询订单
        $order = Db::name('recharge')
            ->where('order_id', $params['order_id'])
            ->find();
        if (empty($order)) {
            exit('success');
        }
        $platform = PaymentPlatformModel::find($order['pay_id']);
        if (!$platform) {
            exit('requestParams error');
        }
        $config = json_decode($platform->config, true);
        exit((new Vortaqpay($config))->notify());
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function OdpayRecharge()
    {
        $params = request()->param();
        if (!isset($params['merchantId']) && !isset($params['sign'])) {
            return 'requestParams error';
        }
        $platform = PaymentPlatformModel::json(['config'])
            ->where('config->class_name', 'Odpay')
            ->where('status', 1)
            ->find();
        if (!$platform) {
            return 'requestParams error';
        }
        $config = json_decode($platform->config, true);
        exit((new Odpay($config))->notify());
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function NowpaymentsRecharge()
    {
        $params = request()->param();
        if (!isset($params['signature']) && !isset($params['payment_id'])) {
            return 'requestParams error';
        }
        // 查询订单
        $order = Db::name('recharge')
            ->where('order_id', $params['order_id'])
            ->find();
        if (empty($order)) {
            exit('OK');
        }
        $platform = PaymentPlatformModel::find($order['pay_id']);
        if (!$platform) {
            exit('requestParams error');
        }
        $config = json_decode($platform->config, true);
        exit((new NOWPayments($config))->notify());
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function Shpay()
    {
        $params = request()->param();
        if (!isset($params['sign']) && !isset($params['outTradeNo'])) {
            return 'requestParams error';
        }
        $platform = PaymentPlatformModel::json(['config'])
            ->where('config->class_name', 'Shpay')
            ->where('status', 1)
            ->find();
        if (!$platform) {
            return 'requestParams error';
        }
        $config = json_decode($platform->config, true);
        if ($params['event'] == 'PAYIN') {
            return (new Shpay($config))->notify();
        } else if ($params['event'] == 'PAYOUT') {
            return (new \transfer\Shpay($config))->notify();
        }
        return 'fall';
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function QeawapayWithdrawal()
    {
        //
        $params = request()->param();
        if (!isset($params['merNo'])) {
            return 'requestParams error';
        }
        $platform = PaymentPlatformModel::json(['config'])
            ->where('config->mch_id', $params['merNo'])
            ->where('status', 1)
            ->find();
        if (!$platform) {
            return 'requestParams error';
        }
        $config = json_decode($platform->config, true);
        exit((new \transfer\Qeawapay($config))->notify());
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function WatchpayWithdrawal()
    {
        //
        $params = request()->param();
        if (!isset($params['merNo'])) {
            return 'requestParams error';
        }
        $platform = PaymentPlatformModel::json(['config'])
            ->where('config->class_name', 'Watchpay')
            ->where('status', 1)
            ->find();
        if (!$platform) {
            return 'requestParams error';
        }
        $config = json_decode($platform->config, true);
        exit((new \transfer\Watchpay($config))->notify());
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function GctpkpayWithdrawal()
    {
        //
        $params = request()->param();
        if (!isset($params['merNo'])) {
            return 'requestParams error';
        }
        $platform = PaymentPlatformModel::json(['config'])
            ->where('config->mer_no', $params['merNo'])
            ->where('status', 1)
            ->find();
        if (!$platform) {
            return 'requestParams error';
        }
        $config = json_decode($platform->config, true);
        return (new \transfer\Gctpkpay($config))->notify();
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function UupayWithdrawal()
    {
        $params = request()->param();
        if (!isset($params['callbackParams'])) {
            return 'requestParams error';
        }
        $platform = PaymentPlatformModel::json(['config'])
            ->where('config->class_name', 'Uupay')
            ->where('status', 1)
            ->find();
        if (!$platform) {
            return 'requestParams error';
        }
        $config = json_decode($platform->config, true);
        return (new \transfer\Uupay($config))->notify();
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function VortaqpayWithdrawal()
    {
        $params = request()->param();
        if (!isset($params['signature']) && !isset($params['payment_id'])) {
            return 'requestParams error';
        }
        // 查询订单
        $order = Db::name('withdrawals')
            ->where('order_id', $params["order_id"])
            ->find();
        if (empty($order)) {
            exit('success');
        }
        $platform = PaymentPlatformModel::find($order['channel']);
        if (!$platform) {
            exit('requestParams error');
        }
        $config = json_decode($platform->config, true);
        exit((new \transfer\Vortaqpay($config))->notify());
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function OdpayWithdrawal()
    {
        $params = request()->param();
        if (!isset($params['merchantId']) && !isset($params['sign'])) {
            return 'requestParams error';
        }
        $platform = PaymentPlatformModel::json(['config'])
            ->where('config->class_name', 'Odpay')
            ->where('status', 1)
            ->find();
        if (!$platform) {
            return 'requestParams error';
        }
        $config = json_decode($platform->config, true);
        exit((new \transfer\Odpay($config))->notify());
    }

    /**
     * @throws DbException
     * @throws Exception
     */
    public function NowpaymentsWithdrawal()
    {
        $params = request()->param();
        if (!isset($params['signature']) && !isset($params['payment_id'])) {
            return 'requestParams error';
        }
        // 查询订单
        $order = Db::name('withdrawals')
            ->where('order_id', $params["order_id"])
            ->find();
        if (empty($order)) {
            exit('success');
        }
        $platform = PaymentPlatformModel::find($order['channel']);
        if (!$platform) {
            exit('requestParams error');
        }
        $config = json_decode($platform->config, true);
        exit((new \transfer\NOWPayments($config))->notify());
    }

    /**
     * VIPDC支付充值回调处理函数
     *
     * 该函数处理VIPDC支付平台的充值回调请求，验证参数合法性，
     * 查询订单信息并调用支付平台的异步通知处理方法
     *
     * @return string 返回处理结果字符串
     */
    public function VipdcpayRecharge()
    {
        $params = request()->param();
        // 验证必要参数是否存在
        if (!isset($params['sign']) && !isset($params['merchant_id'])) {
            return 'requestParams error：sign or merchant_id';
        }
        // 查询订单
        $order = Db::name('recharge')
            ->where('order_id', $params['merchant_order_no'])
            ->find();
        // 订单不存在则直接返回成功
        if (empty($order)) {
            exit('success');
        }
        // 获取支付平台配置信息
        $platform = PaymentPlatformModel::find($order['pay_id']);
        if (!$platform) {
            exit('requestParams error：pay_id');
        }
        $config = json_decode($platform->config, true);
        // 调用VIPDC支付平台的异步通知处理并退出程序
        exit((new \pay\Vipdcpay($config))->notify());
    }

    /**
     * VIPDC支付提现回调处理函数
     *
     * 该函数处理VIPDC支付平台的充值回调请求，验证参数合法性，
     * 查询订单信息并调用支付平台的异步通知处理方法
     *
     * @return string 返回处理结果字符串
     */
    public function VipdcpayWithdrawal()
    {
        $params = request()->param();
        // 验证必要参数是否存在
        if (!isset($params['sign']) && !isset($params['merchant_id'])) {
            return 'requestParams error';
        }

        // 查询订单
        $order = Db::name('withdrawals')
            ->where('order_id', $params['merchant_order_no'])
            ->find();
        // 订单不存在则直接返回成功
        if (empty($order)) {
            exit('success');
        }
        // 获取支付平台配置信息
        $platform = PaymentPlatformModel::find($order['channel']);
        if (!$platform) {
            exit('requestParams error');
        }
        $config = json_decode($platform->config, true);
        // 调用VIPDC支付平台的异步通知处理并退出程序
        exit((new Vipdcpay($config))->notify());
    }

}
