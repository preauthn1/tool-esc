<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use app\common\model\BankModel;
use app\common\model\UserModel;
use think\facade\Log;

class Vipdcpay extends Transferbase{
    public $defaultConfig = [
        'sandbox_merchant_id' => 'M1763138490',
        'sandbox_key' => '27cb5246da3e157b6b6e76821a60c300',
        'merchant_id' => 'M1763138490',
        'key' => '27cb5246da3e157b6b6e76821a60c300',
        'coin' => 'VIPDCPAY',
        'currency_code' => 'BRL',
        'pay' => '/pay/unifiedOrder',
        'repay' => '/repay/unifiedOrder',
        'pay_query' => '/pay/query',
        'repay_query' => '/repay/query',
        'balance' => '/query/balance',
        'domain' => 'https://api.vipdcpay.com',
        'sandbox_domain' => 'https://sandbox-api.vipdcpay.com',
        'pay_notify'=>"https://api.huotai10086.com/api.php/notify/VortaqpayRecharge",
        'repay_notify'=>"https://api.huotai10086.com/api.php/notify/VortaqpayRecharge",
        'pay_channel_code'=>'brlds',
        'repay_channel_code'=>'brldf'
    ];

    public $config = [];
    public function __construct($config) {
        if (empty($config['merchant_id']) || empty($config['key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $document_types = ["CNPJ","CPF","PHONE", "EMAIL", "EVP"];
        // 判断order['document_type'] 是否在 数组document_types里面
        if (!in_array($order['document_type'], $document_types)) {
            throw new Exception('document_type error');
        }
        $extra_params = [
            "name" => trim($order['true_name']),
            $order['document_type'] => $order['document_id']
        ];
//        ksort($extra_params);
//        $data = array_filter($extra_params, function($v) {
//            return $v !== '' && $v !== null;
//        });
//        foreach ($data as $k => $v) $pairs[] = "$k=$v";
//        $signStr = implode('&', $pairs);

        $params = [
            'merchant_id'        => $this->config['merchant_id'],       //商户号
            'merchant_order_no' => $order['order_id'],                  //订单号
            'amount'       => sprintf("%.2f", $order['withdraw_money']),  // 订单金额
            'currency'=>$this->config['currency_code'],                 // 币种
            'channel_code'=>$this->config['repay_channel_code'],          // 支付渠道编码（代收）
            'notify_url' => $this->config['repay_notify'],                // 回调地址
            'subject'      => "MAESTRO-用户提现",                         // 代付说明
            'timestamp'     => time(),
            'sign_type'      => "md5"
        ];

        $params['sign'] = $this->merchantSign($params, $extra_params, $this->config['key']);
        $params['extra_params'] = $extra_params;
        Log::write(["VipdcpayTransferParams", $params], 'transfer');
        try {
            $client = new Client();
            $url = $this->config['domain'] . $this->config['repay'];
            $response = $client->post($url, [
                'form_params' => $params,
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response);
    }

    public function find($order_id) {
        $params = array(
            'merchant_id' => $this->config['merchant_id'],
            'merchant_order_no' => $order_id,
            'timestamp'=>time(),
            'query_time' => time(),
            'sign_type'      => "md5",
        );
        $params['sign'] = $this->merchantSignQuery($params, $this->config['key']);
        Log::write(["VipdcpayTransfer", $params], 'transfer');
        try {
            $client = new Client();
            $url = $this->config['domain'] . $this->config['repay_query'];
            dump($url);
            $response = $client->post($url, [
                'form_params' => $params
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response, 'find');
    }

    public function validateResponse($response, $type = 'pay') {
        $contents = $response->getBody()->getContents();

        $result = json_decode($contents, true);
        if (empty($result)) {
            Log::write(["EkpayTransferError", $result], 'transfer');
            throw new Exception(lang('error'));
        }

        Log::write(["VipdcpayTransferResponse", $result], 'transfer');

        if(isset($result['code']) && $result['code'] == 1){
            return $result;
        }else{
            $msg = isset($result['msg']) ? $result['msg'] : "unkown error";
        }
        throw new Exception($msg);
    }

    public function notify() {
        $params = request()->param();
        Log::write(["VipdcpayTransferNotifyParams", $params], 'transfer');
        if (empty($params)) {
            return 'requestParams error';
        }
        $sign = $this->verifySign($params, $this->config['key']);
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
        // TODO 暂时屏蔽查询接口
         $find = $this->find($params["merchant_order_no"]);
//         var_dump($params);
         Log::write(["VipdcpayTransferNotifyParams", $find], 'transfer');
        if ($find && in_array($params["status"], [3,-1,-2])) {

            if ($params['status'] == 3) { //成功
                $status = 2;
                $remark = '三方出款成功';
            } else {
                $status = 5;
                $remark = '三方出款失败';
            }
            $trade_no = $params['order_no'];
            $ret = $this->completeDcpay($params["merchant_order_no"], $status, $remark, $trade_no, json_encode($params));
            if ($ret === true) {
                return 'SUCCESS';
            }
            return $ret;
        }
        return 'error';
    }

    function verifySign($params, $secret) {
        $sign = $params['sign'];
        unset($params['sign']);

        ksort($params);
        $string = '';
        foreach ($params as $key => $value) {
            $string .= $key . '=' . $value . '&';
        }
        $string .= 'key=' . $secret;

        return strtoupper(md5($string)) === $sign;
    }

    /**
    1. 移除 sign 字段（如果存在）
    2. 处理数组参数：将数组转换为 key=value&key=value 格式
    3. 将所有参数（除空值外）按参数名ASCII码升序排序
    4. 将排序后的参数按 key=value&key=value 格式拼接
    5. 在拼接字符串末尾加上 &key=商户密钥
    6. 对拼接后的字符串进行MD5加密，转为大写
     */
    function merchantSign($data,$extra_params, $key) {

        ksort($extra_params);
        $extra_params_data = array_filter($extra_params, function($v) {
            return $v !== '' && $v !== null;
        });
        $pairs_tmp = [];
        foreach ($extra_params_data as $k => $v) {
            $pairs_tmp[] = "$k=$v";
        }

        $data['extra_params'] = implode('&', $pairs_tmp);


        // 移除sign字段
        unset($data['sign']);

        // 处理数组参数
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                ksort($v);
                $arr = [];
                foreach ($v as $ak => $av) {
                    if ($av !== '' && $av !== null) $arr[] = "$ak=$av";
                }
                $data[$k] = implode('&', $arr);
            }
        }

        // 排序并过滤空值
        ksort($data);
        $data = array_filter($data, function($v) {
            return $v !== '' && $v !== null;
        });

        // 构建签名字符串
        $pairs = [];
        foreach ($data as $k => $v) $pairs[] = "$k=$v";
        $signStr = implode('&', $pairs) . "&key=$key";
        return strtoupper(md5($signStr));
    }

    function merchantSignQuery($data, $key) {
        // 移除sign字段
        unset($data['sign']);

        // 处理数组参数
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                ksort($v);
                $arr = [];
                foreach ($v as $ak => $av) {
                    if ($av !== '' && $av !== null) $arr[] = "$ak=$av";
                }
                $data[$k] = implode('&', $arr);
            }
        }

        // 排序并过滤空值
        ksort($data);
        $data = array_filter($data, function($v) {
            return $v !== '' && $v !== null;
        });

        // 构建签名字符串
        $pairs = [];
        foreach ($data as $k => $v) $pairs[] = "$k=$v";
        $signStr = implode('&', $pairs) . "&key=$key";
        return strtoupper(md5($signStr));
    }

    private function getBankIfsc($code) {
        $extra = BankModel::where('bank_code', $code)->value('extra');
        return $extra ?? $code;
    }
}
