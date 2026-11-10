<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Gctpkpay extends Paybase
{

    public $url = "https://naskl.gctpk.com/payin/createOrder";
    public $config = [];

    public function __construct($config)
    {
        if (empty($config['mer_no']) && empty($config['sha256_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order)
    {
        //生成订单号
        $out_trade_no = $order->order_id;

        $notifyUrl = $this->config['payin_notify_url'];
        $data['merNo'] = $this->config['mer_no'];
        $data['merOrderNo'] = $out_trade_no;
        $data['name'] = 'test';
        $data['email'] = 'test@mail.com'; // 如没有可以固定上传


        $data['phone'] = "123456789";//限制只能为数字号码 纯数字,不需要添加区号
        $data['orderAmount'] = $order->money; // 金额,两位小数
        $data['currency'] = $this->config['coin'];
        $data['busiCode'] = $this->config['payin_busi_code'];##支付编码

        $data['notifyUrl'] = $notifyUrl;##回调地址
        $data['timestamp'] = $this->msectime();

        $data["sign"] = $this->makeSign($data, $this->config['sha256_key']);

//echo "<pre>";var_dump($data);

        Log::write(["GctpkpayParams", $data], 'pay');

        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'json'  => $data,
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
            Log::write(["GctpkpayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["GctpkpayResponse", $result], 'pay');
        $data = $result['data'];
        if (is_array($data) && isset($data['status']) && $data['status'] === 2) {
            //status 订单状态以此参数为准。2=待支付。3=失败(代收下单接口失败的订单没有回调)
            if (isset($data['orderData']) && $data['orderData']) {
                return $data['orderData'];
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
        Log::write(["GctpkpayNotifyParams", $request], 'pay');
        if (!isset($request['sign'])) {
            return 'requestParams error';
        }
        $data['e2eId'] = $request['e2eId'];
        $data['orderAmount'] = $request['orderAmount'];
        $data['payAmount'] = $request['payAmount'];
        $data['orderNo'] = $request['orderNo'];
        $data['merNo'] = $request['merNo'];
        $data['merOrderNo'] = $request['merOrderNo'];
        $data['payTime'] = $request['payTime'];
        $data['busiCode'] = $request['busiCode'];
        $data['status'] = $request['status'];
        $sign = $this->makeSign($data, $this->config['sha256_key']);
        $flag = $sign === $request['sign'];
        $status = $request['status']; // status=5表示支付成功,status=4表示订单已关闭
        if ($flag) {
            $remark = '';
            //订单状态 1 待支付 2 通过 3 失效
            if ($status == 5) { //成功
                $status = 2;
            } else { //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $request['orderNo'];
            $amount_true = $request['payAmount'];
            $ret = $this->complete($request["merOrderNo"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'SUCCESS';
            }
            return $ret;
        }
        return 'fail';
    }


    public static function sign($signSource, $key)
    {
        if (!empty($key)) {
            $signSource = $signSource."&key=".$key;
        }
        return md5($signSource);
    }


    public static function validateSignByKey($signSource, $key, $retsign)
    {
        if (!empty($key)) {
            $signSource = $signSource."&key=".$key;
        }
        $signkey = md5($signSource);
        if ($signkey == $retsign) {
            return true;
        }
        return false;
    }

    //生成签名
    public function makeSign($data, $key)
    {
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            if (!empty($v)) {
                $str .= (string) $k.'='.$v.'&';
            }
        }
        $str = rtrim($str, '&');
        //HamcSHA256加密
        $sign = hash_hmac("sha256", $str, $key);

        return $sign;

    }

//13位时间戳
    public function msectime()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float) sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return intval($msectime);
    }


}
