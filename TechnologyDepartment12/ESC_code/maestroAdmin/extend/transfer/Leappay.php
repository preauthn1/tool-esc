<?php

namespace transfer;

use app\common\model\UserModel;
use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class LeapPay extends Transferbase{
    public $url = "http://tshuadana.payto89.com:82/order/cashout";
    public $get_order_url = "http://tshuadana.payto89.com:82/order/cashout/query";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $user = UserModel::where('id', $order['user_id'])->find();
        $params = array(
            'type' => 3,
            "mch_id" => $this->config['app_id'],
            "order_sn" => $order['order_id'],
            "money" => sprintf("%.2f", $order['money']),
            'goods_desc' => "goods_desc",
            "client_ip" => isset($user['last_login_ip']) ? $user['last_login_ip'] : '1.1.1.1',
            "notify_url" => $this->config['notify_url'],
            "time" => time(),
//            "bank_type_name" => $order['bank_name'],
            "bank_name" => $order['true_name'],
            "bank_card" => $order['bank_number'],
            "ifsc" => !isset($order['bank_kh']) || empty($order['bank_kh']) ? $this->getBankIfsc($order['bank_name']) : $order['bank_kh'],
//            "paytm_account" => '',
//            "nation" => 'india',
            "bank_tel" => $user['mobile'],
            "bank_email" => $user['mobile'] . "@abc.com",
        );

        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["LeapPayTransferParams", $params], 'transfer');
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

    public function validateResponse($response, $type = 'pay') {
        $contents = $response->getBody()->getContents();
//        dump($contents);
        $result = json_decode($contents, true);
//        dump($result);die;
        if (empty($result)) {
            Log::write(["LeapPayTransferError", $result], 'transfer');
            throw new Exception(lang('error'));
        }

        Log::write(["LeapPayTransferResponse", $result], 'transfer');
        if(isset($result['code']) && in_array($result['code'], [1])){
            return $type == 'pay' ? true : $result['data'];
        }else{
            throw new Exception(isset($result['msg']) ? $result['msg'] : "unkown error");
        }
    }

    public function find($order_id) {
        $params = array(
            "mch_id" => $this->config['app_id'],
            "out_order_sn" => $order_id,
            'time' => time(),
        );
        $params['sign'] = $this->getSign($params);
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

    public function notify($order_id = null) {
        if (!is_null($order_id)) {
            $params = $this->find($order_id);
            if (!isArrayAndNotEmpty($params)) {
                return 'continue';
            }
            Log::write(["LeapPayTransferFindResult", $params], 'transfer');
            $params['pt_order'] = $params['orderSn'];
            $params['sh_order'] = $params['out_order_sn'];
            $params['state'] = $params['status'];
        }else{
            $params = request()->param();
            Log::write(["LeapPayTransferNotifyParams", $params], 'transfer');
            if (empty($params)) {
                return 'requestParams error';
            }
            $sign = $this->getSign(myArrayFilter($params));
            if ($sign != $params["sign"]) {
                return 'sign error';
            }
        }
        if (isset($params["state"]) && in_array($params["state"], [3,4])) {
            $remark = '';
            if ($params['state'] == 4) { //成功 //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = '提现转账失败';
            }
            $trade_no = $params['pt_order'];
            $ret = $this->complete($params["sh_order"], $status, $remark, $trade_no);
            if ($ret === true) {
                return 'success';
            }
            return is_null($order_id) ? $ret : 'continue';
        }
        return is_null($order_id) ? 'fail' : 'continue';
    }

    private function getSign($params) {
        unset($params['sign']);
        unset($params['channel']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= "&key=" . $this->config['app_key'];
        return md5($md5str);
    }
    private function getBankIfsc($code) {
        $_bank = [
            "SBIN" => "SBIN0000001",
            "ICIC" => "ICIC0000001",
            "DBSS" => "DBSS0IN0811",
        ];
        return isset($_bank[$code]) ? $_bank[$code] : $code;
    }
}
