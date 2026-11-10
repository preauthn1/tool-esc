<?php

use GuzzleHttp\Client;

class Plant {

    private $config = [];
    public $error = '';

    const API_URL = "http://api.wftqm.com/api/sms/mtsend";
    const GJ_API_URL = "http://api.wftqm.com/api/sms/mtsend";

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
        if (empty($this->config['appkey']) || empty($this->config['secretkey'])) {
            $this->error = "参数有误：配置信息有误";
            return false;
        }
        if (is_array($mobile)) {
            $mobile = implode(",", $mobile);
        }
        $content = "Your Verification Code is:{$code}"; //短信内容注意签名
        $params = [
            'appkey' => $this->config['appkey'],
            'secretkey' => $this->config['secretkey'],
            'phone' => str_replace("+", "", $mobile),
            'content' => $content,
        ];
//        $params['sign'] = md5($params['account'] . $this->config['password'] . $params['datetime']);
        try {
            $client = new Client();
            $response = $client->post(self::GJ_API_URL, [
                'form_params' => $params,
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
        if ($result['code'] > 0) {
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
            "1" => "应用不可用或key错误",
            "2" => "参数错误或为空",
            "3" => "余额不足",
            "4" => "内容为空或包含非法关键词",
            "5" => "内容过长",
            "6" => "号码有误",
            "7" => "群发号码数量不得超过50000个",
            "8" => "sourceaddress必须为3-10位数字或英文字母",
            "9" => "IP非法",
            "88" => "请求失败",
            "99" => "系统错误",
        ];
        return isset($msg[$status]) ? $msg[$status] : 'unkown';
    }

}
