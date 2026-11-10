<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Rbpay extends Paybase{
    public $url = "https://api.rb131411.com/index/underorder.do";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        //扫码
        $params = array(
            "appid" => $this->config['app_id'],
            "amount" => intval($order['money']),
            "callback_url" => $this->config['notify_url'],
            "pay_type" => isset($this->config['pay_type']) ? $this->config['pay_type'] : '0001',
            "out_trade_no" => $order['order_id'],
        );

        $params['sign'] = $this->getSign($params);
//        dump($params);die;
        Log::write(["RbpayParams", $params], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'form_params' => $params
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
//        dump($result);die;
        if (empty($result)) {
            Log::write(["RbpayError", $result], 'pay');
            throw new Exception(lang('error'));
        }

        Log::write(["RbpayResponse", $result], 'pay');
        if(isset($result['code']) && in_array($result['code'], [1,200])){
            return redirect($result['data']['url']) ;
        }else{
            throw new Exception(isset($result['msg']) ? $result['msg'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write(["RbpayNotifyParams", $params], 'pay');
        if (!isset($params['callbacks'])) {
            return 'sign error';
        }
        $sign_params = [
            'amount' => $params['amount'],
            'amount_true' => $params['amount_true'],
            'appid' => $params['appid'],
            'callbacks' => $params['callbacks'],
            'out_trade_no' => $params['out_trade_no'],
        ];
        if(isset($params['app_record_id'])) {
            $sign_params['app_record_id'] = $params['app_record_id'];
        }
        $sign = $this->getSign($sign_params);
        if ($sign != $params["sign"]) {
            return 'sign error';
        }

        if ($params["callbacks"] == "CODE_SUCCESS") {
            $remark = '';
            if ($params["callbacks"] == "CODE_SUCCESS") { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = isset($params['app_record_id']) ? $params['app_record_id'] : null;
            $amount_true = isset($params['amount_true']) ? $params['amount_true'] : null;
            $ret = $this->complete($params["out_trade_no"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'success';
            }
            return $ret;
        }

        return 'fail';
    }

    private function getSign($params) {
        unset($params['sign']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= "&key=" . $this->config['app_key'];
        return strtoupper(md5($md5str));
    }
}
