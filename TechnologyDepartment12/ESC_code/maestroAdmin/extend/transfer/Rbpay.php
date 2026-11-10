<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class Rbpay extends Transferbase{
    public $url = "https://api.rb131411.com/withdrawal/create";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = array(
            "appid" => $this->config['app_id'],
            "money" => intval($order['money']),
            "name" => $order['true_name'],
            "account" => $order['bank_number'],
            "callback" => $this->config['notify_url'],
            "bank_type" => 2,
            "bank_id" => $this->getBankId($order['bank_name']),
            "out_trade_no" => $order['order_id'],
        );
        $params['sign'] = $this->getSign($params);
//        dump($params);die;
        Log::write(["RbpayTransferParams", $params], 'transfer');
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
            Log::write($contents, "RbpayTransferError");
            throw new Exception(lang('error'));
        }

        Log::write(["RbpayTransferResponse", $result], 'transfer');
        if(isset($result['code']) && in_array($result['code'], [1, 200, 10007])){
            return true;
        }else{
            throw new Exception(isset($result['msg']) ? $result['msg'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write(["RbpayTransferNotifyParams", $params], 'transfer');
        if (empty($params) || !isset($params['data'])) {
            return 'requestParams error';
        }
        $data = $params['data'];
        $sign = $this->getSign($data);
        if ($sign != $data["sign"]) {
            return 'sign error';
        }
        if (isset($params["code"]) && in_array($params["code"], [0,1])) {
            Db::startTrans();
            try {
                $order = Db::name('withdrawals')->where('order_id', $data["out_trade_no"])->lock(true)->find();
                if (!empty($order) && $order['status'] == 4) {
                    $money = $order['money'] + $order['taxfee'];
                    $remark = '';
                    if ($params['code'] == 1) { //成功
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
                        "trade_no" => $data['order_no'],
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

    private function getSign($params) {
        unset($params['sign']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= "&key=" . $this->config['app_key'];
        return strtoupper(md5($md5str));
    }

    private function getBankId($bank) {
        $banks = [
            "TECHCOMBANK" => "1",
            "SACOMBANK" => "2",
            "VIETCOMBANK" => "3",
            "AGRIBANK" => "4",
            "BIDV" => "5",
            "EXIMBANK" => "6",
            "VIETINBANK" => "7",
            "MB" => "8",
            "SHB" => "9",
            "HDBANK" => "10",
            "VPBANK" => "11",
            "KIENLONGBANK" => "12",
            "ACB" => "13",
            "VIB" => "14",
            "DONGABANK" => "15",
            "BACABANK" => "16",
            "SCB" => "17",
            "VIETCAPITALBANK" => "18",
            "LienVietPostBank" => "19",
            "MaritimeBank" => "20",
            "OCB" => "21",
        ];
        return isset($banks[$bank]) ? $banks[$bank] : '-1';
    }
}
