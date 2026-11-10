<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Paytrust88 extends Paybase{

    public $url = "https://api.paytrust88.com/v1/transaction/start";
    public $config = [];

    public function __construct($config) {
        if (empty($config['token'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = [
            'return_url' => getWebUrl(),
            'failed_return_url' => getWebUrl(),
            'http_post_url' => $this->config['notify_url'],
            'amount' => sprintf("%.2f", $order['money']),
//            'currency' => 'MYR',
            'currency' => i18n_currency_code(),
            'item_id' => $order['order_id'],
            'item_description' => "user:{$order['user_id']}",
            'name' => isset($this->config['real_name']) ? $this->config['real_name'] : ''
        ];

        Log::write(["Paytrust88Params", $params], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'form_params' => $params,
                'curl' => [CURLOPT_USERPWD => $this->config['token'] . ":"],
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
            Log::write(["Paytrust88Error", $contents], 'pay');
            throw new Exception(lang('error'));
        }

        Log::write(["Paytrust88Response", $result], 'pay');
        if (isset($result['status']) && $result['status'] === 0) {
            return redirect($result['redirect_to']);
        } else {
            throw new Exception(isset($result['status_message']) ? $result['status_message'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write(["Paytrust88NotifyParams", $params], 'pay');

        if (empty($params)) {
            return 'requestParams error';
        }
        $sign = $this->getSign($params);
        if ($sign != $params['signature2']) {
            return 'sign error';
        }
        if (in_array($params['status'], [1,-2,-1])) {
            $remark = '';
            if (in_array($params['status'], [1])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = isset($params['decline_reason']) ? $params['decline_reason'] : 'Payment Fail';
            }
            $trade_no = $params['transaction'];
            $amount_true = null;
            $ret = $this->complete($params["item_id"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'success';
            }
            return $ret;
        }
        return 'fail';
    }

    private function getSign($param) {
        $data = "{$param['transaction']}{$param['status']}{$param['currency']}{$param['amount']}{$param['created_at']}";
        return hash_hmac('sha256', $data, $this->config['token']);
    }

}
