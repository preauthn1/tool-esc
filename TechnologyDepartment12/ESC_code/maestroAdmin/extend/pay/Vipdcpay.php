<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Vipdcpay extends Paybase{
    public $defaultConfig = [
        'sandbox_merchant_id' => 'M1763138490',
        'sandbox_key' => '27cb5246da3e157b6b6e76821a60c300',
        'merchant_id' => 'M1763138490',
        'key' => '27cb5246da3e157b6b6e76821a60c300',
        'coin' => 'VIPDCPAY',
        'currency_code' => 'BRL',
        'pay' => '/pay/unifiedOrder',
        'repay' => '/repay/unifiedOrder',
        'pay_query' => '/pay/query',
        'repay_query' => '/repay/query',
        'balance' => '/query/balance',
        'domain' => 'https://api.vipdcpay.com',
        'sandbox_domain' => 'https://sandbox-api.vipdcpay.com',
        'pay_notify'=>"https://api.huotai10086.com/api.php/notify/VortaqpayRecharge",
        'repay_notify'=>"https://api.huotai10086.com/api.php/notify/VortaqpayRecharge",
        'pay_channel_code'=>'brlds',
        'repay_channel_code'=>'brldf'
    ];

    public $config = [];
    public function __construct($config) {
        if (empty($config['merchant_id']) || empty($config['key'])) {
            throw new Exception('config error');;
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = array(
            'merchant_id'        => env('VIPDCPAY_IS_SANDBOX',true)?$this->config['sandbox_merchant_id']:$this->config['merchant_id'],       //商户号
            'merchant_order_no' => $order['order_id'],                  //订单号
            'amount'       => sprintf("%.2f", $order['money']),  // 订单金额
            'currency'=>$this->config['currency_code'],                 // 币种
            'channel_code'=>$this->config['pay_channel_code'],          // 支付渠道编码（代收）
            'notify_url' => $this->config['pay_notify'],                // 回调地址
            'return_url'  => $this->success(lang('付款成功')),      // 回调地址
            'subject'      => "MAESTRO-用户充值",                         //商品描述
            'timestamp'     => time(),
            'sign_type'      => "md5",
        );
        Log::write(["VipdcpayParams", $params,$this->config], 'pay');
        $params['sign'] = $this->merchantSign($params, $this->config['key']);
//        dump($params);
        Log::write(["VipdcpayParams", $params], 'pay');
        try {
            $client = new Client();
            $url = env('VIPDCPAY_IS_SANDBOX',true)?$this->config['sandbox_domain']:$this->config['domain'] . $this->config['pay'];
            $response = $client->post($url, [
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

    /**
     * 订单查询
     * @param $order_id
     * @return mixed|\think\response\Redirect
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function find($order_id) {
        $params = array(
            'merchant_id' => env('VIPDCPAY_IS_SANDBOX', true)?$this->config['sandbox_merchant_id']:$this->config['merchant_id'],
            'merchant_order_no' => $order_id,
            'timestamp'=>time(),
            'query_time' => timeToStr(),
            'sign_type'      => "md5",
        );
        $params['sign'] = $this->merchantSign($params, $this->config['key']);
        Log::write(["VipdcpayParams", $params], 'pay');
        try {
            $client = new Client();
            $url = env('VIPDCPAY_IS_SANDBOX',true)?$this->config['sandbox_domain']:$this->config['domain'] . $this->config['pay_query'];
            $response = $client->post($url, [
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
        Log::write(["VipdcpayParams", $contents], 'pay');
        $result = json_decode($contents, true);
        if (empty($result)) {
            throw new Exception(lang('error'));
        }
        Log::write(["VipdcpayParams", $result], 'pay');
        if ($type == 'find') {
            if(isset($result['code']) && $result['code'] == 1){
                return $result;
            }
        }else{
            if (isset($result['code']) && $result['code'] == 1) {
                if (isset($result['data']) && $result['data']['pay_url']) {
                    return $result['data']['pay_url'];
                }
            }
        }
        throw new Exception(isset($result['msg']) ? $result['msg'] : "unkown error");
    }

    public function notify() {
        $params = request()->param();
        Log::write(["VipdcpayNotifyParams", $params], 'pay');
        $sign = $this->verifySign($params, $this->config['key']);
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
        $find = $this->find($params["merchant_order_no"]);
        Log::write(["EkpayFindNotifyParams", $find], 'pay');
        if ($find && in_array($params["status"], [0, 1, 2, -1, -2])) {
            $remark = '';
            if ($params['status'] == 2) { //成功
                $status = 2;
            }
            if ($params['status'] == -1){ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $params['order_no'];//系统订单号
            $amount_true = $params['amount'];//支付金额
            $ret = $this->complete($params["merchant_order_no"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'SUCCESS';
            }
            return $ret;
        }
        return 'fail';
    }

    // 验签
    function verifySign($params, $secret) {
        $sign = $params['sign'];
        unset($params['sign']);

        ksort($params);
        $string = '';
        foreach ($params as $key => $value) {
            $string .= $key . '=' . $value . '&';
        }
        $string .= 'key=' . $secret;

        return strtoupper(md5($string)) === $sign;
    }

    /**
    1. 移除 sign 字段（如果存在）
    2. 处理数组参数：将数组转换为 key=value&key=value 格式
    3. 将所有参数（除空值外）按参数名ASCII码升序排序
    4. 将排序后的参数按 key=value&key=value 格式拼接
    5. 在拼接字符串末尾加上 &key=商户密钥
    6. 对拼接后的字符串进行MD5加密，转为大写
     */
    function merchantSign($data, $key) {
        // 移除sign字段
        unset($data['sign']);

        // 处理数组参数
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                ksort($v);
                $arr = [];
                foreach ($v as $ak => $av) {
                    if ($av !== '' && $av !== null) $arr[] = "$ak=$av";
                }
                $data[$k] = implode('&', $arr);
            }
        }

        // 排序并过滤空值
        ksort($data);
        $data = array_filter($data, function($v) {
            return $v !== '' && $v !== null;
        });

        // 构建签名字符串
        $pairs = [];
        foreach ($data as $k => $v) $pairs[] = "$k=$v";
        $signStr = implode('&', $pairs) . "&key=$key";

        return strtoupper(md5($signStr));
    }
}
