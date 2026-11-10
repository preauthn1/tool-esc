<?php

namespace sms;

class CSms {
    protected $sms = null;
    /**
     * 扩展一下短信接口
     * @param array $config
     * @param string $type Alidayu|Dysmsapi
     * @throws \Exception
     */
    public function __construct($config, $type = 'Dysmsapi') {
        if (file_exists(__DIR__ . "/{$type}.php")) {
            include(__DIR__ . "/{$type}.php");
            if (class_exists($type)) {
                $this->sms = new $type($config);
            } else {
                throw new \Exception('Creating sms Object fail');
            }
        } else {
            throw new \Exception('no sms type fail');
        }
    }

    /**
     * 发送普通短信
     * @param string|array $mobile
     * @param array $params
     * @return boolean
     */
    public function sendSms($mobile, $params = []) {
        return $this->sms->sendSms($mobile, $params);
    }

    /**
     * 发送国际短信
     * @param string|array $mobile
     * @param array $params
     * @return boolean
     */
    public function sendGjSms($mobile, $params = []) {
        return $this->sms->sendGjSms($mobile, $params);
    }

    public function getError() {
        return $this->sms->error;
    }

}