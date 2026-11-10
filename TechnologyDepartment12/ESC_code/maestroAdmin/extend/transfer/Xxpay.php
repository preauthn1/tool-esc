<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use app\common\model\BankModel;
use app\common\model\UserModel;
use think\facade\Log;

class Xxpay extends Transferbase{
    public $url = "http://api.xxpay666.com/pay.bank.to";
    public $get_order_url = "http://api.xxpay666.com/pay.order.query";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        if (!empty($order['trade_no'])) {
            return true;
        }
//        $mobile = UserModel::where('id', $order['user_id'])->value('mobile');
        $params = [
            'mer_id' => $this->config['app_id'], //商户id
            'timestamp' => date('Y-m-d H:i:s'), //订单时间
            'version' => '01', //版本
            "terminal" => "H5",
            "amount" => intval($order['money'] * 100),
            "businessnumber" => $order['order_id'],
            "bankcardnumber" => $order['bank_number'],
            'bankcardname' => $order['true_name'], //卡号用户名称
            'bankname' => $order['bank_name'], //银行卡所属银行
            'back_url' => $this->config['notify_url'],//异步回调地址
            'cash_type' => 3,//.1=>对私取现(代付业务) 2=>对公取现(代付业务) 3=》易收付（收款取现）
            'wallet_id' => $this->config['wallet_id'], //钱包ID
            'sign_type' => 'md5',
        ];

        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["XxpayTransferParams", $params], 'transfer',);
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'json' => $params,
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response);
    }

    public function validateResponse($response, $type = 'pay') {
        $contents = $response->getBody()->getContents();
//        dump($contents);
        $result = json_decode($contents, true);
//        dump($result);
        if (empty($result)) {
            Log::write(["XxpayTransferError", $result], 'transfer');
            throw new Exception(lang('error'));
        }

        Log::write(["XxpayTransferResponse", $result], 'transfer');
        $data = isset($result['data']) ? $result['data'] : [];
        if (is_array($result) && isset($result['result']) && $result['result'] == 'success') {
            if((isset($result['status']) && $result['status'] !== '失败')){
                return true;
            }
        }elseif($result['code'] === 'RK_E0003') {
            return true;
        }

        $remark = isset($data['remark']) ? json_decode($data['remark'], true) : false;
        if (is_array($remark) && !empty($remark)) {
            $msg = isset($remark['message']) ? $remark['message'] : $result['remark'];
        } else {
            $msg = isset($data['status']) ? $data['remark'] : 'unkown';
        }
        throw new Exception($msg);
    }

    public function notify() {
        $params = request()->param();
        if (!isset($params['sign'])) {
            return 'sign empty';
        }
        Log::write(["XxpayTransferNotifyParams", $params], 'transfer');
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
                $remark = '提现转账失败';
            }
            $trade_no = $params['businessnumber'];
            $ret = $this->complete($params["businessnumber"], $status, $remark, $trade_no);
            if ($ret === true) {
                return 'success';
            }
            return $ret;
        }
        return 'error';
    }

    private function getSign($params) {
        unset($params['channel']);
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

    private function getBankIfsc($code) {
        $extra = BankModel::where('bank_code', $code)->value('extra');
        return $extra ?? $code;
    }
}
