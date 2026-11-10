<?php

namespace email;

use Brevo\Brevo;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestReplyTo; // 添加这个引入

class BrevoEmail
{
    private $apiKey = "REDACTED";
    private $senderEmail = "noreply@email.maestro-by.com"; // 使用你已验证的域名邮箱
    private $senderName = "Maestro";
    private $replyToEmail = "support@email.maestro-by.com"; // 回复邮箱
    private $replyToName = "Customer Support"; // 回复名称

    public function sendEmail($to, $code = '')
    {
        try {
            $client = new Brevo(apiKey: $this->apiKey);

            // 构建请求数据
            $requestData = [
                'subject' => 'Maestro Verification Code',
                'htmlContent' => $this->buildEmailContent($code),
                'sender' => new SendTransacEmailRequestSender([
                    'name' => $this->senderName,
                    'email' => $this->senderEmail,
                ]),
                'to' => [
                    new SendTransacEmailRequestToItem([
                        'email' => $to,
                        'name' => $this->extractNameFromEmail($to),
                    ]),
                ],
            ];

            // 添加 replyTo（如果有配置）
            if ($this->replyToEmail) {
                $requestData['replyTo'] = new SendTransacEmailRequestReplyTo([
                    'name' => $this->replyToName,
                    'email' => $this->replyToEmail,
                ]);
            }

            $request = new SendTransacEmailRequest($requestData);
            $result = $client->transactionalEmails->sendTransacEmail($request);

            if (isset($result->messageId)) {
                return [
                    'success' => true,
                    'messageId' => $result->messageId,
                    'message' => 'Email sent successfully'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to send email: No message ID returned'
            ];

        } catch (\Exception $e) {
            // 记录详细的错误日志
            $errorMessage = 'Brevo email error: ' . $e->getMessage();
            error_log($errorMessage);

            return [
                'success' => false,
                'message' => $errorMessage
            ];
        }
    }

    /**
     * 发送简单文本邮件（不带HTML样式）
     */
    public function sendTextEmail($to, $code = '')
    {
        try {
            $client = new Brevo(apiKey: $this->apiKey);

            $requestData = [
                'subject' => 'Maestro Verification Code',
                'textContent' => "[Maestro] Your Verification Code is: {$code}\n\nThis code will expire in 10 minutes.",
                'sender' => new SendTransacEmailRequestSender([
                    'name' => $this->senderName,
                    'email' => $this->senderEmail,
                ]),
                'to' => [
                    new SendTransacEmailRequestToItem([
                        'email' => $to,
                        'name' => $this->extractNameFromEmail($to),
                    ]),
                ],
            ];

            $request = new SendTransacEmailRequest($requestData);
            $result = $client->transactionalEmails->sendTransacEmail($request);

            return [
                'success' => true,
                'messageId' => $result->messageId ?? null,
                'message' => 'Email sent successfully'
            ];

        } catch (\Exception $e) {
            error_log('Brevo text email error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 构建邮件HTML内容
     */
    private function buildEmailContent($code)
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background-color: #f8f9fa; border-radius: 8px; padding: 30px; text-align: center;">
                <h2 style="color: #2c3e50; margin-bottom: 20px;">Maestro Verification</h2>
                <p style="font-size: 16px; margin-bottom: 30px;">Your verification code is:</p>
                <div style="background-color: #3498db; color: white; font-size: 32px; font-weight: bold; padding: 15px; border-radius: 5px; letter-spacing: 5px; margin-bottom: 30px;">
                    {$code}
                </div>
                <p style="font-size: 14px; color: #7f8c8d;">This code will expire in 10 minutes. If you didn't request this code, please ignore this email.</p>
            </div>
            <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #95a5a6;">
                &copy; 2024 MAJOR RESULTS, L.L.C. All rights reserved.
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * 从邮箱地址提取名称
     */
    private function extractNameFromEmail($email)
    {
        $name = explode('@', $email)[0];
        // 将点号和下划线替换为空格，并转换为首字母大写
        $name = str_replace(['.', '_'], ' ', $name);
        $name = ucwords($name);
        return $name;
    }

    /**
     * 设置回复地址
     */
    public function setReplyTo($email, $name = '')
    {
        $this->replyToEmail = $email;
        $this->replyToName = $name ?: $email;
        return $this;
    }

    /**
     * 使用模板发送邮件
     */
    public function sendEmailWithTemplate($to, $templateId, $params = [])
    {
        try {
            $client = new Brevo(apiKey: $this->apiKey);

            $requestData = [
                'templateId' => $templateId,
                'sender' => new SendTransacEmailRequestSender([
                    'name' => $this->senderName,
                    'email' => $this->senderEmail,
                ]),
                'to' => [
                    new SendTransacEmailRequestToItem([
                        'email' => $to,
                        'name' => $this->extractNameFromEmail($to),
                    ]),
                ],
                'params' => $params, // 模板参数
            ];

            // 添加 replyTo（如果有配置）
            if ($this->replyToEmail) {
                $requestData['replyTo'] = new SendTransacEmailRequestReplyTo([
                    'name' => $this->replyToName,
                    'email' => $this->replyToEmail,
                ]);
            }

            $request = new SendTransacEmailRequest($requestData);
            $result = $client->transactionalEmails->sendTransacEmail($request);

            return [
                'success' => true,
                'messageId' => $result->messageId ?? null,
                'message' => 'Email sent successfully'
            ];

        } catch (\Exception $e) {
            error_log('Brevo template email error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 批量发送邮件
     */
    public function sendBulkEmails(array $recipients, $code = '')
    {
        $results = [];
        foreach ($recipients as $recipient) {
            $results[$recipient] = $this->sendEmail($recipient, $code);
        }
        return $results;
    }
}