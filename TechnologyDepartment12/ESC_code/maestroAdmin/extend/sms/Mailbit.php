<?php

/**
 * Mailbit短信
 */
class Mailbit {
    private $config = [];
    public $error = '';
    const API_URL = "http://sms.mailbit.co.th/vendorsms/pushsms.aspx";
    const GJ_API_URL = "http://sms.mailbit.co.th/vendorsms/pushsms.aspx";

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
        if (empty($this->config['user']) || empty($this->config['password']) || empty($this->config['sid'])) {
            $this->error = "参数有误：配置信息有误";
            return false;
        }
        $content = "[{$this->config['sign']}]Your Verification Code is:{$code}"; //短信内容注意签名
        $params = [
            'user' => $this->config['user'],
            'password' => $this->config['password'],
            'sid' => $this->config['sid'],
            'msisdn' => str_replace("+", "", $mobile),
            'msg' => $content,
            'fl' => 0,
            'dc' => 8
        ];
        $post_data = http_build_query($params);
        try {
            $result = json_decode($this->post($post_data, self::GJ_API_URL), JSON_UNESCAPED_UNICODE);

        } catch (\Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
        if ($result['ErrorCode'] != '000') {
            $this->error = $result['ErrorMessage'];
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