<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Vn168pay extends Paybase{
    public $url = "https://www.vn168pay.com/Pay_Index.html";
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
            "pay_orderid" => $order['order_id'],
            "pay_amount" => sprintf("%.2f", $order['money']),
            "pay_applydate" => date('Y-m-d H:i:s'),
            "pay_bankcode" => isset($this->config['pay_type']) ? $this->config['pay_type'] : '',
            "pay_notifyurl" => $this->config['notify_url'],
        );

        $params['pay_md5sign'] = $this->getSign($params);
        $params['format'] = "json";
        $params['pay_productname'] = '充值';
        $params['pay_user_id'] = $order['user_id'];
        $params['pay_receive_bankcode'] = 'SACOMBANK';
        if ($this->config['pay_attach']) {
//            $params['pay_attach'] = http_build_query($this->config['pay_attach']);
        }
        if (isset($this->config['pay_receive_bankcode']) && $this->config['pay_receive_bankcode'] != '') {
            $params['pay_receive_bankcode'] = $this->config['pay_receive_bankcode'];
        }
//        dump($params);
        Log::write(["Vn168payParams", $params], 'pay');
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
        Log::write(["Vn168payError", $contents], 'pay');
            throw new Exception(lang('error'));
        }

//        dump($result);die;
        Log::write(["Vn168payResponse", $result], 'pay');
        if($result['status'] === "ok" && isset($result['data']['pay_url'])){
            return redirect($result['data']['pay_url']);
        }else{
            throw new Exception(isset($result['msg']) ? $result['msg'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write(["Vn168payNotifyParams", $params], 'pay');
        $sign = $this->getSign($params);
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
        if ($params["returncode"] == "00") {
            $remark = '';
            if ($params["returncode"] == "00") { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $params['transaction_id'];
            $amount_true = isset($params['paid_amount']) ? $params['paid_amount'] : null;
            $ret = $this->complete($params["orderid"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'OK';
            }
            return $ret;
        }
        return 'fail';
    }

    private function getSign($params) {
        unset($params['sign']);
        unset($params['attach']);
        unset($params['pay_id']);
        ksort($params);
        $md5str = "";
        foreach ($params as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $this->config['app_key']));
        return $sign;
    }

}
