<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Uupay extends Paybase
{

    public $config = [];

    public function __construct($config)
    {
        if (empty($config['merchant_no']) && empty($config['md5_key']) && empty($config['des_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order)
    {
        //生成订单号
        $out_trade_no = $order->order_id;

        //业务请求参数
        $params = [];
        $params['method'] = "payment.url.create";
        $params['amount'] = $order->money;
        $params['merTransNo'] = $order->order_id;
        $params['userPhone'] = "12345678";

//排序，组装加密参数
        $url_params = $this->PARAM_BUILD($params);
//out>> amount=50.00&method=payment.url.create&merTransNo=1668419591772&userPhone=734473732432
//AES加密
        $aes_str = openssl_encrypt($url_params, 'aes-256-ecb', $this->config['des_key'], 0);
//out>>> xFHk5DAt7RSZDAvO8wnQjhhFXLD/Imv1RJeg4KhK63YalWFupVQkENS80mdx4Vynk/4i15zYt505EtXiunLKyf5EoobPhSK0/2b8ZMLED7RL5cZHgzeEWuiTJ1Y6CZ3W

//MD5加密
        $sign = md5($aes_str . $this->config['md5_key']);
//out>>> c090cbf1a62656dc8246ce1eaff3e35a

//请求参数
        $requestParams = [];
        $requestParams['params'] = $aes_str;
        $requestParams['sign'] = $sign;
        $requestParams['merchantNo'] = $this->config['merchant_no'];
//out>>> {"params":"xFHk5DAt7RSZDAvO8wnQjhhFXLD/Imv1RJeg4KhK63YalWFupVQkENS80mdx4Vynk/4i15zYt505EtXiunLKyf5EoobPhSK0/2b8ZMLED7RL5cZHgzeEWuiTJ1Y6CZ3W","$sign":"c090cbf1a62656dc8246ce1eaff3e35a","merchantNo":"Test1111"}


        Log::write(["UupayParams", $params], 'pay');
        Log::write(["UupayParams", $requestParams], 'pay');

//        $ret = $this->globalpay_http_post_res_json($this->url, $data);
//        dump($ret);
        try {
            $client = new Client();
            $response = $client->post($this->config['api_url'], [
                'json'  => $requestParams,
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
            Log::write(["UupayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["UupayResponse", $result], 'pay');
        if (is_array($result) && $result['success'] === true) {
            //status 订单状态以此参数为准。2=待支付。3=失败(代收下单接口失败的订单没有回调)
            if (isset($result['result']) && $result['result']) {
                return $result['result'];
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
        Log::write(["UupayNotifyParams", $request], 'pay');
        if (empty($params) || !isset($request['callbackParams'])) {
            return 'requestParams error';
        }
        $des_key = $this->config['des_key'];//请在商户管理界面获取
//获取post body请求数据
//out>> {"callbackParams":"Efg0N3hDVxTkt6TfnzSjUIdfONPn4qbQnjzMjWXScAYkqZtT1N2kYYoybh5se+pBXuE2I4cSls6v/YM+To4icjuXuM9c3tQ7M4EoOgulLQL/eK6mojO80WDhzWZXvc+aPulixV910EvTahhv6ukiCJ3lp8X9bfLqInf6NBEZ3sr1v5uFVnJvKfhtEVxPDV6sN1cFDB9I751v4iaKxwUZTQ=="}
//加密请求字符串
        $callbackParams = $request['callbackParams'];
//out>> Efg0N3hDVxTkt6TfnzSjUIdfONPn4qbQnjzMjWXScAYkqZtT1N2kYYoybh5se+pBXuE2I4cSls6v/YM+To4icjuXuM9c3tQ7M4EoOgulLQL/eK6mojO80WDhzWZXvc+aPulixV910EvTahhv6ukiCJ3lp8X9bfLqInf6NBEZ3sr1v5uFVnJvKfhtEVxPDV6sN1cFDB9I751v4iaKxwUZTQ==
//解密
        $params = openssl_decrypt($callbackParams,'aes-256-ecb',$des_key,0);
        if (!$params) {
            return 'params error';
        }
//out>> {"merTransNo":"2022110408072837538","merUserNo":"2022-TOP13","paymentType":"STP","referenceId":"TI202211042192130000016","repayMoney":1000,"success":true}
        $params = json_decode($params, true);
        $status = $params['success']; // true 成功 false 失败
        $remark = '';
        //订单状态 1 待支付 2 通过 3 失效
        if ($status) { //成功
            $status = 2;
        } else { //失败
            $status = 3;
            $remark = 'Payment Fail';
        }
        $trade_no = $params['referenceId'];
        $amount_true = $params['repayMoney'];
        $ret = $this->complete($params["merTransNo"], $status, $remark, $trade_no, $amount_true);
        if ($ret === true) {
            return 'SUCCESS';
        }
        return $ret;
    }

    public function PARAM_BUILD($data){
        ksort($data);
        $url = "";
        foreach ($data as $key => $val) {
            if ($val != '') {
                if($url==""){
                    $url = $url . $key . "=" . $val;
                }else{
                    $url = $url . "&" . $key . "=" . $val;
                }
            }
        }
        return $url;
    }


}
