<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class Vn168pay extends Transferbase{
    public $url = "https://www.vn168pay.com/Payment_Dfpay_add.html";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        //扫码
        $params = array(
            "pay_memberid" => $this->config['app_id'],
            "pay_out_trade_no" => $order['order_id'],
            "pay_money" => sprintf("%.2f", $order['money']),
            "pay_bankname" => $order['bank_name'],
            "pay_accountname" => $order['true_name'],
            "pay_cardnumber" => $order['bank_number'],
            "pay_cardaccount" => $order['bank_number'],
            "pay_subbranch" => 'empty',
            "pay_province" => 'empty',
            "pay_city" => 'empty',
            "pay_notifyurl" => $this->config['notify_url'],
        );

        $params['pay_md5sign'] = $this->getSign($params);

//        dump($params);
        Log::write(["Vn168payTransferParams", $params], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
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

    public function validateResponse($response) {
        $contents = $response->getBody()->getContents();
        $result = json_decode($contents, true);
        if (empty($result)) {
            Log::write($contents, "Vn168payTransferError");
            throw new Exception(lang('error'));
        }
//        dump($result);die;
        Log::write(["Vn168payTransferResponse", $result], 'transfer');
        if($result['status'] === "success" || $result['msg'] == '存在重复订单号！'){
            return true;
        }else{
            throw new Exception(isset($result['msg']) ? $result['msg'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        unset($params['channel']);
        if (empty($params)) {
            return 'fail';
        }
        Log::write(["Vn168payTransferNotifyParams", $params], 'transfer');
        $sign = $this->getSign($params);
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
//        if ($params["returncode"] == "00") {
            Db::startTrans();
            try {
                $order = Db::name('withdrawals')->where('order_id', $params["orderid"])->lock(true)->find();
                if (!empty($order) && $order['status'] == 4) {
                    $money = $order['money'] + $order['taxfee'];
                    $remark = '';
                    if ($params['transaction_code'] == 1 && $params["returncode"] == "00") { //成功
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
//        }
        return 'OK';
    }

    private function getSign($params) {
        unset($params['sign']);
        unset($params['attach']);
        unset($params['channel']);
        ksort($params);
        $md5str = "";
        foreach ($params as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $this->config['app_key']));
        return $sign;
    }

}
