<?php
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
/**
 * 阿里云短信接口
 */
class Aliyun {
    private $config = [];
    public $error = '';
    /**
     * 阿里云短信接口
     * @param array $config <ul>
     * <li>string $key AppKey </li>
     * <li>string $secret AppSecret </li>
     * <li>string $sign 短信签名 </li>
     * </ul>
     */
    public function __construct(array $config) {
        $this->config = $config;
    }
    
    /**
     * 发送普通短信
     * @param string|array $mobile 
     * @param array $params
     * @return boolean
     */
    public function sendSms($mobile, $params) {
        $sms_id = $params['sms_id'];
        unset($params['sms_id']);
        if (empty($sms_id)) {
            $this->error = "参数有误：sms_id不能为空";
            return false;
        }
        if (empty($mobile)) {
            $this->error = "参数有误：手机号码不能为空";
            return false;
        }
        if (empty($params)) {
            $this->error = "参数有误：短信模板参数不能为空";
            return false;
        }
        if (empty($this->config['key']) || empty($this->config['secret']) || empty($this->config['sign'])) {
            $this->error = "参数有误：阿里云配置信息有误";
            return false;
        }
        if (is_array($mobile)) {
            $mobile = implode(",", $mobile);
        }
        foreach ($params as $key => $value) {
            $params[$key] = (string) $value;
        }
        
        
        //此处需要替换成自己的AK信息
        $accessKeyId = trim($this->config['key']);
        $accessKeySecret = trim($this->config['secret']);
        //短信API产品名
        $product = "Dysmsapi";
        //短信API产品域名
        $domain = "dysmsapi.aliyuncs.com";
        //暂时不支持多Region
        $region = "cn-hangzhou";

        AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
                        ->regionId($region)
                        ->asDefaultClient();
        
        $error = "";
        try {
            $result = AlibabaCloud::rpc()
                ->product($product)
                // ->scheme('https') // https | http
                ->version('2017-05-25')
                ->action('SendSms')
                ->method('POST')
                ->host($domain)
                ->options([
                    'query' => [
                        'RegionId' => $region,
                        'PhoneNumbers' => $mobile,
                        'SignName' => trim($this->config['sign']),
                        'TemplateCode' => trim($sms_id),
                        'TemplateParam' => json_encode($params),
                    ],
                ])
                ->request();
        } catch (ClientException $e) {
            $error = $e->getErrorMessage();
        } catch (ServerException $e) {
            $error = $e->getErrorMessage();
        }
        
        if (isset($result['Code']) && strtoupper($result['Code']) == 'OK') {
            return true;
        } else {
            foreach ($result as $key => $value) {
                $error .= "{$key}:{$value};";
            }
            $this->error = $error;
            return false;
        }
    }
}
