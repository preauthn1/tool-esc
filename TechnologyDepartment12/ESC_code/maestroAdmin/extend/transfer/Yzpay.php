<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use app\common\model\UserModel;
use think\facade\Log;

class Yzpay extends Transferbase{
    public $url = "https://yzpay.asia/api/smart/disburse";
//    public $url = "https://yzpay.asia/api/test/disburse";
    public $config = [];
    public function __construct($config) {
        if (empty($config['product_key']) || empty($config['secret_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        if (isset($order['trade_no']) && !empty($order['trade_no'])) {
            return true;
        }
        $user = UserModel::where('id', $order['user_id'])->find();
        $data = array(
            "transactionId" => $order['order_id'],
            "bankCode" => $order['bank_name'],
            "name" => $order['true_name'],
            "accountNumber" => $order['bank_number'],
            "phoneNumber" => $user['mobile'],
            "amount" => intval($order['money']),
            "payType" => $this->config['transfer_pay_type'],
            "callback_url" => $this->config['notify_url'],
        );
//        dump($data);
        Log::write(["YZPay1TransferParams", $data], 'transfer');
        $params = [
            'productKey' => $this->config['product_key'],
            'secretKey' => $this->config['secret_key'],
            'encrypted' => $this->enc($data, $this->config['secret_key'], $this->config['product_key'])
        ];

        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'json' => $params,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
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
//        echo($contents);
        $result = json_decode($contents, true);
//        dump($result);die;
        if (empty($result)) {
            Log::write(["YZPayTransferError", $contents], "transfer");
            throw new Exception(lang('error'));
        }
        Log::write(["YZPayTransferResponse", $result], "transfer");
        if ( (isset($result['status']) && $result['status'] === 1)  || ($result['code'] == 422 && strexists($result['message'], 'id已被使用'))) {
            if (isset($result['vendor_trans_id'])) {
                Db::name('withdrawals')->where('order_id', $result['client_trans_id'])->update([
                    'trade_no' => $result['vendor_trans_id']
                ]);
            }
            return true;
        } else {
            throw new Exception(isset($result['message']) ? $result['message'] : "unkown error");
        }
    }

    public function notify() {
        $notify_params = request()->param();
        Log::write(["YZPayTransferNotifyParams", $notify_params], "transfer");

        if (empty($notify_params) || !isset($notify_params['encrypted'])) {
            return 'requestParams error';
        }
        $params = $this->dec($notify_params['encrypted'], $this->config['secret_key'], $this->config['product_key']);
        if (!$params) {
            return 'sign error';
        }
        Log::write(["YZPayTransferNotifyParams2", $params], "transfer");
        if (isset($params["client_trans_id"])) {
            $remark = '';
            if ($params['status'] == "1") { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = '提现转账失败';
            }
            $trade_no = $params['vendor_trans_id'];
            $ret = $this->complete($params["client_trans_id"], $status, $remark, $trade_no);
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
