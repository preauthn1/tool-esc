<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Rrdanapay extends Paybase{

    public $url = "https://rrdana.com/api/smart/va";
    public $config = [];

    public function __construct($config) {
        if (empty($config['product_key']) || empty($config['secret_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $data = [
            "transactionId" => $order['order_id'],
            "amount" => sprintf("%.2f", $order['money']),
            'payeeName' => isset($this->config['real_name']) ? $this->config['real_name'] : '',
            "payeeEmail" => "abc@abc.com",
            "payeePhone" => $this->config['mobile'],
            "payType" => $this->config['pay_type'],
            'description' => "user:{$order['user_id']}",
        ];
//        dump($this->config['notify_url']);
//        dump($data);
        $params = [
            'productKey' => $this->config['product_key'],
            'secretKey' => $this->config['secret_key'],
            'encrypted' => $this->enc($data, $this->config['secret_key'], $this->config['product_key'])
        ];
//        dump($params);
//        die;

        Log::write(["RRDanaPayParams", $params], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'form_params' => $params,
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
            Log::write(["RRDanaPayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }

        Log::write(["RRDanaPayResponse", $result], 'pay');
        if (isset($result['status']) && $result['status'] === 1) {
            $result['bank_name'] = $result['type'];
            $result['vaNumber'] = $result['va_code'];
            return $result;
        } else {
            throw new Exception(isset($result['description']) ? $result['description'] : "unkown error");
        }
    }

    public function notify() {
        $notify_params = request()->param();
        Log::write($notify_params, "RRDanaPayNotifyParams");

        if (empty($notify_params) || !isset($notify_params['encrypted'])) {
            return 'requestParams error';
        }
        $params = $this->dec($notify_params['encrypted'], $this->config['secret_key'], $this->config['product_key']);
        if (!$params) {
            return 'sign error';
        }
        if ($params["client_trans_id"]) {
            $remark = '';
            if (in_array($params['status'], [1])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $params['vendor_trans_id'];
            $amount_true = null;
            $ret = $this->complete($params["client_trans_id"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'success';
            }
            return $ret;
        }
        return 'fail';
    }

    private function enc($d, $sk, $pk) {
        $plaintext = base64_encode(json_encode($d));
        $cipher = "AES-128-CBC";
        $ciphertext_raw = openssl_encrypt($plaintext, $cipher, $sk, $options = OPENSSL_RAW_DATA, $pk);
        $hmac = hash_hmac('sha256', $ciphertext_raw, $sk, $as_binary = true);
        $ciphertext = base64_encode($pk . $hmac . $ciphertext_raw);
        return $ciphertext;
    }

    private function dec($e, $k, $iv) {
        try {
            $c = base64_decode($e);
            $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
            $iv2 = substr($c, 0, $ivlen);
            if ($iv2 == $iv) {
                $hmac = substr($c, $ivlen, $sha2len = 32);
                $ciphertext_raw = substr($c, $ivlen + $sha2len);
                $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $k, $options = OPENSSL_RAW_DATA, $iv);
                $calcmac = hash_hmac('sha256', $ciphertext_raw, $k, $as_binary = true);
                if (hash_equals($hmac, $calcmac)) {
                    return json_decode(base64_decode($original_plaintext), true);
                }
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

}
