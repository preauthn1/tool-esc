<?php
use Twilio\Rest\Client;
use Twilio\Http\CurlClient;
/**
 * Twilio短信接口
 */
class Twilio {
    private $config = [];
    public $error = '';
    /**
     * Twilio短信接口
     * @param array $config <ul>
     * <li>string $account_sid account_sid </li>
     * <li>string $auth_token auth_token </li>
     * <li>string $from_phone from_phone </li>
     * </ul>
     */
    public function __construct(array $config) {
        $this->config = $config;
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
            $this->error = "参数有误：短信内容不能为空";
            return false;
        }
        if (empty($this->config['account_sid']) || empty($this->config['auth_token']) || empty($this->config['from_phone'])) {
            $this->error = "参数有误：配置信息有误";
            return false;
        }
        if (is_array($mobile)) {
            $this->error = "Twilio不支持批量发送短信";
            return false;
        }
        $sms_api = new Client($this->config['account_sid'], $this->config['auth_token']);
        
        //生产环境则设置为CURLOPT_SSL_VERIFYHOST为2，CURLOPT_SSL_VERIFYPEER为true
        //本地测试时设置 CURLOPT_SSL_VERIFYHOST为0，CURLOPT_SSL_VERIFYPEER为false
        $curlOptions = [CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_SSL_VERIFYPEER => true];
        $sms_api->setHttpClient(new CurlClient($curlOptions));
        $sms_body = "【{$this->config['sign']}】Your Verification Code is:{$code}"; //短信内容注意签名
        try {
            //开始发送短信
            $message = $sms_api->messages->create($mobile, ['from' => $this->config['from_phone'], 'body' => $sms_body]);
        } catch (\Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
        if ($message->sid) {
            return true;
        } else {
            $$this->error = '短信发送失败';
            return false;
        }
    }
    
    public function sendGjSms($mobile, $code) {
        return $this->sendSms($mobile, $code);
    }
}
