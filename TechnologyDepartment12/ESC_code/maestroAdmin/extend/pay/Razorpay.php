<?php

namespace pay;

use app\common\model\ConfigModel;
use app\common\model\UserModel;
use Exception;
use Razorpay\Api\Api;
use think\facade\Db;
use think\facade\Log;

class Razorpay extends Paybase{
    public $config = [];
    public $api = null;
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
        $this->api = new Api($this->config['app_id'], $this->config['app_key']);
    }

    public function pay($order) {
        $user = UserModel::getOneUser($order['user_id']);
        $setting = (new ConfigModel)->getConfig();
        try {
            $raz_order = $this->api->order->create([
                    'receipt' => $order['order_id'],
                    'amount' => intval($order['money'] * 100),
                    'currency' => 'INR'
                ]
            );
//            dump($raz_order);
            Db::name('recharge')->where('order_id', $order["order_id"])->update([
                'trade_no' => $raz_order->id
            ]);

            $assign = [
                'config' => $this->config,
                "order" => $order,
                'raz_order' => $raz_order,
                'user' => $user,
                'setting' => $setting,
            ];
            return view(__DIR__ . "/razorpay_web.html", $assign);
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write([$params, "RazorPayNotifyParams"], 'pay');
        if (empty($params)) {
            return 'requestParams error';
        }
        if (isset($params['entity']) && $params['entity'] === 'event') {
            $request = request();
            $content = $request->getContent();
            $sign = $request->header('X-Razorpay-Signature');
            $this->api->utility->verifyWebhookSignature($content, $sign, $this->config['webhook_secret']);
            Log::write([$params, "RazorPayWebHook"], 'pay');
            $payload = $params['payload'];
            $payload_payment = isset($payload['payment']['entity']) ? $payload['payment']['entity'] : null;
            if (!isArrayAndNotEmpty($payload_payment)) {
                return 'payload_payment error';
            }
            $payment = $this->api->payment->fetch($payload_payment['id']);
            $raz_order = $this->api->order->fetch($payload_payment['order_id']);
            if ($params['event'] == 'payment.failed' && $payment) { //失败
                $remark = 'payment failed';
                $res = $this->updateOrder($raz_order['receipt'], 3, $remark);
            }elseif($params['event'] == 'payment.captured') { //成功
                if ($payment->status === 'captured' && $raz_order->status === 'paid') {
                    $this->updateExtra($raz_order['receipt'], $payment->id);
                    $res = $this->updateOrder($raz_order['receipt'], 2);
                    if (is_string($res)) {
                        abort(502, $res);
                    }
                }
            }
            return 'success';
        }else{
            $order = null;
            if (isset($params['error'])) { //失败
                $metadata = json_decode($params['error']['metadata'], true);
                if (isset($metadata) && isset($metadata['order_id']) && $metadata['order_id'] != '') {
//                    $order = Db::name('recharge')->where('trade_no', $metadata['order_id'])->find();
                    $remark = isset($params['reason']) ? $params['reason'] : 'payment failed';
                    if ($order) {
//                        $this->updateExtra($order['order_id'], $metadata['payment_id']);
//                        $this->updateOrder($order['order_id'], 3, $remark);
                    }
                    return $this->error($remark);
                }
            }elseif(isset($params['razorpay_order_id']) && $params['razorpay_order_id'] != ''){
                $order = Db::name('recharge')->where('trade_no', $params['razorpay_order_id'])->find();
                $attributes  = [
                    'razorpay_signature'  => $params['razorpay_signature'],
                    'razorpay_payment_id'  => $params['razorpay_payment_id'],
                    'razorpay_order_id'  => $order['trade_no'],
                ];
                try {
                    $this->api->utility->verifyPaymentSignature($attributes);
                } catch (Exception $ex) {
                    return $this->error($ex->getMessage());
                }
            }
            if (empty($order)) {
                return $this->error('payment failed');
            }
            try {
                $payment = $this->api->payment->fetch($params['razorpay_payment_id']);
                $raz_order = $this->api->order->fetch($order['trade_no']);
            } catch (Exception $ex) {
                return $this->error($ex->getMessage());
            }
            if ($payment->status === 'captured' && $raz_order->status === 'paid') {
                $status = 2;
            }else{
                $status = 3;
                $remark = 'payment failed';
            }
            $this->updateExtra($order['order_id'], $payment->id);
            $res = $this->updateOrder($order['order_id'], $status, $remark);
            if ($res === true) {
                return $this->success(lang('付款成功'));
            }else{
                return $this->error($res !== false ? $res : lang('付款失败'));
            }
        }
    }

    private function updateExtra($order_id, $payment_id = null) {
        if ($payment_id) {
            Db::name('recharge')->where('order_id', $order_id)->update([
                'extra' => $payment_id
            ]);
        }
    }
    private function updateOrder($order_id, $status, $remark = '') {
        if (!in_array($status, [2,3])) {
            return false;
        }

        $trade_no = null;
        $amount_true = null;
        return $this->complete($order_id, $status, $remark, $trade_no, $amount_true);
    }

    private function redirect($message, $type = 'success') {
        $url = '/payed?' . http_build_query(['type' => $type, 'message' => $message]);
        return redirect($url);
    }
    private function success($message) {
        return $this->redirect($message, 'success');
    }
    private function error($message) {
        return $this->redirect($message, 'error');
    }
}
