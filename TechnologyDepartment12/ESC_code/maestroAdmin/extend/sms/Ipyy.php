<?php

class Ipyy {

    private $config = [];
    public $error = '';

    const API_URL = "https://dx.ipyy.net/sms.aspx";
    const GJ_API_URL = "https://dx.ipyy.net/I18NSms.aspx";

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
            $mobile = implode(",", $mobile);
        }
        $str = "【{$this->config['sign']}】Your Verification Code is:{$code}"; //短信内容注意签名
        $content = strtoupper(bin2hex(iconv('utf-8', 'UCS-2BE', $str)));
        $params = [
            'action' => 'send',
            'userid' => '',
            'account' => $this->config['account'],
            'password' => $this->config['password'],
            'mobile' => $mobile,
            'code' => "8",
            'content' => $content,
        ];
        $post_data = http_build_query($params);
        try {
            $result = $this->post($post_data, self::GJ_API_URL);
        } catch (\Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
        if ($result['returnstatus'] == 'Faild') {
            $this->error = $result['message'];
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
        if (empty($this->config['account']) || empty($this->config['password'])) {
            $this->error = "参数有误：配置信息有误";
            return false;
        }
        if (is_array($mobile)) {
            $mobile = implode(",", $mobile);
        }
        $content = "【{$this->config['sign']}】您的验证码：{$code}。"; //短信内容注意签名
        $params = [
            'action' => 'send',
            'userid' => '',
            'account' => $this->config['account'],
            'password' => $this->config['password'],
            'mobile' => $mobile,
            'content' => $content,
        ];
        $post_data = http_build_query($params);
        try {
            $result = $this->post($post_data, self::API_URL);
        } catch (\Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
        if ($result['returnstatus'] == 'Faild') {
            $this->error = $result['message'];
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
        return $this->simplexml_obj2array($return_str);
    }

    // XML转换成数组
    private function simplexml_obj2array($obj) {
        if (is_object($obj)) {
            $result = array();
            foreach ((array) $obj as $key => $item) {
                $result[$key] = $this->simplexml_obj2array($item);
            }
            return $result;
        }
        return $obj;
    }
}
