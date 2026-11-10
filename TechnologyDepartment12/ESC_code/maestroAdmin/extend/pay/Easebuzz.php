<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Easebuzz extends Paybase{

    public $url = "https://testpay.easebuzz.in/";
    public $config = [];

    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
        $this->url = $this->_getURL($config['env']);
    }

    public function pay($order) {
        $surl =  getWebUrl() . '/payed?' . http_build_query(['type' => 'success', 'message' => 'payment success']);
        $furl = getWebUrl() . '/payed?' . http_build_query(['type' => 'error', 'message' => 'payment failed']);
        $params = [
            "key" => $this->config['app_id'],
            "txnid" => $order['order_id'],
            "amount" => sprintf("%.2f", $order['money']),
            "productinfo" => "Laptop",
            'firstname' => isset($this->config['real_name']) ? $this->config['real_name'] : 'firstname',
            'email' => 'email@abc.com',
            "phone" => $this->config['mobile'],
            "surl" => $surl,
            "furl" => $furl,
            'udf1' => $this->config['identifier'],
            'udf2' => "{$order['user_id']}",
        ];

        $params['hash'] = $this->_getHashKey($params);
//        dump($this->config['notify_url']);
//        dump($params);
        $url = $this->url . "payment/initiateLink";
        Log::write(["EasebuzzPayParams", $params], 'pay');
        try {
            $client = new Client();
            $response = $client->post($url, [
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
//        dump($contents);
        $result = json_decode($contents, true);
//        dump($result);die;
        if (empty($result)) {
            Log::write(["EasebuzzPayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }

        Log::write(["EasebuzzPayResponse", $result], 'pay');
        if (isset($result['status']) && $result['status'] === 1) {
            $url = $this->url . "pay/{$result['data']}";
            return redirect($url);
        } else {
            $msg = "unkown error";
            if (isset($result['data'])) {
                $msg = $result['data'];
            }elseif(isset ($result['error_desc'])) {
                $msg = $result['error_desc'];
            }
            throw new Exception($msg);
        }
    }

    public function notify() {
        $params = request()->param();
        unset($params['pay_id']);
        if (empty($params)) {
            return 'params error';
        }
        Log::write(["EasebuzzPayNotifyParams", $params], 'pay');
        $sign = $this->_getReverseHashKey($params);
        if ($sign != $params["hash"]) {
            return 'sign error';
        }
        if (isset($params["txnid"])) {
            $remark = '';
            if (in_array($params['status'], ['success'])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $params['easepayid'];
            $amount_true = null;
            $ret = $this->complete($params["txnid"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'success';
            }
            abort(501, $ret);
        }
    }

    private function _getReverseHashKey($response_array){
        $reverse_hash_sequence = "udf10|udf9|udf8|udf7|udf6|udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key";

        // make an array or split into array base on pipe sign.
        $reverse_hash = "";
        $reverse_hash_sequence_array = explode('|', $reverse_hash_sequence);
        $reverse_hash .= $this->config['app_key'] . '|' . $response_array['status'];

        // prepare a string based on reverse hash sequence from the $response_array array.
        foreach ($reverse_hash_sequence_array as $value) {
            $reverse_hash .= '|';
            $reverse_hash .= isset($response_array[$value]) ? $response_array[$value] : '';
        }

        // generate reverse hash key using hash function(predefine) and return
        return strtolower(hash('sha512', $reverse_hash));
    }
    private function _getHashKey($posted){
        $hash_sequence = "key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10";

        // make an array or split into array base on pipe sign.
        $hash_sequence_array = explode('|', $hash_sequence);
        $hash = null;

        // prepare a string based on hash sequence from the $params array.
        foreach ($hash_sequence_array as $value) {
            $hash .= isset($posted[$value]) ? $posted[$value] : '';
            $hash .= '|';
        }

        $hash .= $this->config['app_key'];
        #echo $hash;
        #echo " ";
        #echo strtolower(hash('sha512', $hash));
        // generate hash key using hash function(predefine) and return
        return strtolower(hash('sha512', $hash));
    }
    private function _getURL($env) {
        $url_link = '';
        switch ($env) {
            case 'test':
                $url_link = "https://testpay.easebuzz.in/";
                break;
            case 'prod':
                $url_link = 'https://pay.easebuzz.in/';
                break;
            default:
                $url_link = "https://testpay.easebuzz.in/";
        }
        return $url_link;
    }
}
