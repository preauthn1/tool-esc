<?php

namespace sms;

class Xinji
{
    public $error = '';

    protected $url = 'http://43.159.145.246:9511';
    protected $spId = '391529';
//    protected $REDACTED;
    protected $REDACTED;
    protected $signature = 'a92569901189f2574effc957c096c432e2d47635';

    /**
     * 发送国际短信
     * @param string $mobile
     * @param string $code
     * @return boolean
     */
    public function sendGjSms($mobile, $code)
    {
        //短信发送
        $action = '/api/send-sms-single';
        if (empty($mobile)) {
            $this->error = "参数有误：手机号码不能为空";
            return false;
        }
        if (trim($code) == "") {
            $this->error = "参数有误：短信验证码不能为空";
            return false;
        }
        if (empty($this->spId) || empty($this->password)) {
            $this->error = "参数有误：配置信息有误";
            return false;
        }
        if (is_array($mobile)) {
            $mobile = implode(",", $mobile);
        }
        $content = "[Maestro] Seu código de verificação é: {$code}."; //短信内容注意签名
        $postData = array(
            'sp_id' => $this->spId,
            'mobile' => $mobile,
            'content' => $content,
        );

        $usePassword = true;
        //如果是使用得密码发送
        if ($usePassword) {
//            $postData['password'] = md5($this->password);
            $postData['password'] = $this->password;
        } else {
//            $postData['signature'] = self::generateSignature($postData, 'POST', $this->password);
            $postData['signature'] = $this->signature;
        }
        $result = self::doCurl($postData, $this->url, $action);
        $data = json_decode($result, true);
        if ($data['code'] == 0) {
            return true;
        } else {
            $this->error = $this->getError($data['code'] . "");
            return false;
        }
    }

    protected function generateSignature($data, $method, $password)
    {
        ksort($data);

        $uri = $method . '&' . urlencode('/') . '&' . preg_replace(['/\+/', '/\*/', '/%7E/'], ['%20', '%2A', '~'], http_build_query($data));

        return base64_encode(hash_hmac("sha1", $uri, $password));
    }

    protected function doCurl($data, $url, $action)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . $action);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    private function getError($status)
    {
        //10000	服务出错，请稍后再试
        //10001	参数错误，请确认
        //10008	账号被锁定，请联系管理员确认
        //10011	余额不足，请尽快充值
        //10100	签名校验失败
        //10101	您的ip不在白名单内，请确认
        //10102	密码错误，请确认
        //10200	产品sp_id必须填写
        //10201	手机号必须填写
        //10202	短信内容必须填写
        //10203	发送时间格式必须为UNIX时间戳
        //10208	短信进拦截，具体原因参考data字段
        //10209	触发防刷，请调整配置
        $msg = [
            "10000" => "服务出错，请稍后再试",
            "10001" => "参数错误，请确认",
            "10008" => "账号被锁定，请联系管理员确认",
            "10011" => "余额不足，请尽快充值",
            "10100" => "签名校验失败",
            "10101" => "您的ip不在白名单内，请确认",
            "10102" => "密码错误，请确认",
            "10200" => "产品sp_id必须填写",
            "10201" => "手机号必须填写",
            "10202" => "短信内容必须填写",
            "10203" => "发送时间格式必须为UNIX时间戳",
            "10208" => "短信进拦截，具体原因参考data字段",
            "10209" => "触发防刷，请调整配置",
            "-1" => "其他错误",
            "0" => "短信发送成功",
        ];
        return isset($msg[$status]) ? $msg[$status] : 'unkown';
    }

}
