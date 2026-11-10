<?php

namespace transfer;

use app\common\model\MoneyLogModel;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class Vortaqpay extends Transferbase
{
    public $url = "https://api.vortaqpay.com";
    public $config = [];

    public function __construct($config)
    {
        if (empty($config['merchant_no']) || empty($config['app_id'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order)
    {
        $payee = [
            'name'         => $order["true_name"],
            'account'      => $order['document_id'], // pixPIX密钥
            'account_type' => $order['document_type'], // CPF PHONE EMAIL CHAVE
        ];

        $data = array(
            'country'          => 'BR',
            'currency'         => 'BRL',
            'order_id'         => $order['order_id'],
            'amount'           => sprintf('%.2f', $order["original_money"]), // 固定2位小数点的浮点数金额，如：10.00。
            'notification_url' => str_replace('\/', '/', $this->config['payout_notification_url']),
//            'success_redirect_url' => '',
            'timestamp'        => $this->getMillisecond(),
        );
        $data['signature'] = strtoupper($this->md5_sign($data, '&key='.$this->config['payout_secret']));
        $data['payee'] = $payee;
        $post_data = array(
            'merchant_no' => $this->config['merchant_no'],
            'data'        => $data,
        );
        $header = [
            'Content-Type' => 'application/json;charset=UTF-8',
            'ApiVersion'   => '1.0',
            'AppId'        => $this->config['app_id'],
            'Noncestr'     => mt_rand(1, 99999999),
            'Timestamp'    => $this->getMillisecond(),
        ];
        //请求头


        Log::write(["VortaQpayParams", $post_data], 'transfer');
        Log::write(["VortaQpayHeader", $header], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->url.'/api/settle/settlement', [
                'json'    => $post_data,
                'headers' => $header
            ]);
        } catch (RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response);
    }

    public function validateResponse($response)
    {
        $contents = $response->getBody()->getContents();
        $result = json_decode($contents, true);
        if (empty($result)) {
            Log::write(["VortaQpayTransferError", $contents], 'transfer');
            throw new Exception(lang('error'));
        }

        Log::write(["VortaQpayTransferResponse", $result], 'transfer');
        if (is_array($result) && isset($result['state']) && $result['state'] === 'ok') {
            return $result;
        } else {
            throw new Exception($result['errorMsg'] ?? "unknown error");
        }
    }

    public function notify()
    {
        $params = request()->param();
        Log::write(["VortaQpayTransferNotifyParams", $params], 'transfer');
        if (!isset($params['signature'])) {
            return 'requestParams error';
        }

        $data = [
            'amount'        => $params['amount'],
            'country'       => $params['country'],
            'currency'      => $params['currency'],
            'order_id'      => $params['order_id'],
            'settlement_id' => $params['settlement_id'],
            'status'        => $params['status'],
            'status_code'   => $params['status_code'],
            'status_detail' => $params['status_detail'],
            'timestamp'     => $params['timestamp'],
        ];

        $signature = strtoupper($this->md5_sign($data, '&key='.$this->config['payout_secret']));
        $flag = $params['signature'] == $signature;
        if ($flag) {
            $status = $data['status_code'];
            Db::startTrans();
            try {
                $order = Db::name('withdrawals')->where('order_id', $params["order_id"])->lock(true)->find();
                if (!empty($order) && ($order['status'] == 2 || ($status == 4 && $order['status'] == 4))) {
                    $money = $order['original_money'];
                    $remark = '';
                    // status_code 1-下单 2-待审核 3-出款中 4-出款失败 5-退回 100-成功;
                    $money_log = new MoneyLogModel();
                    $user_id = $order['user_id'];
                    if ($status == 100) { //成功
                        $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_FROZEN, 'sub', '提现成功',
                            $remark, 2);
                        $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_WITHDRAWAL, 'add', '', '', 2);
                        $status = 4;
                    } else { //失败
//                    $title = '提现失败';
                        $remark = '提现转账失败';
//                    // 提现失败，退回冻结资金，退回手续费
//                    $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_FROZEN, 'sub', $title,
//                        $remark, 2);
//                    $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_BROKERAGE, 'add', $title,
//                        $remark, 2);
//                    if ($order['handling_fee'] > 0) {
//                        $money_log->changeMoney($user_id, $order['handling_fee'], MoneyLogModel::TYPE_BROKERAGE,
//                            'add', '用户提现手续费', '用户提现手续费', 2);
//                    }
                        $status = 5;
                    }
                    if ($remark){
                        $remark .= '_'.$order['remark'];
                    } else {
                        $remark = $order['remark'];
                    }
                    Db::name('withdrawals')->where(['id' => $order['id']])->update([
                        'status'     => $status,
                        'check_time' => time(),
                        'remark'     => $remark,
                        "trade_no"   => $params['settlement_id'],
                        "pay_time"   => timeToStr(),
                        "result"     => json_encode($params)
                    ]);
                }
                // 提交事务
                Db::commit();
                return 'SUCCESS';
            } catch (\think\Exception $exc) {
                Log::write($exc->getMessage()."\n".$exc->getTraceAsString(), "error");
                // 回滚事务
                Db::rollback();
                return 'fail';
            }
        }
        return 'fail';
    }

    /**
     * 获取utc时间戳
     */
    function getMillisecond()
    {
        $sysTimeZone = date_default_timezone_get(); //先取出系统所在服务器的时区
        date_default_timezone_set('UTC'); //设置时区为UTC时区
        list($s1, $s2) = explode(' ', microtime());
        $timestamp = (float) sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
        date_default_timezone_set($sysTimeZone); //设置时区为服务器时区
        return $timestamp;
    }

    /**
     * MD5 签名
     * @param  array  $data
     * @param  string  $key
     * @param  bool  $is_md5
     * @return string
     */
    function md5_sign($data, $key, $is_md5 = true)
    {
        ksort($data);
        $string = '';
        foreach ($data as $k => $vo) {
            if ($vo !== '') {
                $string .= $k.'='.$vo.'&';
            }
        }
        $string = rtrim($string, '&');
        $result = $string.$key;
        return $is_md5 ? md5($result) : $result;
    }
}
