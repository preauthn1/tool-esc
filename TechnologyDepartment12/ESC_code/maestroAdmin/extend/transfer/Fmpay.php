<?php

namespace transfer;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class Fmpay extends Transferbase{
    public $url = "https://gateway.fhyl.ooo/withdraw/gateway";
    public $get_order_url = "https://gateway.fhyl.ooo/withdraw/query";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_transfer_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        if (isset($order['trade_no']) && !empty($order['trade_no'])) {
            return true;
        }
//        $user = UserModel::where('id', $order['user_id'])->find();
        //扫码
        $params = [
            'version' => 'V1.0',
            "partner_id" => $this->config['app_id'],
            "order_no" => $order['order_id'],
            "amount" => sprintf("%.2f", $order['money']),
            "account_name" => $order['true_name'],
            "account_no" => $order['bank_number'],
            "bank_code" => $this->getBank($order['bank_name']),
        ];

        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["FmpayTransferParams", $params], 'transfer');
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


    public function find($order_id) {
        $params = array(
            'partner_id' => $this->config['app_id'],
            'order_no' => $order_id,
            'version' => 'V1.0',
            'amount' => '0.00',
        );
        $params['sign'] = $this->getSign($params);
//        dump($params);
        Log::write(["FmpayTransferFindParams", $params], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->get_order_url, [
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
            Log::write(["FmpayTransferError", $contents], 'transfer');
            throw new Exception(lang('error'));
        }
        Log::write(["FmpayTransferResponse", $result], 'transfer');
        if(in_array($result['code'], ['00'])){
            if ($type == 'pay') {
                Db::name('withdrawals')->where('order_id', $result['order_no'])->update([
                    'trade_no' => $result['trade_no']
                ]);
            }
            return $type == 'pay' ? true : $result;
        }else{
            throw new Exception(isset($result['message']) ? $result['message'] : "unkown error");
        }
    }

    public function notify($order_id) {
        $result = $this->find($order_id);
        if (!isArrayAndNotEmpty($result)) {
            return 'continue';
        }
        Log::write(["FmpayTransferFindResult", $result], 'transfer');
        $params = $result;
        if (in_array($params["status"], [2, 3])) {
            $remark = '';
            if ($params['status'] == '2') { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = '提现转账失败';
            }
            $trade_no = $params['trade_no'];
            $ret = $this->complete($params["order_no"], $status, $remark, $trade_no);
            if ($ret === true) {
                return 'success';
            }
        }
        return 'continue';
    }

    private function getSign($params) {
        unset($params['sign']);
        unset($params['attach']);
        unset($params['channel']);
        ksort($params);
        $md5str = "";
        foreach ($params as $key => $val) {
            $val = trim($val);
            if ('sign' != $key && '' != $val) {
                $md5str = $md5str . $key . "=" . $val . "&";
            }
        }
        $md5str .= $this->config['app_transfer_key'];
//        Log::write("Fmpay presign string : ".$md5str, "debug");
        return strtolower(md5($md5str));
    }

    private function getBank($code) {
        $banks = [];
        return isset($banks[$code]) ? $banks[$code] : $code;
    }

}
