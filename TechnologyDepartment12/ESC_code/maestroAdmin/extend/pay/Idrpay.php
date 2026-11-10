<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Idrpay extends Paybase{
    public $url = "https://idrpays.com/api/fundin/deposit";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = array(
            "merchant_id" => $this->config['app_id'],
            "business_email" => $this->config['api_user'],
            "order_id" => $order['order_id'],
            "deposit_method_id" => isset($this->config['pay_type']) ? intval($this->config['pay_type']) : 1,
            "bank_id" => $this->config['pay_receive_bankcode'],
            "deposit_amount" => sprintf("%.2f", $order['money']),
            'currency' => 'IDR',
            'customer_name' => isset($this->config['real_name']) ? $this->config['real_name'] : '',
            'customer_email' => '',
            'customer_phone_no' => '',
            'customer_address' => '',
            'note' => '',
            'website_url' => '',
            "request_time" => time(),
            "success_url" => getWebUrl(),
            "fail_url" => getWebUrl(),
            "callback_noti_url" => $this->config['notify_url'],
        );

        $signature = $this->config['app_key'].$params['merchant_id'].$params['business_email'].$params['order_id'].$params['deposit_method_id'].
                $params['bank_id']. $params['deposit_amount']. $params['currency']. $params['customer_name']. $params['request_time'].
                $params['success_url']. $params['fail_url']. $params['callback_noti_url'];
        $params['sign_data'] = strtoupper(hash('md5', $signature));

        Log::write(["IdrpayParams", $params], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'form_params' => ['requestParams' => json_encode($params)],
                'auth' => [$this->config['api_user'], $this->config['api_password']],
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
        $result = json_decode($contents, true);
        if (empty($result)) {
            Log::write(["IdrpayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }

        Log::write(["IdrpayResponse", $result], 'pay');
        if(isset($result['errCode']) && $result['errCode'] === 0){
            return redirect($result['redirect_url']);
        }else{
            throw new Exception(isset($result['error']) ? $result['error'] : "unkown error");
        }
    }

    public function notify() {
        $params = json_decode(request()->param('requestParams'), true);
        Log::write(["IdrpayNotifyParams", $params], 'pay');
        if (empty($params)) {
            return 'requestParams error';
        }
        $sign = $this->getSign($params);
        if ($sign != $params["sign_data"]) {
            return 'sign error';
        }

        if ($params["order_status_code"] == "000") {
            $remark = '';
            if (in_array($params['order_status_code'], ['000'])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $params['trade_no'];
            $amount_true = null;
            $ret = $this->complete($params["order_id"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return json_encode(['received' => 'Yes']);
            }
            return $ret;
        }
    }

    private function getSign($params) {
        $signature = $this->config['app_key'] . $params["merchant_id"] . $params['order_id'] . $params['deposit_method_id'] . $params['currency'];
        return strtoupper(hash('md5', $signature));
    }
}
