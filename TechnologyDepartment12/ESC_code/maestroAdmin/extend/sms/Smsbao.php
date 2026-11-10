<?php

namespace sms;
/**
 * 短信宝
 */
class Smsbao
{
    private $config = [
        'account' => 'yutu6986',
        'password' => 'Aa123123',
        'sign' => '重庆渝途创网络科技'
    ];
    public $error = '';
    const API_URL = "http://api.smsbao.com/sms";
    const GJ_API_URL = "http://api.smsbao.com/wsms";

//    /**
//     * 接口网短信接口
//     * @param array $config <ul>
//     * <li>string $account account </li>
//     * <li>string $password password </li>
//     * </ul>
//     */
//    public function __construct(array $config) {
//        $this->config = $config;
//    }
//
    /**
     * 发送国际短信
     * @param string $mobile
     * @param string $code
     * @return boolean
     */
    public function sendGjSms($mobile, $code)
    {
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
        $content = "【{$this->config['sign']}】您的验证码是{$code}。如非本人操作，请忽略本短信"; //短信内容注意签名
//        $params = [
//            'u' => $this->config['account'],
//            'p' => md5($this->config['password']),
//            'm' => $mobile,
//            'c' => UrlEncode($content),
//        ];
        $sendurl = self::GJ_API_URL . "?u=" . $this->config['account'] . "&p=" . md5($this->config['password']) . "&m=" . $mobile . "&c=" . urlencode($content);
        try {
            $message = file_get_contents($sendurl);
        } catch (\Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
        if ($message === '0') {
            return true;
        } else {
            $this->error = $this->get_message($message);
            return false;
        }
    }

    /**
     * 发送普通短信
     * @param string $mobile
     * @param string $code
     * @return boolean
     */
    public function sendSms($mobile, $code)
    {
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
        $content = "【{$this->config['sign']}】您的验证码是{$code}。如非本人操作，请忽略本短信"; //短信内容注意签名
//        $params = [
//            'u' => $this->config['account'],
//            'p' => md5($this->config['password']),
//            'm' => $mobile,
//            'c' => UrlEncode($content),
//        ];
        $sendurl = self::API_URL . "?u=" . $this->config['account'] . "&p=" . md5($this->config['password']) . "&m=" . $mobile . "&c=" . urlencode($content);
        try {
            $message = file_get_contents($sendurl);
        } catch (\Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
        if ($message === '0') {
            return true;
        } else {
            $this->error = $this->get_message($message);
            return false;
        }
    }

    private function post($curlPost, $url)
    {
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


    private function get_message($code)
    {
        $msg = [
            "0" => "短信发送成功",
            "-1" => "参数不全",
            "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
            "30" => "密码错误",
            "40" => "账号不存在",
            "41" => "余额不足",
            "42" => "帐户已过期",
            "43" => "IP地址限制",
            "50" => "内容含有敏感词",
            "51" => "手机号码不正确"
        ];

        return isset($msg[$code]) ? $msg[$code] : "短信发送失败";
    }
}