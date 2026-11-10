<?php

class Chuany {

    private $config = [];
    public $error = '';

    const API_URL = "http://39.97.4.102:9090/sms/distinct/v1";
    const GJ_API_URL = "http://39.97.4.102:9090/sms/distinct/v1";

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
        if (empty($this->config['app_key']) || empty($this->config['app_secret']) || empty($this->config['app_code'])) {
            $this->error = "参数有误：配置信息有误";
            return false;
        }
        if (is_array($mobile)) {
            $this->error = "只支持单个手机号码";
            return false;
        }
        $content = "【{$this->config['sign']}】Your Verification Code is:{$code}"; //短信内容注意签名
        $params = [
            'appkey' => $this->config['app_key'],
            'appcode' => $this->config['app_code'],
            'timestamp' => time() * 1000,
            'sms' => [
                [
                    'phone' => str_replace("+", "00", $mobile),
                    'msg' => $content,
                    'extend' => ''
                ]
            ],
        ];
        $params['sign'] = md5($this->config['app_key'] . $this->config['app_secret'] . $params['timestamp']);
        try {
            $result = $this->post($params, self::GJ_API_URL);
        } catch (\Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
        if (empty($result) || $result['code'] != '00000') {
            $this->error = isset($result['desc']) ? $result['desc'] : 'unkown';
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

    private function post($curlPost, $url) {
        $data_string = json_encode($curlPost);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string)
                ]
        );
        $return_str = curl_exec($ch);
        curl_close($ch);
        return json_decode($return_str, true);
    }

}
