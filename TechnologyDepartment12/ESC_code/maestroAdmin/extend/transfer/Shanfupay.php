<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class Shanfupay extends Transferbase{
    public $url = "https://shanfu.io/paytrust88/apply_for_withdraw.php";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $currency = i18n_currency_code();
        $params = array(
            'version' => "v1.0",
            "app_id" => $this->config['app_id'],
            "order_uid" => $order['user_id'],
            "order_id" => $order['order_id'],
            "currency" => $currency,
            "amount" => sprintf("%.2f", $order['money']),
            "pay_type" => "bank",
            "bank_user_name" => $order['true_name'],
            "bank_account" => $order['bank_number'],
//            "bank_name" => $order['bank_name'],
            "bank_code" => $this->getBankCode($order['bank_name'], $currency),
//            "bank_code" => '59f413927793b',
//            "bank_branch" => 'empty',
//            "bank_address" => 'empty',
//            "bank_swift" => 'empty',
//            "bank_iban" => 'empty',
            "notify_url" => $this->config['notify_url'],
        );

        $params['signature'] = $this->getSign($params);
//        dump($params);
        Log::write(["ShanfupayTransferParams", $params], 'transfer');
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
            Log::write($contents, "ShanfupayTransferError");
            throw new Exception(lang('error'));
        }

        Log::write(["ShanfupayTransferResponse", $result], 'transfer');
        if(isset($result['status']) && (in_array($result['status'], [1]))){
            return true;
        }else{
            throw new Exception(isset($result['error']) ? $result['error'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write(["ShanfupayTransferNotifyParams", $params], 'transfer');
        if (empty($params)) {
            return 'requestParams error';
        }
        $sign = $this->getSign(array_filter($params));
        if ($sign != $params["signature"]) {
            return 'sign error===' . $sign;
        }
        if (isset($params["status"]) && $params["status"] == "1" && in_array($params['order_status'], [2,-1,-2])) {
            Db::startTrans();
            try {
                $order = Db::name('withdrawals')->where('order_id', $params["order_id"])->lock(true)->find();
                if (!empty($order) && $order['status'] == 4) {
                    $money = $order['money'] + $order['taxfee'];
                    $remark = '';
                    if ($params['order_status'] == "2") { //成功
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
                        "trade_no" => $params['platform_order_id'],
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
        return '200';
    }

    private function getSign($params) {
        unset($params['signature']);
        unset($params['pay_id']);
        unset($params['channel']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= "&" . $this->config['app_key'];
        return strtoupper(md5($md5str));
    }

    private function getBankCode($bank, $currency = '') {
        $bank_list = json_decode(file_get_contents('https://shanfu.io/paytrust88/bank_list.php'), true);
        $key = "shanfupay_bank";
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
