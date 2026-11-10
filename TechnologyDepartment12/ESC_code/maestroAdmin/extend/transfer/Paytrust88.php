<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class Paytrust88 extends Transferbase{
    public $url = "https://api.paytrust88.com/v1/payout/start";
    public $config = [];
    public function __construct($config) {
        if (empty($config['token'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $currency = i18n_currency_code();
        $params = [
            'amount' => sprintf("%.2f", $order['money']),
            'currency' => $currency,
            "bank_code" => $this->getBankCode($order['bank_name'], $currency),
//            'currency' => 'MYR',
//            "bank_code" => '59f413927793b',
            "name" => $order['true_name'],
            "iban" => $order['bank_number'],
            'http_post_url' => $this->config['notify_url'],
            'amount' => sprintf("%.2f", $order['money']),
            'item_id' => $order['order_id'],
            'item_description' => "user:{$order['user_id']}",
        ];
//        dump($params);
        Log::write(["Paytrust88Params", $params], "transfer");
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'form_params' => $params,
                'curl' => [CURLOPT_USERPWD => $this->config['token'] . ":"],
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
            Log::write($contents, "Paytrust88TransferError");
            throw new Exception(lang('error'));
        }

        Log::write(["Paytrust88TransferResponse", $result], "transfer");
        if(isset($result['status']) && in_array($result['status'], [0])){
            return true;
        }else{
            throw new Exception(isset($result['error_desc']) ? $result['error_desc'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write(["Paytrust88NotifyParams", $params], "transfer");

        if (empty($params)) {
            return 'requestParams error';
        }
        $sign = $this->getSign($params);
        if ($sign != $params['signature']) {
            return 'sign error';
        }
        if (in_array($params['status'], [-1, 2, -2, -3])) {
            Db::startTrans();
            try {
                $order = Db::name('withdrawals')->where('order_id', $params["item_id"])->lock(true)->find();
                if (!empty($order) && $order['status'] == 4) {
                    $money = $order['money'] + $order['taxfee'];
                    $remark = '';
                    if ($params['status'] == 2) { //成功
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
                        "trade_no" => $params['payout'],
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
            return 'success';
        }
        return 'fail';
    }

    private function getSign($param) {
        $data = "{$param['payout']}{$param['amount']}{$param['currency']}{$param['status']}{$param['created_at']}";
        return hash_hmac('sha256', $data, $this->config['token']);
    }


    private function getBankCode($bank, $currency = '') {
        $bank_list = json_decode(file_get_contents('https://api.paytrust88.com/v1/bank'), true);
        $key = "paytrus88_bank";
        if (!empty($bank_list)) {
            cache($key, $bank_list);
        }else{
            $bank_list = cache($key);
        }

        $list = [];
        foreach ($bank_list as $item) {
            $list[$item['currency']][strtoupper($item['name'])] = $item;
        }
        if (isset($list[$currency]) && isset($list[$currency][strtoupper($bank)])) {
            return $list[$currency][strtoupper($bank)]['bank_code'];
        }

        return $bank;
    }
}
