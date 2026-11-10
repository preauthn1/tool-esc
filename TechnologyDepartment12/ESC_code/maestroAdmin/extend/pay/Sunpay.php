<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Sunpay extends Paybase{
    public $url = "http://payment.errhqhm.cn/index/paymentorder";
    public $get_order_url = "http://payment.errhqhm.cn/index/getorder";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = array(
            'appid'        => $this->config['app_id'],
            'pay_type'     => $this->config['pay_type'],
            'out_trade_no' => $order['order_id'],
            'amount'       => sprintf("%.2f", $order['money']),
            'callback_url' => $this->config['notify_url'],
            'success_url'  => getWebUrl(),
            'error_url'  => getWebUrl(),
            'version'      => 'v2.0',
            'out_uid'      => $order['user_id'],
        );
        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["SunpayParams", $params], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->url . "?format=json", [
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
            'appid'        => $this->config['app_id'],
            'out_trade_no' => $order_id,
        );
        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["SunpayFindParams", $params], 'pay');
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
        $result = json_decode($contents, true);
        if (empty($result)) {
            Log::write(["SunpayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["SunpayResponse", $result], 'pay');
        if(isset($result['code']) && $result['code'] == 200){
            return $type == 'pay' ? redirect($result['url']) : $result['data'][0];
        }else{
            throw new Exception(isset($result['msg']) ? $result['msg'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write(["SunpayNotifyParams", $params], 'pay');
        $sign = $this->getSign(myArrayFilter($params));
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
        $find = $this->find($params["out_trade_no"]);
        Log::write(["SunpayFindNotifyParams", $find], 'pay');

        if ($find && in_array($find["status"], [2,3,4])) {
            $remark = '';
            if (in_array($find['status'], [4])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $params['out_trade_no'];
            $amount_true = null;
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
        unset($params['pay_id']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= "&key=" . $this->config['app_key'];
        return strtoupper(md5($md5str));
    }
}
