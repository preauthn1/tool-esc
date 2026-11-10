<?php

namespace util;

use think\facade\Env;

class BtApi {

    private $BT_KEY = '';  //接口密钥
    private $BT_PANEL = "";    //面板地址
    private $BT_SITE_ID = "";    //站点ID
    private $user;
    private $password;

    //如果希望多台面板，可以在实例化对象时，将面板地址与密钥传入
    public function __construct() {
        $this->BT_PANEL = Env::get('BT_PANEL');
        $this->BT_KEY = Env::get('BT_KEY');
        $this->BT_SITE_ID = Env::get('BT_SITE_ID');
        $this->BT_WEB_NAME = Env::get('BT_WEB_NAME');
        $this->user = Env::get('BT_AUTH_USER');
        $this->password = Env::get('BT_AUTH_PWD');
    }

    public function addDomain($domain) {
        if (empty($this->BT_KEY) || empty($this->BT_PANEL) || empty($this->BT_SITE_ID)) {
            return false;
        }
        //拼接URL地址
        $url = $this->BT_PANEL . '/site?action=AddDomain';

        //准备POST数据
        $p_data = $this->GetKeyData();  //取签名
        $p_data['id'] = $this->BT_SITE_ID;
        $p_data['webname'] = $this->BT_WEB_NAME;
        $p_data['domain'] = $domain;

        //请求面板接口
        $result = $this->HttpPostCookie($url, $p_data);

        //解析JSON数据
        $data = json_decode($result, true);
        return $data;
    }

    //示例取面板日志
    public function getSites() {
        //拼接URL地址
        $url = $this->BT_PANEL . '/data?action=getData&table=sites';
        //准备POST数据
        $p_data = $this->GetKeyData();  //取签名
//        $p_data['list'] = 15;
        //请求面板接口
        $result = $this->HttpPostCookie($url, $p_data);
        //解析JSON数据
        $data = json_decode($result, true);
        return $data;
    }

    /**
     * 构造带有签名的关联数组
     */
    private function GetKeyData() {
        $now_time = time();
        $p_data = array(
            'request_token' => md5($now_time . '' . md5($this->BT_KEY)),
            'request_time' => $now_time
        );
        return $p_data;
    }

    /**
     * 发起POST请求
     * @param String $url 目标网填，带http://
     * @param Array|String $data 欲提交的数据
     * @return string
     */
    private function HttpPostCookie($url, $data, $timeout = 60) {
        //定义cookie保存位置
        $cookie_file = './' . md5($this->BT_PANEL) . '.cookie';
        if (!file_exists($cookie_file)) {
            $fp = fopen($cookie_file, 'w+');
            fclose($fp);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        if ($this->user) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->user}:{$this->password}");
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

}
