<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Shanfupay extends Paybase{
    public $url = "https://shanfu.io/paytrust88/get_payment.php";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = array(
            "version" => 'v1.0',
            "app_id" => $this->config['app_id'],
            "price" => sprintf("%.2f", $order['money']),
            "pay_type" => 'bank',
            "currency" => i18n_currency_code(),
            "goods_name" => "GoodsName",
            "order_id" => $order['order_id'],
            "order_uid" => $order['user_id'],
            "notify_url" => $this->config['notify_url'],
            "return_url" => getWebUrl(),
            "fail_return_url" => getWebUrl(),
        );
//        dump($params);
        $params['signature'] = $this->getSign($params);
        Log::write(["ShanfupayParams", $params], 'pay');
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
            Log::write(["ShanfupayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["ShanfupayResponse", $result], 'pay');
        if(isset($result['status']) && $result['status'] == 1){
            return redirect($result['payment_url']);
        }else{
            throw new Exception(isset($result['msg']) ? $result['msg'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write(["shanfupayNotifyParams", $params], 'pay');
        $sign = $this->getSign(array_filter($params));
        if ($sign != $params["signature"]) {
            Log::write(["shanfupayNotifyParamsErrorSign", $sign], 'pay');
            return 'sign error';
        }
        if ($params["status"] == "1") {
            $remark = '';
            if (in_array($params['order_status'], [1])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = isset($params['status_message']) ? $params['status_message'] : 'Payment Fail';
            }
            $trade_no = $params['platform_order_id'];
            $amount_true = isset($params['pay_price']) ? $params['pay_price'] : null;
            $ret = $this->complete($params["order_id"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return '200';
            }
            return $ret;
        }
        return 'fail';
    }

    private function getSign($params) {
        unset($params['signature']);
        unset($params['pay_id']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= "&" . $this->config['app_key'];
        return strtoupper(md5($md5str));
    }
}
