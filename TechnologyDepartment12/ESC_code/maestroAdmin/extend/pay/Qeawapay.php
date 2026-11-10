<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Qeawapay extends Paybase
{

    public $url = "https://pay.qeawapay.com/pay/web";
    public $config = [];

    public function __construct($config)
    {
        if (empty($config['recharge_merchant_key']) || empty($config['mch_id'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order)
    {
        $result = null;

        $merchant_key = $this->config['recharge_merchant_key'];//支付秘钥

        $version = '1.0'; // 需同步返回JSON 必填，固定值 1.0

        $mch_id = $this->config['mch_id'];

        $notify_url = $this->config['notify_url']; // 异步通知地址, 不超过 200 字节,

        $page_url = ''; // 支付成功后跳转地址, 不超过 200 字节,

        $mch_order_no = $order->order_id; // 商家订单号保证每笔订单唯一

        $pay_type = $this->config['pay_type']; // 支付类型

        $trade_amount = $order->money; // 当地货币 精确到元

        $order_date = date('Y-m-d H:i:s'); // 订单时间 时间格式(北京时间) yyyy-MM-dd HH:mm:ss

        $bank_code = ''; // 网银通道必填，其他类型一定不能填该参数

        // 商品名称 不超过 50 字节

        $sign_type = 'MD5'; // 固定值 MD5，不参与签名

        $mch_return_msg = ''; // 透传参数, 不超过200字节

        $signStr = "";

        if ($bank_code != "") {
            $signStr = $signStr."bank_code=".$bank_code."&";
        }
        $goods_name = $order->order_id; // 商品名称 不超过 50 字节
        $signStr = $signStr."goods_name=".$order->order_id."&";
        $signStr = $signStr."mch_id=".$mch_id."&";
        $signStr = $signStr."mch_order_no=".$mch_order_no."&";
        if ($mch_return_msg != "") {
            $signStr = $signStr."mch_return_msg=".$mch_return_msg."&";
        }
        $signStr = $signStr."notify_url=".$notify_url."&";
        $signStr = $signStr."order_date=".$order_date."&";
        if ($page_url != "") {
            $signStr = $signStr."page_url=".$page_url."&";
        }
        $signStr = $signStr."pay_type=".$pay_type."&";
        $signStr = $signStr."trade_amount=".$trade_amount."&";
        $signStr = $signStr."version=".$version;

        $sign = $this->sign($signStr, $merchant_key);

        $postdata = array(
            'goods_name'   => $goods_name,
            'mch_id'       => $mch_id,
            'mch_order_no' => $mch_order_no,
            'notify_url'   => $notify_url,
            'order_date'   => $order_date,
            'pay_type'     => $pay_type,
            'trade_amount' => $trade_amount,
            'version'      => $version,
            /** 下面这些参数有填写才需要提交，不填写的不需要提交也不需要参与签名 */
            /**'bank_code'=>$bank_code,
             * 'mch_return_msg'=>$mch_return_msg,
             * 'page_url'=>$page_url,*/
            'sign_type'    => $sign_type,
            'sign'         => $sign
        );


        Log::write(["QeawapayParams", $postdata], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'form_params' => $postdata
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
            Log::write(["QeawapayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["QeawapayResponse", $result], 'pay');

        if (is_array($result) && isset($result['respCode']) && $result['respCode'] === 'SUCCESS') {
            if (isset($result['payInfo']) && $result['payInfo']) {
                return $result['payInfo'];
            }
            return $result;
        } else {
            $message = $result['tradeMsg'] ?? 'unknown';
        }
        throw new Exception($message);
    }

    public function notify()
    {
        $request = request()->param();
        Log::write(["QeawapayNotifyParams", $request], 'pay');
        if (!isset($request['sign'])) {
            return 'requestParams error';
        }
        $merchant_key = $this->config['recharge_merchant_key'];

        $amount = $request['amount'];

        $mchId = $request["mchId"];

        $mchOrderNo = $request["mchOrderNo"];

        $merRetMsg = $request["merRetMsg"] ?? '';

        $orderDate = $request["orderDate"];

        $orderNo = $request["orderNo"];

        $oriAmount = $request["oriAmount"];

        $tradeResult = $request["tradeResult"];

        $signType = $request["signType"];

        $sign = $request["sign"];


        $signStr = "";
        $signStr = $signStr."amount=".$amount."&";
        $signStr = $signStr."mchId=".$mchId."&";
        $signStr = $signStr."mchOrderNo=".$mchOrderNo."&";
        if ($merRetMsg != "") {
            $signStr = $signStr."merRetMsg=".$merRetMsg."&";
        }
        $signStr = $signStr."orderDate=".$orderDate."&";
        $signStr = $signStr."orderNo=".$orderNo."&";
        $signStr = $signStr."oriAmount=".$oriAmount."&";
        $signStr = $signStr."tradeResult=".$tradeResult; // 1：支付成功 其他失败
        $flag = $this->validateSignByKey($signStr, $merchant_key, $sign);
        $status = $tradeResult;
        if ($flag) {
            $remark = '';
            //订单状态 1 待支付 2 通过 3 失效
            if ($status == 1) { //成功
                $status = 2;
            } else { //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $request['orderNo'];
            $amount_true = $amount;
            $ret = $this->complete($request["mchOrderNo"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'success';
            }
            return $ret;
        }
        return 'fail';
    }

    public static function http_post($url, $data)
    {
        $options = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => 'Content-type:application/x-www-form-urlencoded',
                'header'  => 'Content-Encoding : gzip',
                'content' => $data,
                'timeout' => 15 * 60
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }


    public static function http_post_res($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_USERAGENT,
            "Mozilla/5.0 (Windows NT 5.1; zh-CN) AppleWebKit/535.12 (KHTML, like Gecko) Chrome/22.0.1229.79 Safari/535.12");
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }


    public static function convToGBK($str)
    {
        if (mb_detect_encoding($str, "UTF-8, ISO-8859-1, GBK") != "UTF-8") {
            return iconv("utf-8", "gbk", $str);
        } else {
            return $str;
        }
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
}
