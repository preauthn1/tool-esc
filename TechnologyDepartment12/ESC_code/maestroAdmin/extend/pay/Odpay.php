<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Odpay extends Paybase
{
    public $config = [];

    public function __construct($config)
    {
        if (empty($config['merchantId']) || empty($config['key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order)
    {
        $params = [
            'merchantId' => $this->config['merchantId'],
            'country'    => 2, // 默认填2，巴西
            'orderNo'    => $order->order_id,
            'amount'     => sprintf("%.2f", $order['money']),
            'notifyUrl'  => $this->config['payin_notification_url'],
        ];

        $params['sign'] = $this->getSignature($this->ascii_params($params), $this->config['key']);

        Log::write(["OdpayParams", $params], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->config['api_url'].'/payment/createOrder', [
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

    public function validateResponse($response)
    {
        $contents = $response->getBody()->getContents();
        $result = json_decode($contents, true);
        if (empty($result)) {
            Log::write(["OdpayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["OdpayResponse", $result], 'pay');

        if (is_array($result) && isset($result['code']) && $result['code'] == 200) {
            if (isset($result['data']) && $result['data']) {
                return $result['data']['jumpUrl'];
            }
            return $result;
        } else {
            $message = $result['msg'] ?? 'unknown';
        }
        throw new Exception($message);
    }

    public function notify()
    {
        $request = request()->param();
        Log::write(["OdpayNotifyParams", $request], 'pay');
        if (!isset($request['sign'])) {
            return 'requestParams error';
        }
        $data = [
            'merchantId' => $request['merchantId'],
            'orderNo'    => $request['orderNo'],
            'payNo'      => $request['payNo'],
            'amount'     => $request['amount'],
            'amountReal' => $request['amountReal'],
            'status'     => $request['status'],
        ];
        $sign = $this->getSignature($this->ascii_params($data), $this->config['key']);
        $flag = $sign == $request['sign'];
        $status = $request['status'];
        if ($flag) {
            $remark = '';
            //订单状态 1 待支付 2 通过 3 失效
            if ($status == 2) { //成功
                $status = 2;
            } else { //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $request['payNo'];
            $amount_true = $request['amountReal'];
            $ret = $this->complete($request["orderNo"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'success';
            }
            return $ret;
        }
        return 'fail';
    }

    /**
     * 自定义ascii排序 返回字符串
     * @param  array  $params
     * @return string
     */
    function ascii_params($params = array())
    {
        if (!empty($params)) {
            $p = ksort($params);
            if ($p) {
                $str = '';
                foreach ($params as $k => $val) {
                    $str .= $k.'='.$val.'&';
                }
                $strs = rtrim($str, '&');
                return $strs;
            }
        }
        return '参数错误';
    }

    /**
     * @brief 使用HMAC-SHA1算法生成oauth_signature签名值
     *
     * @param  $str   string 源串
     * @param $keystring 密钥
     *
     * @return string
     */
    function getSignature($str, $key)
    {
        $signature = "";
        if (function_exists('hash_hmac')) {
            $signature = base64_encode(hash_hmac("sha1", $str, $key, true));
        } else {
            $blocksize = 64;
            $hashfunc = 'sha1';
            if (strlen($key) > $blocksize) {
                $key = pack('H*', $hashfunc($key));
            }
            $key = str_pad($key, $blocksize, chr(0x00));
            $ipad = str_repeat(chr(0x36), $blocksize);
            $opad = str_repeat(chr(0x5c), $blocksize);
            $hmac = pack(
                'H*', $hashfunc(
                    ($key ^ $opad).pack(
                        'H*', $hashfunc(
                            ($key ^ $ipad).$str
                        )
                    )
                )
            );
            $signature = base64_encode($hmac);
        }
//    echo($signature);
        return $signature;
    }
}
