<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Shpay extends Paybase
{

    public $url = "https://transapi.shpays.com/v1/trans/payIn";
    public $config = [];

    public function __construct($config)
    {
        if (empty($config['mchtId']) || empty($config['appId'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order)
    {
        $params = [
            'mchtId'      => $this->config['mchtId'],
            'appId'       => $this->config['appId'],
            'countryCode' => 'BR',
            'requestTime' => date('Y-m-d H:i:s'),
            'notifyUrl'   => $this->config['notification_url'],
            'signType'    => 'MD5',
            'transAmt'    => sprintf("%.2f", $order['money']),
            'outTradeNo'  => $order->order_id,
            'subject'     => 'Recharge '.$order['money'].' BRL',
            'mobile'      => '123456789'
        ];

        $params['sign'] = $this->getSign($params);

        Log::write(["ShpayParams", $params], 'pay');
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

    public function validateResponse($response)
    {
        $contents = $response->getBody()->getContents();
        $result = json_decode($contents, true);
        if (empty($result)) {
            Log::write(["ShpayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["ShpayResponse", $result], 'pay');

        if (is_array($result) && isset($result['success']) && $result['success'] === true) {
            if (isset($result['result']) && $result['result']) {
                return $result['result']['link'];
            }
            return $result;
        } else {
            $message = $result['message'] ?? 'unknown';
        }
        throw new Exception($message);
    }

    public function notify()
    {
        $request = request()->param();
        Log::write(["ShpayNotifyParams", $request], 'pay');
        if (!isset($request['sign'])) {
            return 'requestParams error';
        }
        $data = [
            'event'          => $request['event'],
            'outTradeNo'     => $request['outTradeNo'],
            'transNo'        => $request['transNo'],
            'transStatus'    => $request['transStatus'],
            'transAmt'       => $request['transAmt'],
            'completionTime' => $request['completionTime'],
        ];
        $sign = $this->getSign($data);
        $flag = $sign == $request['sign'];
        $status = $request['transStatus'];
        if ($flag) {
            $remark = '';
            //订单状态 1 待支付 2 通过 3 失效
            if ($status == 'SUCCESS') { //成功
                $status = 2;
            } else { //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $request['transNo'];
            $amount_true = $request['transAmt'];
            $ret = $this->complete($request["outTradeNo"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'success';
            }
            return $ret;
        }
        return 'fail';
    }

    private function getSign($params)
    {
        ksort($params);
        $md5str = "";
        foreach ($params as $key => $val) {
            $md5str = $md5str.$key."=".$val."&";
        }
        $md5str = trim($md5str, '&');
        $sign = strtoupper(md5($md5str.$this->config['key']));
        return $sign;
    }
}
