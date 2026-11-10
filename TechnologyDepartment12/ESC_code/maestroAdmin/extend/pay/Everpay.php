<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Everpay extends Paybase{
    public $url = "https://openapi.ever-pay.net/gateway/pay";
    public $get_order_url = "https://openapi.ever-pay.net/gateway/query";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['public_key']) || empty($config['private_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = array(
            'merchantCode'        => $this->config['app_id'],
            'method'        => $this->config['pay_type'],
            'orderNum' => $order['order_id'],
            'payMoney'       => intval($order['money']),
            'productDetail' => 'recharge',
            'name' => $this->config['real_name'],
            'email' => $this->config['mobile'] . "@abc.com",
            'phone' => $this->config['mobile'],
            'notifyUrl' => $this->config['notify_url'],
            'redirectUrl'  => $this->success(lang('付款成功')),
            "dateTime" => date('YmdHis'),
            'expiryPeriod' => '1000',
//            'bankCode' => 'bankCode'
        );
        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["EverpayParams", $params], 'pay');
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
            'merchantCode' => $this->config['app_id'],
            'queryType' => 'ORDER_QUERY',
            'orderNum' => $order_id,
            'dateTime' => date("YmdHis")
        );
        $params['sign'] = $this->getSign($params);
        Log::write(["EverpayFindParams", $params], 'pay');
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
            Log::write(["EverpayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["EverpayResponse", $result], 'pay');

        if($result['platRespCode'] === 'SUCCESS'){
            return $type == 'pay' ? redirect(sprintf("https://openapi.ever-pay.net/cash/?orderNum=%s", $result['platOrderNum'])) : $result;
        }else{
            $msg = isset($result['platRespMessage']) ? $result['platRespMessage'] : "unkown error";
        }
        throw new Exception($msg);
    }

    public function notify() {
        $params = request()->param();
        if (!isset($params['platSign'])) {
            return 'sign empty';
        }
        Log::write(["EverpayNotifyParams", $params], 'pay');
        $decryptSign = $this->public_key_decrypt($params['platSign'], $this->config['public_key']);
        $params_str = $this->getParams($params);
        if ($decryptSign != $params_str) {
            return 'sign error:' . json_encode([$decryptSign, $params_str]) ;
        }
        if (isset($params["code"]) && in_array($params["code"], ['00'])) {
            $find = $this->find($params["orderNum"]);
            Log::write(["EverpayFindNotifyParams", $find], 'pay');
            $remark = '';
            if (in_array($find["status"], ['SUCCESS'])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $params['platOrderNum'];
            $amount_true = null;
            $ret = $this->complete($params["orderNum"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'SUCCESS';
            }
            return $ret;
        }
        return 'fail';
    }


    private function getSign($params) {
        return $this->pivate_key_encrypt($this->getParams($params), $this->config['private_key']);
    }

    private function getParams($params) {
        unset($params['sign']);
        unset($params['platSign']);
        unset($params['pay_id']);
        ksort($params);
        $params_str = '';
        foreach ($params as $key => $val) {
            $params_str = $params_str . $val;
        }
        return $params_str;
    }

    private function pivate_key_encrypt($data, $pivate_key) {
        $pivate_key = 'REDACTED';
        $pi_key = openssl_pkey_get_private($pivate_key);
        $crypto = '';
        foreach (str_split($data, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, $pi_key);
            $crypto .= $encryptData;
        }

        return base64_encode($crypto);
    }

    private function public_key_decrypt($data, $public_key) {
        $public_key = '-----BEGIN PUBLIC KEY-----' . "\n" . $public_key . "\n" . '-----END PUBLIC KEY-----';
        $data = base64_decode($data);
        $pu_key = openssl_pkey_get_public($public_key);
        $crypto = '';
        foreach (str_split($data, 128) as $chunk) {
            openssl_public_decrypt($chunk, $decryptData, $pu_key);
            $crypto .= $decryptData;
        }

        return $crypto;
    }

}
