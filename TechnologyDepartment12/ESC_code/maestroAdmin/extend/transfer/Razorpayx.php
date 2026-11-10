<?php

namespace transfer;

use GuzzleHttp\Client;
use think\facade\Db;
use think\Exception;
use Razorpay\Api\Api;
use think\facade\Log;
use transfer\Transferbase;
class Razorpayx extends Transferbase{
    public $url = "https://api.razorpay.com/v1/payouts";
    public $get_order_url = "https://pos.paytend.com/a/df_getpayresult";
    public $config = [];
    public $api = null;
    public function __construct($config) {
        if (empty($config['app_id']) ||
            empty($config['account_number']) ||
            empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
        $this->api = new Api($this->config['app_id'], $this->config['app_key']);
    }

    public function pay($order) {
        if (isset($order['trade_no']) && !empty($order['trade_no'])) {
            return true;
        }
        $params = [
            'account_number' => $this->config['account_number'],
            "amount" => intval($order['money'] * 100), //单位：分
            'currency' => 'INR',//i18n_currency_code(),
            'mode' => 'IMPS',
            'purpose' => 'payout',
            'fund_account' => [
                'account_type' => 'bank_account',
                'bank_account' => [
                    'name' => $order['true_name'],
                    "ifsc" => !isset($order['bank_kh']) || empty($order['bank_kh']) ? $this->getBankIfsc($order['bank_name']) : $order['bank_kh'],
                    'account_number' => $order['bank_number'],
                ],
                'contact' => [
                    'name' => $order['true_name'],
                ],
            ],
            "reference_id" => $order['order_id'], //订单号
//            "noticeUrl" => $this->config['notify_url'], //通知地址
        ];
//        dump($params);
        Log::write(["RazorPayXTransferParams", $params], "transfer");
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'timeout' => 70,
                'form_params' => $params,
                'auth' => [$this->config['app_id'], $this->config['app_key']],
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
        if (is_null($response)) {
            throw new Exception('请求失败');
        }
        $contents = $response->getBody()->getContents();
//        dump($contents);
        $result = json_decode($contents, true);
//        dump($result);
        if (empty($result)) {
            Log::write(["RazorPayXTransferError", $contents], "transfer");
            throw new Exception(lang('error'));
        }

        Log::write(["RazorPayXTransferResponse", $result], "transfer");
        if (isset($result['id']) && isset($result['reference_id']) ) {
            Db::name('withdrawals')->where('order_id', $result['reference_id'])->update([
                'trade_no' => $result['id']
            ]);
            return true;
        }else{
            throw new Exception(isset($result['error']) ? $result['error']['description'] : "unkown error");
        }
    }

    public function notify() {
        $request = request();
        $params = $request->param();
        $content = $request->getContent();
        $sign = $request->header('X-Razorpay-Signature');
        $this->api->utility->verifyWebhookSignature($content, $sign, $this->config['webhook_secret']);
        Log::write([$sign, $content, $params, "RazorPayXTransferNotifyParams"], 'transfer');
        if (empty($params)) {
            return 'requestParams error';
        }
        if (isset($params['entity']) && $params['entity'] === 'event') {
            Log::write([$params, "RazorPayWebHook"], 'transfer');
            $payload = $params['payload'];
            $payload_payout = isset($payload['payout']['entity']) ? $payload['payout']['entity'] : null;
            if (!isArrayAndNotEmpty($payload_payout)) {
                return 'payload_payout error';
            }
            if ($params['event'] == 'payout.reversed' && $payload_payout) { //失败
                $remark = isset($payload['failure_reason']) ? $payload['failure_reason'] : 'payout.reversed';
                $res = $this->updateOrder($payload_payout['reference_id'], 3, $remark);
            }elseif($params['event'] == 'payout.processed') { //成功
                $res = $this->updateOrder($payload_payout['reference_id'], 2);
                if (is_string($res)) {
                    abort(502, $res);
                }
            }
            return 'success';
        }
    }


    private function updateOrder($order_id, $status, $remark = '') {
        if (!in_array($status, [2,3])) {
            return false;
        }
        Db::startTrans();
        try {
            $order = Db::name('withdrawals')->where('order_id', $order_id)->lock(true)->find();
            if (!empty($order) && $order['status'] == 4) {
                $money = $order['money'] + $order['taxfee'];
                if ($status == 2) { //成功
                    Db::name('home_user')->where(['id' => $order['user_id']])->setInc('frozen_money', (-1 * $money));
                } else { //失败
                    addMoneyLog($order['user_id'], $money, 8, '提现转账失败');
                    Db::name('home_user')->where(['id' => $order['user_id']])->setInc('frozen_money', (-1 * $money));
                }
                Db::name('withdrawals')->where(['id' => $order['id']])->update([
                    'status' => $status,
                    'check_time' => time(),
                    'remark' => $remark,
                    "pay_time" => timeToStr(),
                ]);
            }
            // 提交事务
            Db::commit();
        } catch (\think\Exception $exc) {
            Log::write($exc->getMessage() . "\n" . $exc->getTraceAsString(), "error");
            // 回滚事务
            Db::rollback();
            return $exc->getMessage();
        }

        return true;
    }
    private function getBankIfsc($code) {
        $_bank = [
            "SBIN" => "SBIN0000001",
            "ICIC" => "ICIC0000001",
            "DBSS" => "DBSS0IN0811",
        ];
        return isset($_bank[$code]) ? $_bank[$code] : $code;
    }
}
