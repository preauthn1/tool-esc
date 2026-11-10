<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Lang;
use think\facade\Log;

class Paytend extends Paybase{
    public $url = "https://pos.paytend.com/a/qpay_webpayment";
    public $get_order_url = "https://pos.paytend.com/a/qpay_orderquery";
//    public $url = "http://218.97.161.247:8055/a/qpay_webpayment";
//    public $get_order_url = "http://218.97.161.247:8055/a/qpay_orderquery";
    public $config = [];
    public function __construct($config) {
        if (empty($config['AGENT_ID']) ||
            empty($config['MERCHANT_ID']) ||
            empty($config['PAYTEND_RSA_KEY_PUBLIC']) ||
            empty($config['priKey']) ||
            empty($config['pubKey'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = array(
            "merchantId" => $this->config['MERCHANT_ID'],
            'currency' => i18n_currency_code(),
            "amount" => strval(intval($order['money'] * 100)), //单位：分
            "billNo" => $order['order_id'],
            "bankCode" => $this->config['pay_type'],
            "language" => $this->getLang(),
            "returnUrl" => getWebUrl(),
            "noticeUrl" => $this->config['notify_url'],
        );
        Log::write(["PaytendPayParams", $params], 'pay');
        $form_params = $this->get_form_params($params);
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'form_params' => $form_params
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response);
    }

    public function find($order_id) {
        $params = array(
            "merchantId" => $this->config['MERCHANT_ID'],
            "billNo" => $order_id,
        );
//        dump($params);
        try {
            $client = new Client();
            $response = $client->post($this->get_order_url, [
                'form_params' => $this->get_form_params($params)
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response, 'find');
    }

    private function get_form_params($params) {
        $_public_params = [
            'requestId' => random(32), //请求流水号
            'agentId' => $this->config['AGENT_ID'], //机构分配的合作方编号
            'version' => '2.0', //固定2.0
//            'randomKey' => 'A1B1C1D1F1', //Aes Key密文（RSA公钥加密）
            'bizData' => stripslashes(json_encode($params)),  //请求AES加密数据
//            'signType' => 'RSA',  //签名方式，固定RSA
//            'signature' => '', //RSA私钥计算签名
        ];
        $randomKey = random(16);
//        dump($_public_params);

        $encryptAesKey = $this->encryptRsa($randomKey, $this->getPaytendPublicKey());
        $bizDataAes = $this->bizDataAes($_public_params['bizData'], $randomKey);
        $_public_params['randomKey'] = $randomKey;
        ksort($_public_params);
        $query_params = urldecode(http_build_query($_public_params));
//        dump($query_params);
        $signature = $this->getSign($query_params, $this->getPrivateKey());
        if(!$this->verifySign($query_params, $signature, $this->getPublicKey())){
            Log::write([$query_params, $signature], "PaytendPayError");
            throw new Exception(lang('error'));
        }

        $_public_params['randomKey'] = $encryptAesKey;
        $_public_params['bizData'] = $bizDataAes;
        $_public_params['signType'] = 'RSA';
        $_public_params['signature'] = $signature;
        return $_public_params;
    }

    public function validateResponse($response, $type = 'pay') {
        if (is_null($response)) {
            throw new Exception('请求失败');
        }
        $contents = $response->getBody()->getContents();
//        dump($contents);
        $result = json_decode($contents, true);
//        dump($result);die;
        if (empty($result)) {
            Log::write(["PaytendPayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }

        Log::write(["PaytendPayResponse", $result], 'pay');
        if (isset($result['respCode']) && $result['respCode'] === '00') {
            $bizData = $result['bizData'];
            return $type == 'pay' ? redirect($bizData['payUrl']) : $bizData;
        }else{
            throw new Exception(isset($result['respMsg']) ? $result['respMsg'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        unset($params['pay_id']);
        Log::write(["PaytendPayNotifyParams", $params], 'pay');
        if (empty($params)) {
            return json(['returnCode' => 'fail', 'returnMsg' => '参数为空']);
        }
        $params2 = $params;
        unset($params2['signType']);
        unset($params2['signature']);
        ksort($params2);
        $query_params = urldecode(http_build_query($params2));
        $pubKey = $this->getPaytendPublicKey();
        $signature = urldecode($params['signature']);
        $verify = $this->verifySign2($query_params, $signature, $pubKey);
        if(!$verify){
            $signature = $params['signature'];
            $verify = $this->verifySign2($query_params, $signature, $pubKey);
        }
        if(!$verify){
            return json(['returnCode' => 'fail', 'returnMsg' => 'sign error']);
        }
        $bizData = json_decode($params['bizData'], true);
//        dump($bizData);
        if (isset($bizData["tradeState"]) && isset($bizData["billNo"])) {
            $find = $this->find($bizData["billNo"]);
            Log::write(["PaytendPaFind", $find], 'pay');
            if (empty($find)) {
                return json(['returnCode' => 'fail', 'returnMsg' => '请求查询接口失败']);
            }
            $remark = '';
            if (in_array($find['tradeState'], [1])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = isset($find['tradeStateMsg']) ? $find['tradeStateMsg'] : 'Payment Fail';
            }
            $trade_no = $find['payTendOrderId'];
            $amount_true = null;
            $ret = $this->complete($find["billNo"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return json(['returnCode' => 'SUCCESS', 'returnMsg' => '']);
            }
            return json(['returnCode' => 'fail', 'returnMsg' => $ret]);
        }
        return json(['returnCode' => 'fail', 'returnMsg' => '参数不正确']);
    }

    private function getPaytendPublicKey() {
        return $this->encode_public_key($this->config['PAYTEND_RSA_KEY_PUBLIC']);
    }

    private function getPublicKey() {
        $publicKey = "-----BEGIN PUBLIC KEY-----\n".
            wordwrap($this->config['pubKey'], 64, "\n", true).
            "\n-----END PUBLIC KEY-----";
        return $publicKey;
    }

    private function getPrivateKey() {
        $privateKey = "REDACTED";
        return $privateKey;
    }

    /**
     * $key 为公钥字符串
     */
    private function encode_public_key($key) {
        $pay_public_key = "-----BEGIN PUBLIC KEY-----\r\n";
        foreach (str_split($key, 64) as $str) {
            $pay_public_key = $pay_public_key . $str . "\r\n";
        }
        $pay_public_key = $pay_public_key . "-----END PUBLIC KEY-----";
        $pu_key = openssl_pkey_get_public($pay_public_key);
        if ($pu_key == false) {
            throw new Exception("打开公钥出错");
        }
        return $pu_key;
    }

    /**
     * $data为待加密字符串；
     * $key 为公钥字符串
     */
    private function encode_private_key($key) {
        $pay_private_key = "REDACTED";
        $pr_key = openssl_pkey_get_private($pay_private_key);
        if ($pr_key == false) {
            throw new Exception("打开私钥出错");
        }
        return $pr_key;
    }

    /**
     * 加密$bizData
     * @param $bizData
     * @param $aesKey
     * @return string
     */
    private function bizDataAes($bizData, $aesKey) {
        return $this->encryptAes($bizData, $aesKey);
    }

    private function getBankCode($code) {
        $_bank = [
            "BCA.ID" => "BCA",
            "BNI.ID" => "BNI",
            "MDR.ID" => "MDR",
            "BRI.ID" => "BRI",
            "CIMBN.ID" => "CIMBN",
        ];
        return isset($_bank[$code]) ? $_bank[$code] : '-1';
    }

    /**
     * AES加密 AES/ECB/PKCS5Padding
     * @param $data
     * @param $aesKey
     * @return string
     */
    public function encryptAes($data, $aesKey) {
        return base64_encode(openssl_encrypt($data, 'AES-128-ECB', $aesKey, OPENSSL_RAW_DATA, ''));
    }


    public function encryptRsa($data, $rsaPubKey) {
        $pubKey = openssl_pkey_get_public($rsaPubKey);
        $encrypted = '';
        if (openssl_public_encrypt($data, $encrypted, $pubKey)) {
            return base64_encode($encrypted);
        }
        openssl_free_key($pubKey);
        return false;
    }

    public function decryptRsa($data, $rsaKey) {
        //
    }

    //生成签名
    private function getSign($query_params, $priKey) {
        return $this->getPrivateSign($query_params, $priKey);
    }

    //验证签名
    private function verifySign($sign_str, $sign, $pubKey){
        return $this->verifyPublic($sign_str, $sign, $pubKey);
    }

    /**
     * @param $data 待验签数据
     * @param $sign 签名字符串
     * @param $publicKey 公钥
     * @return bool
     */
    function verifySign2($data, $sign, $publicKey){
        $algo = "SHA256";
        $sign = base64_decode($sign);
        $result = openssl_verify($data,$sign,$publicKey,$algo);
        openssl_free_key($publicKey);
        return (bool)$result;
    }

    //生成签名
    public function getPrivateSign($sign_str, $private_key) {
        $private_key = openssl_pkey_get_private($private_key);//加载密钥
        $signature = '';
        openssl_sign($sign_str, $signature, $private_key, OPENSSL_ALGO_SHA256);//生成签名
        $signature = base64_encode($signature);
        openssl_free_key($private_key);
        return $signature;
    }

    //验证签名
    public function verifyPublic($sign_str, $sign, $public_key) {
        $public_key = openssl_get_publickey($public_key);
        $verify = openssl_verify($sign_str, base64_decode($sign), $public_key, OPENSSL_ALGO_SHA256);
        openssl_free_key($public_key);
        return $verify == 1;//false or true
    }

    private function getLang() {
        $langs = [
            'zh-cn' => 'zh-cn', //中国
            'en' => 'en-us',
            'vn' => 'vi-vn', //越南
            'idn' => 'id-id', //印尼
            'thb' => 'th', //泰国
        ];
        $range = Lang::getLangSet();
        if (!$range || !isset($langs[$range])) {
            $range = 'zh-cn';
        }
        return $langs[$range];
    }
}
