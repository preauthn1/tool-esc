<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class Efupay extends Transferbase{
    public $url = "http://efupays.com:8084/api/defray/V2";
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
            'version' => "V2",
            'signType' => "MD5",
            "merchantNo" => $this->config['app_id'],
            "date" => date("YmdHis"),
            "orderNo" => $order['order_id'],
            "channleType" => 0,
            "bizAmt" => sprintf("%.2f", $order['money']),
            "accName" => $order['true_name'],
            "bankCode" => $order['bank_name'],
            "bankBranchName" => $order['bank_name'],
            "cardNo" => $order['bank_number'],
            "openProvince" => 'empty',
            "openCity" => 'empty',
            "noticeUrl" => $this->config['notify_url'],
        );

        $params['sign'] = $this->getSign($params);
        Log::write(["EfupayTransferParams", $params], 'transfer');
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

    public function validateResponse($response) {
        $contents = $response->getBody()->getContents();
//        dump($contents);
        $result = json_decode($contents, true);
//        dump($result);die;
        if (empty($result)) {
            Log::write(["EfupayTransferError", $result], 'transfer');
            throw new Exception(lang('error'));
        }

        Log::write(["EfupayTransferResponse", $result], 'transfer');
        if(isset($result['code']) && (in_array($result['code'], [0])) || $result['msg'] == '订单重复'){
            return true;
        }else{
            throw new Exception(isset($result['error']) ? $result['error'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write(["EfupayTransferNotifyParams", $params], 'transfer');
        if (empty($params)) {
            return 'requestParams error';
        }
        $sign = $this->getSign(myArrayFilter($params));
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
        if (isset($params["status"]) && $params["status"] != "0") {
            Db::startTrans();
            try {
                $order = Db::name('withdrawals')->where('order_id', $params["orderNo"])->lock(true)->find();
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
                        "trade_no" => $params['orderNo'],
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
        return 'SUCCESS';
    }

    private function getSign($params) {
        unset($params['sign']);
        unset($params['pay_id']);
        unset($params['channel']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= $this->config['app_key'];
        return md5($md5str);
    }
}
