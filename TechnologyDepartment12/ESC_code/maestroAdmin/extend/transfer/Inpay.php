<?php

namespace transfer;

use app\common\model\BankModel;
use app\common\model\UserModel;
use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class Inpay extends Transferbase{
    public $url = "https://www.inpays.in/openApi/payout/createOrder";
    public $get_order_url = "https://www.inpays.in/openApi/payout/queryOrder";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        if (isset($order['trade_no']) && !empty($order['trade_no'])) {
            return true;
        }
        $mobile = UserModel::where('id', $order['user_id'])->value('mobile');
        $params = [
            "merchant" => $this->config['app_id'],
            "orderId" => $order['order_id'],
            "amount" => strval(intval($order['money'])),
            "customName" => $order['true_name'],
            "customMobile" => strval($mobile),
            "customEmail" => strval($mobile) . "@abc.com",
            "bankAccount" => $order['bank_number'],
            "ifscCode" => !isset($order['bank_kh']) || empty($order['bank_kh']) ? $this->getBankIfsc($order['bank_name']) : $order['bank_kh'],
            "notifyUrl" => $this->config['notify_url'],
        ];

        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["InPayTransferParams", $params], 'transfer');
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

    public function find($order_id) {
        $params = array(
            'merchant' => $this->config['app_id'],
            'orderId' => $order_id,
        );
        $params['sign'] = $this->getSign($params);
        Log::write(["InPayFindParams", $params], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->get_order_url, [
                'form_params' => $params
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response, 'find');
    }

    public function validateResponse($response, $type = 'pay') {
        $contents = $response->getBody()->getContents();
//        dump($contents);
        $result = json_decode($contents, true);
//        dump($result);die;
        if (empty($result)) {
            Log::write(["InPayTransferError", $result], 'transfer');
            throw new Exception(lang('error'));
        }

        Log::write(["InPayTransferResponse", $result], 'transfer');
        if ($type == 'find') {
            if(isset($result['success']) && $result['success']){
                return $result['data'];
            }else{
                $msg = isset($result['errorMessages']) ? $result['errorMessages'] : "unkown error";
            }
        }else{
            if(isset($result['success']) && $result['success']){
                if (isset($result['data']['orderId']) && isset($result['data']['platOrderId'])) {
                    Db::name('withdrawals')->where('order_id', $result['data']['orderId'])->update([
                        'trade_no' => $result['data']['platOrderId']
                    ]);
                }
                return true;
            }else{
                $msg = isset($result['errorMessages']) ? $result['errorMessages'] : "unkown error";
            }
        }
        throw new Exception($msg);
    }

    public function notify() {
        $params = request()->param();
        Log::write(["InPayTransferNotifyParams", $params], 'transfer');
        if (empty($params)) {
            return 'requestParams error';
        }
        $sign = $this->getSign(myArrayFilter($params));
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
        if (!isset($params["orderId"])) {
            return 'orderId error';
        }
        $find = $this->find($params["orderId"]);
        Log::write(["InPayFindNotifyParams", $find], 'transfer');
        if ($find && in_array($find["status"], ['PAY_FAIL', 'PAY_SUCCESS'])) {
            $remark = '';
            if (in_array($find['status'], ['PAY_SUCCESS'])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = '提现转账失败';
            }
            $trade_no = null;
            $ret = $this->complete($params["orderId"], $status, $remark, $trade_no);
            if ($ret === true) {
                return 'ok';
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
        return strtolower(md5($md5str));
    }

    private function getBankIfsc($code) {
        $extra = BankModel::where('bank_code', $code)->value('extra');
        return $extra ?? $code;
    }
}
