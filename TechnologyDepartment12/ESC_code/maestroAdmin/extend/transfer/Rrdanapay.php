<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use app\common\model\UserModel;
use think\facade\Log;

class Rrdanapay extends Transferbase{
    public $url = "https://rrdana.com/api/smart/disburse";
    public $config = [];
    public function __construct($config) {
        if (empty($config['product_key']) || empty($config['secret_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
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

        Log::write(["RRDanaPayTransferParams", $data], 'transfer');
        $params = [
            'productKey' => $this->config['product_key'],
            'secretKey' => $this->config['secret_key'],
            'encrypted' => $this->enc($data, $this->config['secret_key'], $this->config['product_key'])
        ];
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
            Log::write($contents, "RRDanaPayTransferError");
            throw new Exception(lang('error'));
        }
        Log::write(["RRDanaPayTransferResponse", $result], 'transfer');
        if ( (isset($result['status']) && $result['status'] === 1)  || ($result['code'] == 422 && $result['message'] == 'transaction id已被使用')) {
            return true;
        } else {
            Log::write($result, "RRDanaPayTransferError");
            throw new Exception(isset($result['message']) ? $result['message'] : "unkown error");
        }
    }

    public function notify() {
        $notify_params = request()->param();
        Log::write(["RRDanaPayTransferNotifyParams", $notify_params], 'transfer');

        if (empty($notify_params) || !isset($notify_params['encrypted'])) {
            return 'requestParams error';
        }
        $params = $this->dec($notify_params['encrypted'], $this->config['secret_key'], $this->config['product_key']);
        if (!$params) {
            return 'sign error';
        }
        if (isset($params["client_trans_id"])) {
            Db::startTrans();
            try {
                $order = Db::name('withdrawals')->where('order_id', $params["client_trans_id"])->lock(true)->find();
                if (!empty($order) && $order['status'] == 4) {
                    $money = $order['money'] + $order['taxfee'];
                    $remark = '';
                    if ($params['status'] == "1") { //成功
                        Db::name('home_user')->where(['id' => $order['user_id']])->setInc('frozen_money', (-1 * $money));
                        $status = 2;
                    }else{ //失败
                        addMoneyLog($order['user_id'], $money, 8, '提现转账失败');
                        Db::name('home_user')->where(['id' => $order['user_id']])->setInc('frozen_money', (-1 * $money));
                        $status = 3;
                        $remark = '提现转账失败';
                    }
                    Db::name('withdrawals')->where(['id' => $order['id']])->update([
                        'status' => $status,
                        'check_time' => time(),
                        'remark' => $remark,
                        "trade_no" => $params['vendor_trans_id'],
                        "pay_time" => timeToStr(),
                    ]);
                }
                // 提交事务
                Db::commit();
            } catch (\think\Exception $exc) {
                Log::write($exc->getMessage() . "\n" . $exc->getTraceAsString(), "error");
                // 回滚事务
                Db::rollback();
                return 'fail';
            }
        }
        return 'success';
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
