<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class Everpay extends Transferbase{
    public $url = "https://openapi.ever-pay.net/gateway/cash";
    public $get_order_url = "https://openapi.ever-pay.net/gateway/query";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['public_key']) || empty($config['private_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        if (isset($order['trade_no']) && !empty($order['trade_no'])) {
            return true;
        }
        $params = [
            "merchantCode" => $this->config['app_id'],
            "orderNum" => $order['order_id'],
            "money" => strval(intval($order['money'])),
            'feeType' => 1,
            'dateTime' => date("YmdHis"),
            "name" => $order['true_name'],
            "number" => $order['bank_number'],
            "bankCode" => $this->getBankCode($order['bank_name']),
            "notifyUrl" => $this->config['notify_url'],
            "description" => 'withdraw'
        ];

        $params['sign'] = $this->getSign($params);
//        dump($params);die;
        Log::write(["EverPayTransferParams", $params], 'transfer');
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
            'merchantCode' => $this->config['app_id'],
            'queryType' => 'CASH_QUERY',
            'orderNum' => $order_id,
            'dateTime' => date('YmdHis')
        );
        $params['sign'] = $this->getSign($params);
        Log::write(["EverPayFindParams", $params], 'transfer');
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
            Log::write(["EverPayTransferError", $result], 'transfer');
            throw new Exception(lang('error'));
        }

        Log::write(["EverPayTransferResponse", $result], 'transfer');
        if($result['platRespCode'] === 'SUCCESS'){
            if ($type == 'pay') {
                if (isset($result['platOrderNum']) && isset($result['orderNum'])) {
                    Db::name('withdrawals')->where('order_id', $result['orderNum'])->update([
                        'trade_no' => $result['platOrderNum']
                    ]);
                }
                return true;
            }else{
                return $result;
            }
        }else{
            $msg = isset($result['platRespMessage']) ? $result['platRespMessage'] : "unkown error";
            if ($msg == 'the orderNum already exists') {
                return true;
            }
        }
        throw new Exception($msg);
    }

    public function notify() {
        $params = request()->param();
        if (!isset($params['platSign'])) {
            return 'sign empty';
        }
        Log::write(["EverPayTransferNotifyParams", $params], 'transfer');
        $decryptSign = $this->public_key_decrypt($params['platSign'], $this->config['public_key']);
        $params_str = $this->getParams($params);
        if ($decryptSign != $params_str) {
            return 'sign error:' . json_encode([$decryptSign, $params_str]) ;
        }
        if (isset($params["status"]) && in_array($params["status"], [2, 4])) {
            $find = $this->find($params["orderNum"]);
            Log::write(["EverPayFindNotifyParams", $find], 'pay');
            $remark = '';
            if (in_array($find['status'], [2])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = isset($find['msg']) ? $find['msg'] : '提现转账失败';
            }
            $trade_no = null;
            $ret = $this->complete($params["orderNum"], $status, $remark, $trade_no);
            if ($ret === true) {
                return 'SUCCESS';
            }
            return $ret;
        }
        return 'error';
    }

    private function getSign($params) {
        return $this->pivate_key_encrypt($this->getParams($params), $this->config['private_key']);
    }

    private function getParams($params) {
        unset($params['sign']);
        unset($params['platSign']);
        unset($params['channel']);
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

    private function getBankCode($code) {
        return $code;
    }
}
