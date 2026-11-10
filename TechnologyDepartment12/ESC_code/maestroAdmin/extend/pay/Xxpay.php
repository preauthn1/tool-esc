<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Xxpay extends Paybase {

    public $url = "http://api.xxpay666.com/customer.pay";
    public $get_order_url = "http://api.xxpay666.com/customer.Pay.query";
    public $config = [];

    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = [
            'mer_id' => $this->config['app_id'], //商户id
            'timestamp' => date('Y-m-d H:i:s'), //订单时间
            'terminal' => $this->config['pay_type'],  //渠道类型：PC-网关  H5-快捷  ALI_CODE-支付宝扫码 ALI_PAY_WAP-支付宝H5  WEIXIN_CODE-微信扫码  WEIXIN_PAY_WAP-微信H5
            'version' => '01', //版本
            'amount' => intval($order['money'] * 100), //收款金额 以 ‘分’ 为单位
            'backurl' => $this->success(lang('PaymentSuccess')),  //支付失败同步回调地址
            'failUrl' => $this->error(lang('PaymentFail')),  //支付失败同步回调地址
            'ServerUrl' => $this->config['notify_url'], //成功支付异步回调地址
            'businessnumber' => $order['order_id'], //订单号 必须以数字或者字母组合，长度必须30位 保证不重复
            'goodsName' => 'goodsName', //商品名称
            'IsCredit' => 0,  //可空 直连必填(0:非直联 1：直联)
            'BankCode' =>'',  //可空 直连必填(IPS 唯一标识指定支付银行的编号)
            'ProductType' => '',//可空 直连必填(1个人网银 2企业网银)
            'UserId' => $order['user_id'],//可空 商户客户唯一标识id
            'UserRealName' => $this->config['real_name'],//可空 付款人姓名
            'UserRealMobile' => $this->config['mobile'],//可空 付款人手机号
            'UserRealBankCard' => '',//可空 付款人银行卡号
            'UserRealIdNumber' => $this->config['mobile'],//可空 付款人身份证号
            'sign_type' => 'md5',
        ];
        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["XxpayParams", $params], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'form_params' => $params
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response);
    }

    public function validateResponse($response) {
        $contents = $response->getBody()->getContents();
//        dump($contents);
        $result = json_decode($contents, true);
//        dump($result);die;
        if (empty($result)) {
            Log::write(["XxpayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["XxpayResponse", $result], 'pay');

        $data = isset($result['data']) ? $result['data'] : [];
        if (is_array($result) && isset($result['result']) && $result['result'] == 'success') {
            if (isset($data['trade_qrcode']) && $data['trade_qrcode']) {
                return redirect($data['trade_qrcode']);
            }
            return $data;
        } else {
            $remark = isset($data['remark']) ? json_decode($data['remark'], true) : false;
            if (is_array($remark) && !empty($remark)) {
                $message = isset($remark['message']) ? $remark['message'] : $result['remark'];
            } else {
                $message = isset($data['status']) ? $data['remark'] : 'unkown';
            }
        }
        throw new Exception($message);
    }

    public function notify() {
        $params = request()->param();
        if (!isset($params['sign'])) {
            return 'sign empty';
        }
        Log::write(["XxpayNotifyParams", $params], 'pay');
        $sign = $this->getSign($params);
        if ($params['sign'] != $sign) {
            return 'sign error';
        }
        if (isset($params["result"]) && in_array($params["result"], ['success'])) {
            $remark = '';
            if (trim($params['status']) === '成功') { //成功
                $status = 2;
            } else { //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $params['businessnumber'];
            $amount_true = null;
            $ret = $this->complete($params["businessnumber"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'success';
            }
            return $ret;
        }
        return 'fail';
    }

    private function getSign($params) {
        unset($params['pay_id']);
        // sign,sign_type不参与签名
        unset($params['sign'], $params['sign_type']);
        ksort($params);//根据键值升序排列
        $str_sign = '';
        foreach ($params as $key => $value) {
            $str_sign .= $key . '=' . $value . '&';
        }

        $str_sign .= $this->config['app_key'];
        return strtoupper(md5($str_sign));
    }

}
