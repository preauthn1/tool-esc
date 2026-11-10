<?php

namespace email;

use Smtp\SmtpApiMailer;

class Smtpserver
{
    /**
     * 发送邮件
     * @param $to 接收邮箱
     * @param $code 验证码
     * @return bool
     * @throws \Exception
     */
    public function sendEmail($to, $code = '')
    {
        $smtp = new SmtpApiMailer('d1429db5efa7125a92393b26916620a0192206ce540fe83fd056bd3b1f7454a8f9d48be93c3acb67857ddfaafc408f02');
        $smtp->setFrom('zzhy112266@gmail.com', 'OmnicomGroup');
//        $smtp->setHeader(['List-Open' => '<https://shop.halo8.xyz/>']);
        $content = "[NBC] Your Verification Code is: {$code}"; //邮箱验证码内容
        $smtp->setTo($to);
        $smtp->setText($content);
        try {
            $response = $smtp->sendMail();
            dump($response);
            if ($response['body']) {
                $res = json_decode($response['body'], true);
                //{ success: 1, message: 'Mail accepted' }
                if ($res['success'] == 1) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }
}