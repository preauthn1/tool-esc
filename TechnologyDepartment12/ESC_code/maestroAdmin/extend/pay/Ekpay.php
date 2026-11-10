<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Ekpay extends Paybase{
    public $url = "http://gateway.ekpay.iwins.in/v1/idpay/pay_center";
    public $get_order_url = "http://gateway.ekpay.iwins.in/v1/idpay/pay_order_query";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = array(
            'merchant_no'        => $this->config['app_id'],
            'merchant_order' => $order['order_id'],
            'amount'       => sprintf("%.2f", $order['money']),
            'notify_url' => $this->config['notify_url'],
            'return_url'  => $this->success(lang('付款成功')),
            'return_method'      => '2',
            'pay_type'     => $this->config['pay_type'],
            'additional'      => $order['user_id'],
        );
        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["EkpayParams", $params], 'pay');
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

    public function find($order_id) {
        $params = array(
            'merchant_no' => $this->config['app_id'],
            'merchant_order' => $order_id,
            'query_time' => timeToStr(),
        );
        $params['sign'] = $this->getSign($params);
        Log::write(["EkpayFindParams", $params], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->get_order_url, [
                'form_params' => $params
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response, 'find');
    }

    public function validateResponse($response, $type = 'pay') {
        $contents = $response->getBody()->getContents();
//        dump($contents);
        $result = json_decode($contents, true);
//        dump($result);die;
        if (empty($result)) {
            Log::write(["EkpayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["EkpayResponse", $result], 'pay');
        if ($type == 'find') {
            if(isset($result['result_code']) && $result['result_code'] == 200){
                return $result;
            }else{
                $msg = isset($result['result_message']) ? $result['result_message'] : "unkown error";
            }
        }else{
            if(isset($result['code']) && $result['code'] == 200){
                return redirect($result['data']['url']);
            }else{
                $msg = isset($result['message']) ? $result['message'] : "unkown error";
            }
        }
        throw new Exception(isset($result['message']) ? $result['message'] : "unkown error");
    }

    public function notify() {
        $params = request()->param();
        Log::write(["EkpayNotifyParams", $params], 'pay');
        $sign = $this->getSign(myArrayFilter($params));
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
        $find = $this->find($params["merchant_order"]);
        Log::write(["EkpayFindNotifyParams", $find], 'pay');
        if ($find && in_array($find["status"], [2, 4, 5, 6, 7])) {
            $remark = '';
            if (in_array($find['status'], [2])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $find['merchant_order'];
            $amount_true = $find['paid'];
            $ret = $this->complete($params["merchant_order"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'SUCCESS';
            }
            return $ret;
        }
        return 'fail';
    }

    private function getSign($params) {
        unset($params['sign']);
        unset($params['pay_id']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= "&secret=" . $this->config['app_key'];
        return strtoupper(md5($md5str));
    }
}
