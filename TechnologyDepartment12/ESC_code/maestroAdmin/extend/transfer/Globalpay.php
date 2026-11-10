<?php

namespace transfer;

use app\common\model\BankModel;
use app\common\model\UserModel;
use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class GlobalPay extends Transferbase{
    public $url = "http://njsyal.gdsua.com/withdraw/singleOrder";
    public $get_order_url = "http://njsyal.gdsua.com/withdraw/singleQuery";
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
        $countryCode = $this->getCountryCode();
        $ccy_no = $this->getCcyCode();
        $mobile = UserModel::where('id', $order['user_id'])->value('mobile');
        $params = [
            "mer_no" => $this->config['app_id'],
            "mer_order_no" => $order['order_id'],
            "acc_no" => $order['bank_number'],
            "acc_name" => $order['true_name'],
            "ccy_no" => $ccy_no,
            "order_amount" => strval(intval($order['money'])),
            "mobile_no" => strval($mobile),
            "customEmail" => strval($mobile) . "@abc.com",
            "bank_code" => $this->getBankCode($order['bank_name']),
            "notifyUrl" => $this->config['notify_url'],
            "summary" => "withdraw"
        ];
        if ($countryCode === '') {
            $params['bank_code'] = 'UPI';
            $params['province'] = !isset($order['bank_kh']) || empty($order['bank_kh']) ? $this->getBankIfsc($order['bank_name']) : $order['bank_kh'];
        }
        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["GlobalPayTransferParams", $params], 'transfer');
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

    public function find($order_id) {
        $params = array(
            'mer_no' => $this->config['app_id'],
            'mer_order_no' => $order_id,
            'request_no' => random(32),
            'request_time' => date("YmdHis"),
        );
        $params['sign'] = $this->getSign($params);
        Log::write(["GlobalPayFindParams", $params], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->get_order_url, [
                'json' => $params
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
            Log::write(["GlobalPayTransferError", $result], 'transfer');
            throw new Exception(lang('error'));
        }

        Log::write(["GlobalPayTransferResponse", $result], 'transfer');
        $msg = '';
        if ($type == 'find') {
            if(isset($result['query_status']) && $result['query_status'] == 'SUCCESS'){
                return $result;
            }else{
                $msg = isset($result['err_msg']) ? $result['err_msg'] : "unkown error";
            }
        }else{
            if(isset($result['status']) && $result['status'] == 'SUCCESS'){
                if (isset($result['mer_order_no']) && isset($result['order_no'])) {
                    Db::name('withdrawals')->where('order_id', $result['mer_order_no'])->update([
                        'trade_no' => $result['order_no']
                    ]);
                }
                return true;
            }else{
                $msg = isset($result['err_msg']) ? $result['err_msg'] : "unkown error";
            }

            if ($msg == 'order number already exists') {
                return true;
            }
        }
        throw new Exception($msg);
    }

    public function notify() {
        $params = request()->param();
        Log::write(["GlobalPayTransferNotifyParams", $params], 'transfer');
        if (empty($params)) {
            return 'requestParams error';
        }
        $sign = $this->getSign(myArrayFilter($params));
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
        if (!isset($params["mer_order_no"])) {
            return 'mer_order_no error';
        }
        $find = $this->find($params["mer_order_no"]);
        Log::write(["GlobalPayFindNotifyParams", $find], 'pay');
        if ($find && in_array($find["status"], ['FAIL', 'SUCCESS'])) {
            $remark = '';
            if (in_array($find['status'], ['SUCCESS'])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = '提现转账失败';
            }
            $trade_no = $params['order_no'];
            $ret = $this->complete($params["mer_order_no"], $status, $remark, $trade_no);
            if ($ret === true) {
                return 'SUCCESS';
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

    private function getBankCode($bank_code) {

        return $bank_code;
    }
    private function getCountryCode() {
        $code = i18n_currency_code();
        $res = [
            'CNY' => 'CNY',
            'RP' => 'IDN',
            'INR' => 'IND',
            'VND' => 'VNM',
            'THB' => 'THA',
            'RM' => 'MYS',
            'NGA' => 'NGA',
        ];

        return isset($res[$code]) ? $res[$code] : $code;
    }
    private function getCcyCode() {
        $code = i18n_currency_code();
        $res = [
            'CNY' => 'CNY',
            'RP' => 'IDR',
            'INR' => 'INR',
            'VND' => 'VND',
            'THB' => 'THB',
            'RM' => 'MYR',
            'NGA' => 'NGN',
        ];

        return isset($res[$code]) ? $res[$code] : $code;
    }
}
