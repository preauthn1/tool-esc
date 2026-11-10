<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class Idrpay extends Transferbase{
    public $url = "https://idrpays.com/api/withdraw/bankaccount";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = array(
            "merchant_id" => $this->config['app_id'],
            "business_email" => $this->config['api_user'],
            "order_id" => $order['order_id'],
            "bank_id" => $order['bank_name'],
            "withdraw_method_id" => 2,
            "bank_account_number" => $order['bank_number'],
            "withdraw_amount" => sprintf("%.2f", $order['money']),
            'currency' => 'IDR',
            "bank_account_holder_name" => $order['true_name'],
            "bank_province" => 'empty',
            "bank_city" => 'empty',
            "bank_branch" => 'empty',
            'note' => '',
            'website_url' => '',
            "request_time" => time(),
            "callback_noti_url" => $this->config['notify_url'],
        );

        $signature = $this->config['app_key'].$params['merchant_id'].$params['business_email'].$params['order_id'].$params['withdraw_method_id'].
                $params['bank_id']. $params['bank_account_number'].$params['withdraw_amount']. $params['currency'].
                $params['bank_account_holder_name']. $params['bank_province']. $params['bank_city'].
                $params['bank_branch']. $params['request_time']. $params['callback_noti_url'];
        $params['sign_data'] = strtoupper(hash('md5', $signature));
        Log::write(["IdrpayParams", $params], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'form_params' => ['requestParams' => json_encode($params)],
                'auth' => [$this->config['api_user'], $this->config['api_password']],
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
            Log::write($contents, "IdrpayError");
            throw new Exception(lang('error'));
        }

        Log::write(["IdrpayResponse", $result], 'transfer');
        if(isset($result['errCode']) && (in_array($result['errCode'], [0,8]))){
            return true;
        }else{
            throw new Exception(isset($result['error']) ? $result['error'] : "unkown error");
        }
    }

    public function notify() {
        $params = json_decode(request()->param('requestParams'), true);
        Log::write(["IdrpayNotifyParams", $params], 'transfer');
        if (empty($params)) {
            return 'requestParams error';
        }
        $sign = $this->getSign($params);
        if ($sign != $params["sign_data"]) {
            return 'sign error';
        }
        if (isset($params["order_status_code"]) && $params["order_status_code"] != "001") {
            Db::startTrans();
            try {
                $order = Db::name('withdrawals')->where('order_id', $params["order_id"])->lock(true)->find();
                if (!empty($order) && $order['status'] == 4) {
                    $money = $order['money'] + $order['taxfee'];
                    $remark = '';
                    if ($params['order_status_code'] == "000") { //成功
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
                        "trade_no" => $params['transaction_id'],
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
        return json_encode(['received' => 'Yes']);
    }

    private function getSign($params) {
        $signature = $this->config['app_key'] . $params["merchant_id"] . $params['order_id'] . $params['withdraw_method_id'] . $params['currency'];
        return strtoupper(hash('md5', $signature));;
    }
}
