<?php

namespace pay;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use think\Exception;
use think\facade\Log;

class Vortaqpay extends Paybase
{

    public $config = [];

    public function __construct($config)
    {
        if (empty($config['merchant_no']) || empty($config['app_id'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order)
    {
        $data = array(
            'country'              => 'BR',
            'currency'             => 'BRL',
            'payment_method_id'    => 'PIX',
            'payment_method_flow'  => 'REDIRECT',
            'order_id'             => $order->order_id,
            'amount'               => sprintf('%.2f', $order->money), // 固定2位小数点的浮点数金额，如：10.00。
            'notification_url'     => str_replace('\/', '/', $this->config['payin_notification_url']),
//            'success_redirect_url' => '',
            'timestamp'            => $this->getMillisecond(),
        );
        $data['signature'] = strtoupper($this->md5_sign($data, '&key='.$this->config['payin_secret']));
        $post_data = array(
            'merchant_no' => $this->config['merchant_no'],
            'data'        => $data,
        );
        $header = [
            'Content-Type' => 'application/json;charset=UTF-8',
            'ApiVersion'   => '1.0',
            'AppId'        => $this->config['app_id'],
            'Noncestr'     => mt_rand(1, 99999999),
            'Timestamp'    => $this->getMillisecond(),
        ];
        //请求头


        Log::write(["VortaQpayParams", $post_data], 'pay');
        Log::write(["VortaQpayHeader", $header], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->config['api_url'].'/api/pay/payment', [
                'json'    => $post_data,
                'headers' => $header
            ]);
        } catch (RequestException $ex) {
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
            Log::write(["VortaQpayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["VortaQpayResponse", $result], 'pay');

        if (is_array($result) && isset($result['state']) && $result['state'] === 'ok') {
            if (isset($result['data']) && $result['data']['redirect_url']) {
                return $result['data']['redirect_url'];
            }
            return $result;
        } else {
            $message = $result['errorMsg'] ?? 'unknown';
        }
        throw new Exception($message);
    }

    public function notify()
    {
        $request = request()->param();
        Log::write(["VortaQpayNotifyParams", $request], 'pay');
        if (!isset($request['signature'])) {
            return 'requestParams error';
        }

        $data = [
            'amount'       => $request['amount'],
            'currency'     => $request['currency'],
            'order_id'     => $request['order_id'],
            'payment_id'   => $request['payment_id'],
            'status'       => $request['status'],
            'status_code'  => $request['status_code'],
            'status_detail' => $request['status_detail'],
            'timestamp'    => $request['timestamp'],
        ];
        $signature = strtoupper($this->md5_sign($data, '&key='.$this->config['payin_secret']));
        $flag = $request['signature'] == $signature;
        $status = $data['status_code'];
        if ($flag) {
            $remark = '';
            //订单状态 1 待支付 2 通过 3 失效
            if ($status == 100) { //成功
                $status = 2;
            } else { //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $request['payment_id'];
            $amount_true = $request['amount'];
            $ret = $this->complete($request["order_id"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'success';
            }
            return $ret;
        }
        return 'fail';
    }


    /**
     * 获取utc时间戳
     */
    function getMillisecond()
    {
        $sysTimeZone = date_default_timezone_get(); //先取出系统所在服务器的时区
        date_default_timezone_set('UTC'); //设置时区为UTC时区
        list($s1, $s2) = explode(' ', microtime());
        $timestamp = (float) sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
        date_default_timezone_set($sysTimeZone); //设置时区为服务器时区
        return $timestamp;
    }

    /**
     * MD5 签名
     * @param  array  $data
     * @param  string  $key
     * @param  bool  $is_md5
     * @return string
     */
    function md5_sign($data, $key, $is_md5 = true)
    {
        ksort($data);
        $string = '';
        foreach ($data as $k => $vo) {
            if ($vo !== '') {
                $string .= $k.'='.$vo.'&';
            }
        }
        $string = rtrim($string, '&');
        $result = $string.$key;
        return $is_md5 ? md5($result) : $result;
    }
}
