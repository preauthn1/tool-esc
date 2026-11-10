<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Jfpay extends Paybase{
    public $url = "https://api.jfynp168.com/index/unifiedorder";
    public $get_order_url = "https://api.jfynp168.com/index/queryCollectOrder";
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
            'out_trade_no' => $order['order_id'],
            'version'      => 'v2.0',
            'pay_type'     => $this->config['pay_type'],
            'amount'       => sprintf("%.2f", $order['money']),
            'callback_url' => $this->config['notify_url'],
            'success_url'  => $this->success(lang('付款成功')),
            'error_url'  => $this->error(lang('付款失败')),
            'out_uid'      => $order['user_id'],
        );
        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["JfpayParams", $params], 'pay');
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
        Log::write(["JfpayFindParams", $params], 'pay');
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
            Log::write(["JfpayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["JfpayResponse", $result], 'pay');
        if(isset($result['code']) && (
                ($result['code'] == 200 && $type == 'pay') ||
                ($result['code'] == 1 && $type == 'find')
            )){
            return $type == 'pay' ? redirect($result['url']) : $result['data'];
        }else{
            throw new Exception(isset($result['msg']) ? $result['msg'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write(["JfpayNotifyParams", $params], 'pay');
        $sign = $this->getSign(myArrayFilter($params));
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
        $find = $this->find($params["out_trade_no"]);
        Log::write(["JfpayFindNotifyParams", $find], 'pay');
        if ($find && in_array($find["status"], [1, 2, 4])) {
            $remark = '';
            if (in_array($find['status'], [4])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $find['order_no'];
            $amount_true = $find['amount_true'];
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
