<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;
use think\helper\Str;

class Thaipay extends Paybase{

    public $url = "https://api.asiatopayment.com/%s/submitOrder";
    public $get_order_url = "https://api.asiatopayment.com/%s/order_query";
    public $config = [];

    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    private function getUri($pay_type, $type = 'pay') {
        $url = sprintf($type == 'pay' ? $this->url : $this->get_order_url, Str::studly(strtolower($pay_type)));
//        dump($url);
        return $url;
    }

    public function pay($order) {
        $countryCode = $this->getCurrencyCode();
        $params = [
            "appid" => $this->config['app_id'],
            "currency_code" => $countryCode,
            "trade_no" => $order['order_id'],
            "goods_name" => "charge",
            "money" => sprintf("%.2f", $order['money']),
            "isRepeat" => "0",
            "time" => strval(time()),
            "notify_url" => $this->config['notify_url'],
            "return_url" => getWebUrl(),
        ];
        $params['sign'] = $this->getSign($params);
        $params['sign_type'] = "MD5";
//        dump($params);
        Log::write([$params, "ThaipayParams"], 'pay');
//        return view(__DIR__ . '/wap.html', ['gateway' => $this->url, 'params' => $params]);
        try {
            $client = new Client();
            $response = $client->post($this->getUri($this->config['pay_type']), [
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

    public function find($trade_no, $orderid) {
        $countryCode = $this->getCurrencyCode();
        $params = array(
            'appid' => $this->config['app_id'],
            "currency_code" => $countryCode,
            'trade_no' => $trade_no,
            'orderid' => $orderid,
            "time" => strval(time()),
            'sign_type' => 'MD5',
        );
        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write([$params, "ThaipayFindParams"], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->getUri($this->config['pay_type'], 'find'), [
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
            Log::write([$contents, "ThaipayError"], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["ThaipayResponse---{$type}", $result], 'pay');
        if (isset($result['error']) && $result['error'] < 0) {
            return $type == 'pay' ? redirect($result['data']['pay_url']) : $result['data'];
        } else {
            throw new Exception(isset($result['msg']) ? $result['msg'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write([$params, "ThaipayNotifyParams"], 'pay');
        $sign = $this->getSign(myArrayFilter($params));
        if ($sign != $params["sign"]) {
            Log::write([$sign, "ThaipayNotifyParamsErrorSign"], 'pay');
            return 'sign error';
        }
        $this->config['pay_type'] = Db::name('paylist')->where('id', $params['pay_id'])->value('name');
        $find = $this->find($params["trade_no"], $params["orderid"]);
        if (in_array($find['pay_status'], [2, 3])) {
            $remark = '';
            if (in_array($find['pay_status'], [2])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = isset($params['msg']) ? $params['msg'] : 'Payment Fail';
            }
            $trade_no = $params['orderid'];
            $amount_true = null;
            $ret = $this->complete($params["trade_no"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'success';
            }
            return $ret;
        }
        return 'fail';
    }

    private function getSign($params) {
        unset($params['sign']);
        unset($params['sign_type']);
        unset($params['pay_id']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= $this->config['app_key'];
        return (md5($md5str));
    }

    private function getCurrencyCode() {
        $code = i18n_currency_code();
        $res = [
            'CNY' => 'CNY',
            'USDT' => 'USDT',
            'LAK' => 'LAK',
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
