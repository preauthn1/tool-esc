<?php

/**
 * 接口网
 */
class Jiekou {
    private $config = [];
    public $error = '';
    const API_URL = "http://sms.106jiekou.com/utf8/sms.aspx";
    const GJ_API_URL = "http://sms.106jiekou.com/utf8/worldapi.aspx";

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
        if (empty($this->config['account']) || empty($this->config['password'])) {
            $this->error = "参数有误：配置信息有误";
            return false;
        }
        if (is_array($mobile)) {
            $this->error = "接口网不支持批量发送短信";
            return false;
        }
        $post_data = "account={$this->config['account']}&password={$this->config['password']}&mobile={$mobile}&content=".rawurlencode("您的验证码是：{$code}。如需帮助请联系客服。");
        try {
            $message = $this->post($post_data, self::GJ_API_URL);
        } catch (\Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
        if ($message == 100) {
            return true;
        } else {
            $this->error = $this->get_message($message);
            return false;
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
        if (empty($this->config['account']) || empty($this->config['password'])) {
            $this->error = "参数有误：配置信息有误";
            return false;
        }
        if (is_array($mobile)) {
            $this->error = "接口网不支持批量发送短信";
            return false;
        }
        $post_data = "account={$this->config['account']}&password={$this->config['password']}&mobile={$mobile}&content=".rawurlencode("您的验证码是：{$code}。如需帮助请联系客服。");
        try {
            $message = $this->post($post_data, self::API_URL);
        } catch (\Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
        if ($message == 100) {
            return true;
        } else {
            $this->error = $this->get_message($message);
            return false;
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

    
    private function get_message( $code ) {
        $msg = [
            "100" => "发送成功",
            "101" => "验证失败",
            "102" => "手机号码格式不正确",
            "103" => "会员级别不够",
            "104" => "内容未审核",
            "105" => "内容过多",
            "106" => "账户余额不足",
            "107" => "Ip受限",
            "108" => "手机号码发送太频繁，请换号或隔天再发",
            "109" => "帐号被锁定",
            "110" => "手机号发送频率持续过高，黑名单屏蔽数日",
            "120" => "系统升级",
        ];
        
        return isset($msg[$code]) ? $msg[$code] : "短信发送失败";
    }
}