<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Fmpay extends Paybase{
    public $url = "https://gateway.fhyl.ooo/payment/gateway";
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
            "partner_id" => $this->config['app_id'],
            "version" => 'V1.0',
            "pay_type" => isset($this->config['pay_type']) ? $this->config['pay_type'] : '0001',
            "bank_code" => isset($this->config['real_name']) ? $this->config['real_name'] : '',
            "order_no" => $order['order_id'],
            "amount" => sprintf("%.2f", $order['money']),
            //"pay_applydate" => date('Y-m-d H:i:s'),
            "notify_url" => $this->config['notify_url'],
        );
        if (isset($this->config['pay_receive_bankcode']) && $this->config['pay_receive_bankcode'] != '') {
            $params['summary'] = $this->config['pay_receive_bankcode'];
        }

        $params['sign'] = $this->getSign($params);
        if (isset($this->config['pay_attach'])) {
//            $params['attach'] = http_build_query($this->config['pay_attach']);
        }
//        $assign = ['params' => $params, "gateway" => $this->url];
//        return view(__DIR__ . "/wap.html", $assign);

//        dump($params);
        Log::write(["FmpayParams", $params], 'pay');
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
        Log::write(["FmpayResponse", $contents], 'pay');
        $result = json_decode($contents, true);
        //判断返回的是不是跳转链接，直接返回跳转JS
        if(!empty($contents) && substr($contents, 0 , 7) === '<script'){
            return $contents;
        }

        if (empty($result)) {
            Log::write(["FmpayError", $contents], 'pay');
            throw new Exception(lang($contents ? $contents : 'error'));
        }

        Log::write(["FmpayResponse", $result], 'pay');
        if(isset($result['vaNumber'])){
            return $result;
        }else{
            throw new Exception(isset($result['msg']) ? $result['msg'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write(["FmpayNotifyParams", $params], 'pay');
        $sign = $this->getSign($params);
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
        if ($params["code"] == "00") {
            $remark = '';
            if (in_array($params['code'], ['00'])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $params['trade_no'];
            $amount_true = isset($params['amount']) ? $params['amount']: null;
            $ret = $this->complete($params["order_no"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'ok';
            }
            return $ret;
        }
        return 'error';
    }

    private function getSign($params) {
        unset($params['sign']);
        unset($params['attach']);
        unset($params['pay_id']);
        ksort($params);
        $md5str = "";
        foreach ($params as $key => $val) {
            $val = trim($val);
            if ('sign' != $key && '' != $val) {
                $md5str = $md5str . $key . "=" . $val . "&";
            }
        }
        $md5str .= $this->config['app_key'];
//        Log::write("Fmpay presign string : ".$md5str, "debug");
        return strtolower(md5($md5str));
    }
}
