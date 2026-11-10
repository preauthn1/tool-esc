<?php

namespace pay;

use GuzzleHttp\Exception\RequestException;
use think\Exception;
use think\facade\Log;
use util\NowPaymentsAPI;

class NOWPayments extends Paybase
{

    public $config = [];

    public function __construct($config)
    {
        if (empty($config['api_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order)
    {
        $order_id = $order['order_id'];
        $amount = $order['money'] ?? 0;
        $amount = sprintf('%.2f', $amount);
        $currency = $this->config['currency'] ?? 'usd';
        // Set callback URL for payment notifications
        $callbackUrl = $this->config['ipn_callback_url']; // '/api/payment/notify/nowpayments';

        // Create payment request using the NOWPayments API
        $paymentData = [
            'price_amount' => $amount,
            'price_currency' => $currency,
            'order_id' => $order_id,
            'order_description' => "Recharge " . $amount . " " . $currency,
            'ipn_callback_url' => $callbackUrl,
            'success_url' => $this->config['success_url'] ?? '',
            'cancel_url' => $this->config['cancel_url'] ?? '',
            'is_fixed_rate' => false, // 是否需要手续费
            'is_fee_paid_by_user' => false, // 手续费由用户支付
        ];

        $header = [
            'x-api-key' => $this->config['api_key'],
            'Content-Type'=>'application/json'
        ];
        Log::write(["NOWPayments", $paymentData], 'pay');
        try {
//            $client = new Client();
//            $response = $client->post($this->config['api_url'] . $this->config['invoice_url'], [
//                'body' => json_encode($paymentData),
//                'headers' => $header
//            ]);
            $nowPaymentsAPI = new NowPaymentsAPI($this->config['api_key']);
            $response = $nowPaymentsAPI->createInvoice($paymentData);
        } catch (RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response);
    }

    public function validateResponse($contents)
    {
        $result = json_decode($contents, true);
        if (empty($result)) {
            Log::write(["NOWPayments", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["NOWPayments", $result], 'pay');
        if (empty($result)) {
            throw new \Exception($result['message'] ?? '支付信息错误');
        }
        if (isset($result['invoice_url'])) {
            return $result['invoice_url'];
        }
        $message = $result['errorMsg'] ?? 'unknown';
        throw new Exception($message);
    }

    public function notify()
    {
        $request = request()->param();
        Log::write(["NOWPaymentsNotifyParams", $request], 'pay');

        // 检查必要参数
        if (!isset($request['payment_status'])) {
            return 'requestParams error';
        }

        $order_id = $request['order_id'] ?? '';
        $paymentStatus = $request['payment_status'] ?? '';
        $price_amount = $request['price_amount'] ?? '';
        $paymentId = $request['payment_id'] ?? '';
        $payAmount = $request['pay_amount'] ?? 0;
        $payCurrency = $request['pay_currency'] ?? '';

        // 验证签名
        $data = [
            'payment_status' => $paymentStatus,
            'order_id' => $order_id,
            'price_amount' => $price_amount,
            'payment_id' => $paymentId,
            'pay_amount' => $payAmount,
            'pay_currency' => $payCurrency
        ];

        // 过滤空值
        $data = array_filter($data, function ($value) {
            return $value !== '' && $value !== null;
        });

        // 按键名排序
        ksort($data);

        // 构造签名字符串
        $signString = http_build_query($data);

        // 生成签名
        $signature = hash_hmac('sha512', $signString, $this->config['ipn_secret']);
        // 验证签名
        $flag = hash_equals($signature, $request['signature'] ?? '');

        if ($flag) {
            $remark = '';
            $status = 1; // 默认待支付
            // 根据支付状态更新订单
            if ($paymentStatus == 'confirmed' || $paymentStatus == 'completed') {
                // 支付成功
                $status = 2;
            } elseif ($paymentStatus == 'cancelled' || $paymentStatus == 'expired') {
                // 支付失败或取消
                $status = 3;
                $remark = 'Payment Fail or Cancelled';
            }
            $trade_no = $paymentId;
            $amount_true = $payAmount;

            // 更新订单状态
            $ret = $this->complete($order_id, $status, $remark, $trade_no, $amount_true);

            if ($ret === true) {
                return 'OK';
            }
            return $ret ?: 'fail';
        }
        return 'fail';
    }


}
