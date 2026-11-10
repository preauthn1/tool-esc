<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Banana extends Paybase {

    public $url = "https://api.banana-pay.com/api/gateway/pay";
    public $get_order_url = "http://api.xxpay666.com/customer.Pay.query";
    public $config = [];

    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $data = [
            'merchant_no' => $this->config['app_id'], //商户id
            'timestamp' => time(), //订单时间
            'sign_type' => 'MD5',
        ];
        $params = [
            'merchant_ref' => $order['order_id'],
            'product' => $this->config['pay_type'],
            'amount' => sprintf4f($order['money']),
            'extend_params' => strval($order['user_id']),
//            'notify_url' => $this->config['notify_url'],
        ];
        $data['params'] = json_encode($params);
        $data['sign'] = $this->getSign($data);
//        dump($this->config['notify_url']);die;
        Log::write(["BananaParams", $data], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'form_params' => $data
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response);
    }

    public function validateResponse($response) {
        $contents = $response->getBody()->getContents();
//        dump($contents);
        $result = json_decode($contents, true);
//        dump($result);
        if (empty($result)) {
            Log::write(["BananaError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["BananaResponse", $result], 'pay');

        $params = isset($result['params']) ? json_decode($result['params'], true) : [];
//        dump($params);die;
        if (is_array($result) && isset($result['code']) && $result['code'] == '200') {
            if (isset($params['payurl']) && $params['payurl']) {
                return redirect($params['payurl']);
            }
            return $params;
        } else {
            $message = isset($result['message']) ? $result['message'] : 'unkown';
        }
        throw new Exception($message);
    }

    public function notify() {
        $request = request()->param();
        if (!isset($request['sign'])) {
            return 'sign empty';
        }
        Log::write(["BananaNotifyParams", $request], 'pay');
        $sign = $this->getSign($request);
        if ($request['sign'] != $sign) {
            return 'sign error';
        }
        $params = isset($request['params']) ? json_decode($request['params'],true) : [];
        $status = isset($params['status']) ? (int)$params['status'] : 0;
        if (in_array($status, [1,3,5])) {
            $remark = '';
            //订单状态，1：支付，2：待定，3：失败 Order status, 1: Success, 2: Pending, 5: Reject
            if ($status == 1) { //成功
                $status = 2;
            } else { //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $request['system_ref'];
            $amount_true = null;
            $ret = $this->complete($request["merchant_ref"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'SUCCESS';
            }
            return $ret;
        }
        return 'fail';
    }

    private function getSign($data) {
        //组装签名字段 签名 MD5(merchant_no+params+sign_type+timestamp+Key)-说明key 是商户秘钥
        $merchant_no = isset($data['merchant_no']) ? $data['merchant_no'] : '';
        $params = isset($data['params']) ? $data['params'] : '';
        $sign_type = isset($data['sign_type']) ? $data['sign_type'] : '';
        $timestamp = isset($data['timestamp']) ? $data['timestamp'] : '';
        $sign_str = $merchant_no . $params . $sign_type . $timestamp . $this->config['app_key'];
        $sign = md5($sign_str); //MD5签名 不区分大小写  MD5 signature is not case sensitive
        return $sign;
    }

}
