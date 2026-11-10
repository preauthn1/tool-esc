<?php

namespace transfer;

use app\common\model\MoneyLogModel;
use GuzzleHttp\Client;
use pay\Paybase;
use think\facade\Db;
use think\Exception;
use think\facade\Log;

class Shpay extends Paybase
{

    public $url = "https://transapi.shpays.com/v1/trans/payOut";
    public $config = [];

    public function __construct($config)
    {
        if (empty($config['mchtId']) || empty($config['appId'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order)
    {
        $params = [
            'mchtId'      => $this->config['mchtId'],
            'appId'       => $this->config['appId'],
            'countryCode' => 'BR',
            'requestTime' => date('Y-m-d H:i:s'),
            'notifyUrl'   => $this->config['notification_url'],
            'signType'    => 'MD5',
            'transAmt'    => sprintf("%.2f", $order['original_money']),
            'outTradeNo'  => $order->order_id,
            'subject'     => 'Withdrawal '.$order['original_money'].' BRL',
            'pixType'     => $order['document_type'], // CPF PHONE EMAIL
            'accountNo'   => $order['document_id'],
            'accountName' => $order["true_name"],
        ];

        $params['sign'] = $this->getSign($params);

        Log::write(["ShpayTransferParams", $params], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'json' => $params
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
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
            Log::write(["ShpayTransferError", $contents], 'transfer');
            throw new Exception(lang('error'));
        }
        Log::write(["ShpayTransferResponse", $result], 'transfer');

        if (is_array($result) && isset($result['success']) && $result['success'] === true) {
            return $result;
        } else {
            $message = $result['message'] ?? 'unknown';
        }
        throw new Exception($message);
    }

    public function notify()
    {
        $params = request()->param();
        Log::write(["ShpayTransferNotifyParams", $params], 'transfer');
        if (!isset($params['sign'])) {
            return 'requestParams error';
        }
        $data = [
            'event'          => $params['event'],
            'outTradeNo'     => $params['outTradeNo'],
            'transNo'        => $params['transNo'],
            'transStatus'    => $params['transStatus'],
            'transAmt'       => $params['transAmt'],
            'completionTime' => $params['completionTime'],
        ];
        $sign = $this->getSign($data);
        $flag = $sign == $params['sign'];
        if ($flag) {
            $status = $params['transStatus'];
            Db::startTrans();
            try {
                $order = Db::name('withdrawals')->where('order_id', $params["outTradeNo"])->lock(true)->find();
                if (!empty($order) && $order['status'] == 2) {
                    $money = $order['original_money'];
                    $remark = '';
                    // tradeResult 0:申请成功 1:转账成功 2:转账失败 3:转账拒绝 4:处理中;
                    $money_log = new MoneyLogModel();
                    $user_id = $order['user_id'];
                    if ($status == 'SUCCESS') { //成功
                        $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_FROZEN, 'sub', '提现成功',
                            $remark, 2);
                        $status = 4;
                    } else { //失败
                        $remark = '提现转账失败';
                        $status = 5;
                    }
                    $remark .= '_'.$order['remark'];
                    Db::name('withdrawals')->where(['id' => $order['id']])->update([
                        'status'     => $status,
                        'check_time' => time(),
                        'remark'     => $remark,
                        "trade_no"   => $params['transNo'],
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

    private function getSign($params)
    {
        ksort($params);
        $md5str = "";
        foreach ($params as $key => $val) {
            $md5str = $md5str.$key."=".$val."&";
        }
        $md5str = trim($md5str, '&');
        $sign = strtoupper(md5($md5str.$this->config['key']));
        return $sign;
    }
}
