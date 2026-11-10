<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use app\common\model\BankModel;
use app\common\model\UserModel;
use think\facade\Log;

class Ekpay extends Transferbase{
    public $url = "http://gateway.ekpay.iwins.in/v1/idpay/remit";
    public $get_order_url = "http://gateway.ekpay.iwins.in/v1/idpay/remit_order_query";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $mobile = UserModel::where('id', $order['user_id'])->value('mobile');
        $params = [
            "merchant_no" => $this->config['app_id'],
            "merchant_order" => $order['order_id'],
            "amount" => strval(intval($order['money'])),
            "ifsc" => !isset($order['bank_kh']) || empty($order['bank_kh']) ? $this->getBankIfsc($order['bank_name']) : $order['bank_kh'],
            "bank_name" => $order['bank_name'],
            'receiver_type' => "1",
            "receiver_account" => $order['bank_number'],
            "receiver_name" => $order['true_name'],
            "phone_no" => strval($mobile),
            "email" => "abc@abc.com",
            "notify_url" => $this->config['notify_url'],
            "additional" => strval($order['user_id']),
        ];

        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["EkpayTransferParams", $params], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'json' => $params,
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
        Log::write(["EkpayFindParams", $params], 'transfer');
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
            Log::write(["EkpayTransferError", $result], 'transfer');
            throw new Exception(lang('error'));
        }

        Log::write(["EkpayTransferResponse", $result], 'transfer');

        if(isset($result['result_code']) && in_array($result['result_code'], ["200", "50004"])){
            return $type == 'pay' ? true : $result;
        }else{
            $msg = isset($result['result_message']) ? $result['result_message'] : "unkown error";
        }
        throw new Exception($msg);
    }

    public function notify() {
        $params = request()->param();
        Log::write(["EkpayTransferNotifyParams", $params], 'transfer');
        if (empty($params)) {
            return 'requestParams error';
        }
        $sign = $this->getSign(myArrayFilter($params));
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
        $find = $this->find($params["merchant_order"]);
        Log::write(["EkpayFindNotifyParams", $find], 'transfer');
        if ($find && in_array($find["status"], [4, 3])) {
            $remark = '';
            if (in_array($find['status'], [3])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = '提现转账失败';
            }
            $trade_no = $params['merchant_order'];
            $ret = $this->complete($params["merchant_order"], $status, $remark, $trade_no);
            if ($ret === true) {
                return 'SUCCESS';
            }
            return $ret;
        }
        return 'error';
    }

    private function getSign($params) {
        unset($params['sign']);
        unset($params['channel']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= "&secret=" . $this->config['app_key'];
        return strtoupper(md5($md5str));
    }

    private function getBankIfsc($code) {
        $extra = BankModel::where('bank_code', $code)->value('extra');
        return $extra ?? $code;
    }
}
