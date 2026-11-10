<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Globalpay extends Paybase{
    public $url = "http://gyials.gdsua.com/ty/orderPay";
    public $get_order_url = "http://gyials.gdsua.com/ty/orderQuery";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $countryCode = $this->getCountryCode();
        $ccy_no = $this->getCcyCode();
        $params = array(
            'mer_no' => $this->config['app_id'],
            'mer_order_no' => $order['order_id'],
            'pname' => $this->config['real_name'],
            'pemail' => $this->config['mobile'] . "@abc.com",
            'phone' => $this->config['mobile'],
            'order_amount' => sprintf("%.2f", $order['money']),
            'countryCode' => $countryCode,
            'ccy_no' => $ccy_no,
            'notifyUrl' => $this->config['notify_url'],
            'busi_code' => $this->config['pay_type'],
            'goods' => 'Charge',
//            'pageUrl'  => $this->success(lang('付款成功')),
        );
        if (isset($this->config['pay_receive_bankcode']) && $this->config['pay_receive_bankcode'] != '') {
            $params['bankCode'] = $this->config['pay_receive_bankcode'];
        }
        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["GlobalPayParams", $params], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'json' => $params
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
            'mer_no' => $this->config['app_id'],
            'mer_order_no' => $order_id,
            'request_no' => random(32),
            'request_time' => date('YmdHis')
        );
        $params['sign'] = $this->getSign($params);
        Log::write(["GlobalPayFindParams", $params], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->get_order_url, [
                'json' => $params
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
            Log::write(["GlobalPayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["GlobalPayResponse", $result], 'pay');
        if ($type == 'find') {
            if(isset($result['query_status']) && $result['query_status'] === 'SUCCESS'){
                return $result;
            }else{
                $msg = isset($result['errorMessages']) ? $result['errorMessages'] : "unkown error";
            }
        }else{
            if(isset($result['status']) && $result['status'] === 'SUCCESS'){
                return strexists($result['order_data'], 'http://') || strexists($result['order_data'], 'https://') ?
                        redirect($result['order_data']) : ['vaNumber' => $result['order_data']];
            }else{
                $msg = isset($result['err_msg']) ? $result['err_msg'] : "unkown error";
            }
        }
        throw new Exception($msg);
    }

    public function notify() {
        $params = request()->param();
        Log::write(["GlobalPayNotifyParams", $params], 'pay');
        $sign = $this->getSign(myArrayFilter($params));
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
        if (!isset($params["mer_order_no"])) {
            return 'orderId error';
        }
        $find = $this->find($params["mer_order_no"]);
        Log::write(["GlobalPayFindNotifyParams", $find], 'pay');
        if ($find && in_array($find["order_status"], ['FAIL', 'SUCCESS', 'CLOSED'])) {
            $remark = '';
            if (in_array($find['order_status'], ['SUCCESS'])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $find['order_no'];
            $amount_true = null;
            $ret = $this->complete($params["mer_order_no"], $status, $remark, $trade_no, $amount_true);
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
        $md5str .= "&key=" . $this->config['app_key'];
        return strtolower(md5($md5str));
    }

    private function getCountryCode() {
        $code = i18n_currency_code();
        $res = [
            'CNY' => 'CNY',
            'RP' => 'IDN',
            'INR' => 'IND',
            'VND' => 'VNM',
            'THB' => 'THA',
            'RM' => 'MYS',
            'NGA' => 'NGA',
        ];

        return isset($res[$code]) ? $res[$code] : $code;
    }
    private function getCcyCode() {
        $code = i18n_currency_code();
        $res = [
            'CNY' => 'CNY',
            'RP' => 'IDR',
            'INR' => 'INR',
            'VND' => 'VND',
            'THB' => 'THB',
            'RM' => 'MYR',
            'NGA' => 'NGN',
        ];

        return isset($res[$code]) ? $res[$code] : $code;
    }
}
