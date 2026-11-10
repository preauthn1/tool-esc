<?php

/**
 * 传信云短信
 */
class Chuanxin {
    private $config = [];
    public $error = '';
    const API_URL = "http://47.242.85.7:9090/sms/batch/v2";
    const GJ_API_URL = "http://47.242.85.7:9090/sms/batch/v2";

    /**
     * 接口网短信接口
     * @param array $config <ul>
     * <li>string $account account </li>
     * <li>string $password password </li>
     * </ul>
     */
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
        if (empty($this->config['appkey']) || empty($this->config['appcode']) || empty($this->config['appsecret'])) {
            $this->error = "参数有误：配置信息有误";
            return false;
        }
        if (is_array($mobile)) {
            if(count($mobile) > 1000){
                $this->error = "一次最多支持1000个号码";
                return false;
            }
            $mobile = str_split(',' ,$mobile);
        }
        $content = "[{$this->config['sign']}]Your Verification Code is:{$code}"; //短信内容注意签名
        $params = [
            'appkey' => $this->config['appkey'],
            'appcode' => $this->config['appcode'],
            'appsecret' => $this->config['appsecret'],
            'phone' => str_replace("+", "00", $mobile),
            'msg' => $content,
            'timestamp' => time() *1000,
        ];
        $post_data = http_build_query($params);
        try {
            $result = json_decode($this->post($post_data, self::GJ_API_URL), JSON_UNESCAPED_UNICODE);

        } catch (\Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
        if ($result['code'] != '00000') {
            $this->error = $result['desc'];
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
        if (empty($mobile)) {
            $this->error = "参数有误：手机号码不能为空";
            return false;
        }
        if (trim($code) == "") {
            $this->error = "参数有误：短信验证码不能为空";
            return false;
        }
        if (empty($this->config['appkey']) || empty($this->config['appcode']) || empty($this->config['appsecret'])) {
            $this->error = "参数有误：配置信息有误";
            return false;
        }
        if (is_array($mobile)) {
            if(count($mobile) > 1000){
                $this->error = "一次最多支持1000个号码";
                return false;
            }
            $mobile = str_split(',' ,$mobile);
        }
        $content = "【{$this->config['sign']}】您的验证码是:{$code}"; //短信内容注意签名
        $params = [
            'appkey' => $this->config['appkey'],
            'appcode' => $this->config['appcode'],
            'appsecret' => $this->config['appsecret'],
            'phone' => $mobile,
            'msg' => $content,
            'timestamp' =>  time() *1000,
        ];
        $post_data = http_build_query($params);
        try {
            $result = json_decode($this->post($post_data, self::API_URL), JSON_UNESCAPED_UNICODE);
        } catch (\Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
        if ($result['code'] != '00000') {
            $this->error = $result['desc'];
            return false;
        } else {
            return true;
        }
    }
    
    private function post($curlPost, $url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
        $return_str = curl_exec($curl);
        curl_close($curl);
        return $return_str;
    }

    private function get($curlGet, $url){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url . '?' . $curlGet);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $return_str = curl_exec($curl);
        curl_close($curl);
        return $return_str;
    }

    public function getError(){
        return $this->error;
    }
}