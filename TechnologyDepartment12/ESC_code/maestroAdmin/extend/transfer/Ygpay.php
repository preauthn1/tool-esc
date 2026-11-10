<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use app\common\model\BankModel;
use app\common\model\UserModel;
use think\facade\Log;

class Ygpay extends Transferbase{
    public $url = "http://api.yigou.info/api/trans/create_order";
    public $get_order_url = "http://api.yigou.info/api/trans/query_order";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $mobile = UserModel::where('id', $order['user_id'])->value('mobile');
        $params = [
            "mchId" => $this->config['mch_id'],
            "appId" => $this->config['app_id'],
            "mchTransNo" => $order['order_id'],
            "amount" => intval($order['money']) * 100,
            "currency" => "INR",
            "notifyUrl" => $this->config['notify_url'],
            "bankCode" => !isset($order['bank_kh']) || empty($order['bank_kh']) ? $this->getBankIfsc($order['bank_name']) : $order['bank_kh'],
            "accountName" => $order['true_name'],
            "accountNo" => $order['bank_number'],
            "remarkInfo" => strval($order['user_id']),
        ];

        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["YgpayTransferParams", $params], 'transfer',);
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

    public function find($order_id, $transOrderId) {
        $params = array(
            'mchId' => $this->config['mch_id'],
            'transOrderId' => $transOrderId,
            'mchTransNo' => $order_id,
        );
        $params['sign'] = $this->getSign($params);
        Log::write(["YgpayFindParams", $params], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->get_order_url, [
                'json' => $params
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $response = $ex->getResponse();
        }
        return $this->validateResponse($response, 'find');
    }

    public function validateResponse($response, $type = 'pay') {
        $contents = $response->getBody()->getContents();
//        dump($contents);
        $result = json_decode($contents, true);
//        dump($result);die;
        if (empty($result)) {
            Log::write(["YgpayTransferError", $result], 'transfer');
            throw new Exception(lang('error'));
        }

        Log::write(["YgpayTransferResponse", $result], 'transfer');

        if(isset($result['retCode']) && (
                in_array($result['retCode'], ["SUCCESS"]) ||
                ($result['retCode'] == 'FAIL' && strexists($result['retMsg'], '商户转账订单号已存在'))
            )
        ){
            return $type == 'pay' ? true : $result;
        }else{
            $msg = isset($result['retMsg']) ? $result['retMsg'] : "unkown error";
        }
        throw new Exception($msg);
    }

    public function notify() {
        $params = request()->param();
        Log::write(["YgpayTransferNotifyParams", $params], 'transfer');
        if (empty($params)) {
            return 'requestParams error';
        }
        $sign = $this->getSign(myArrayFilter($params));
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
//        $find = $this->find($params["mchOrderNo"], $params["transOrderId"]);
//        Log::write(["YgpayFindNotifyParams", $find], 'transfer');
        if (in_array($params["status"], [2, 3])) {
            $remark = '';
            if (in_array($params['status'], [2])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = '提现转账失败';
            }
            $trade_no = $params['transOrderId'];
            $ret = $this->complete($params["mchOrderNo"], $status, $remark, $trade_no);
            if ($ret === true) {
                return 'success';
            }
            return $ret;
        }
        return 'error';
    }

    private function getSign($params) {
        unset($params['sign']);
        unset($params['channel']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= "&key=" . $this->config['app_key'];
        return strtoupper(md5($md5str));
    }

    private function getBankIfsc($code) {
        $extra = BankModel::where('bank_code', $code)->value('extra');
        return $extra ?? $code;
    }
}
