<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Efupay extends Paybase{
    public $url = "http://efupays.com:8084/api/pay/V2";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = array(
            "version" => 'V2',
            "signType" => 'MD5',
            "merchantNo" => $this->config['app_id'],
            "date" => date("YmdHis"),
            "channleType" => $this->config['pay_type'],
            "noticeUrl" => $this->config['notify_url'],
            "orderNo" => $order['order_id'],
            "bizAmt" => sprintf("%.2f", $order['money']),
            "customNo" => $order['user_id'],
            "returnUrl" => getWebUrl(),
        );
//        dump($params);
        $params['sign'] = $this->getSign($params);
        Log::write(["EfupayParams", $params], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'json' => $params
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
            Log::write($contents, "EfupayError");
            throw new Exception(lang('error'));
        }
        Log::write(["EfupayResponse", $result], 'pay');
        if(isset($result['code']) && $result['code'] == 0){
            $detail = $result['detail'];
            if ($detail['PayURL']) {
                return redirect($detail['PayURL']);
            }else{
                return response($detail['PayHtml']);
            }
        }else{
            Log::write($result, "EfupayError");
            throw new Exception(isset($result['msg']) ? $result['msg'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write(["EfupayNotifyParams", $params], 'pay');
        $sign = $this->getSign(myArrayFilter($params));
        if ($sign != $params["sign"]) {
            Log::write(["EfupayNotifyParamsErrorSign", $sign], 'pay');
            return 'sign error';
        }
        if ($params["status"] != "0") {
            $remark = '';
            if (in_array($params['status'], [1])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $params['platform_order_id'];
            $amount_true = null;
            $ret = $this->complete($params["orderNo"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'SUCCESS';
            }
            return $ret;
        }
        return 'error';
    }

    private function getSign($params) {
        unset($params['sign']);
        unset($params['pay_id']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= $this->config['app_key'];
        return md5($md5str);
    }
}
