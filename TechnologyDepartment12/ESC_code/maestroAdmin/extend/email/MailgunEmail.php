<?php


namespace email;


use Mailgun\Mailgun;

class MailgunEmail
{

    public function sendEmail($to, $code = '')
    {
        $mg = Mailgun::create('REDACTED'); // For EU servers
        $ret = $mg->messages()->send('noreply.maestrobr.com', [
            'from' => 'noreply@maestrobr.com',
            'to' => $to,
            'subject' => 'Maestro Verification',
            'text' => "[Maestro] Your Verification Code is: {$code}"
        ]);
//        $message = $ret->getMessage();
        $code = $ret->getStatusCode();
        if ($code == 200) {
            return true;
        }
        return false;
    }
}
