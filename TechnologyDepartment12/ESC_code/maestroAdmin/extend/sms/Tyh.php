<?php

use GuzzleHttp\Client;
class Tyh {

    private $config = [];
    public $error = '';

    const API_URL = "http://sms.skylinelabs.cc:20003/sendsmsV2";
    const GJ_API_URL = "http://sms.skylinelabs.cc:20003/sendsmsV2";

    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * 发送国际短信
     * @param string $mobile 
     * @param array $code
     * @return boolean
     */
    public function sendGjSms($mobile, $code) {
        if (empty($mobile)) {
            $this->error = "参数有误：手机号码不能为空";
            return false;
        }
        if (trim($code) == "") {
            $this->error = "参数有误：短信验证码不能为空";
            return false;
        }
        if (empty($this->config['account']) || empty($this->config['password'])) {
            $this->error = "参数有误：配置信息有误";
            return false;
        }
        if (is_array($mobile)) {
            $mobile = implode(",", $mobile);
        }
        $content = "Cam on Quy Khach ma cua ban la {$code}. Xin vui long khong chia se cho bat ki ai."; //短信内容注意签名
        $params = [
            'account' => $this->config['account'],
            'datetime' => date("YmdHis"),
            'numbers' => str_replace("+", "", $mobile),
            'content' => $content,
        ];
        $params['sign'] = md5($params['account'] . $this->config['password'] . $params['datetime']);
        try {
            $client = new Client();
            $response = $client->post(self::GJ_API_URL, [
                'json' => $params,
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
        if ($result['status'] < 0) {
            $this->error = $this->getError($result['status']);
            return false;
        } else {
            return true;
        }
    }

    /**
     * 发送普通短信
     * @param string $mobile 
     * @param array $code
     * @return boolean
     */
    public function sendSms($mobile, $code) {
        return $this->sendGjSms($mobile, $code);
    }
    
    private function getError($status) {
        $msg = [
            "-1" => "认证错误",
            "-2" => "Ip访问受限",
            "-3" => "短信内容含有敏感字符",
            "-4" => "短信内容为空",
            "-5" => "短信内容过长",
            "-6" => "不是模板的短信",
            "-7" => "号码个数过多",
            "-8" => "号码为空",
            "-9" => "号码异常",
            "-10" => "该通道余额不足，不能满足本次发送",
            "-11" => "定时时间格式不对",
            "-12" => "由于平台的原因，批量提交出错，请与管理员联系",
            "-13" => "用户被锁定"
        ];
        return isset($msg[$status]) ? $msg[$status] : 'unkown';
    }

}
