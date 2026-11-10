<?php

namespace transfer;

use app\common\model\MoneyLogModel;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class NOWPayments extends Transferbase
{

    public $config = [];

    public function __construct($config)
    {
        if (empty($config['api_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order)
    {
        $currency = $this->config['currency'] ?? 'usd';
        // Set callback URL for payment notifications
        $address = $order['withdrawal_address'];
        $callbackUrl = $this->config['ipn_callback_url_withdrawal']; // '/api/payment/notify/nowpayments';
        $paymentData = array(
            'ipn_callback_url' => $callbackUrl,
            'withdrawals' => [
                [
                    'address' => $address,
                    'currency' => $currency,
                    'amount' => sprintf('%.2f', $order["original_money"]),
                    'ipn_callback_url' => $callbackUrl,
                ]
            ],
        );
        $header = [
            'x-api-key: ' . $this->config['api_key'],
            'Content-Type: application/json',
            'Authorization: Bearer *' . $this->config['your_jwt_token'] . "*"
        ];
        $header = [
            'x-api-key' => $this->config['api_key'],
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->config['']
        ];

        try {
            $client = new Client();
            $response = $client->post($this->config['api_url'] . $this->config['payout_url'], [
                'json' => $paymentData,
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
            Log::write(["NOWPayments", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["NOWPayments", $result], 'pay');
        if (empty($result)) {
            throw new \Exception($result['message'] ?? '支付信息错误');
        }
        if (isset($result['withdrawals'][0])) {
            //creating;
            //processing;
            //sending;
            //finished;
            //failed;
            //rejected;
            $status = isset($result['withdrawals'][0]['status']) ? $result['withdrawals'][0]['status'] : '';
            $status = strtolower($status);
            Log::write(["VortaQpayTransferResponse", $result], 'transfer');
            if (is_array($result) && ($status != 'failed' && $status != 'rejected')) {
                return $result;
            } else {
                throw new Exception($result['errorMsg'] ?? "unknown error");
            }
        }
        $message = $result['errorMsg'] ?? 'unknown';
        throw new Exception($message);
    }

    public function notify()
    {
        $params = request()->param();
        Log::write(["NOWPaymentsPayoutNotifyParams", $params], 'transfer');

        // 检查必要参数（针对payout回调）
        if (!isset($params['withdrawal_id'])) {
            return 'requestParams error';
        }

        $withdrawal_id = $params['withdrawal_id'] ?? '';
        $withdrawal_status = $params['withdrawal_status'] ?? '';
        $order_id = $params['order_id'] ?? '';

        // 验证签名
        // 获取除signature外的所有参数
        $data = $params;
        unset($data['signature']);

        // 按键名排序
        ksort($data);

        // 构造签名字符串
        $signString = '';
        foreach ($data as $key => $value) {
            $signString .= $key . '=' . $value . '&';
        }
        $signString = rtrim($signString, '&');

        // 生成签名
        $signature = hash_hmac('sha512', $signString, $this->config['ipn_secret']);

        // 验证签名
        $flag = hash_equals($signature, $request['signature'] ?? '');

        if ($flag) {
            $status = 1; // 默认处理中
            // 根据提现状态更新订单
            $withdrawal_status = strtolower($withdrawal_status);
            switch ($withdrawal_status) {
                case 'finished':
                    // 提现成功
                    $status = 100;
                    break;
                case 'failed':
                case 'rejected':
                    // 提现失败
                    $status = 3;
                    $remark = 'Withdrawal Failed or Rejected';
                    break;
                case 'creating':
                case 'processing':
                case 'sending':
                default:
                    // 处理中状态，保持默认状态
                    $status = 1;
                    break;
            }
            Db::startTrans();
            try {
                $order = Db::name('withdrawals')->where('order_id', $order_id)->lock(true)->find();
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
                    if ($remark) {
                        $remark .= '_' . $order['remark'];
                    } else {
                        $remark = $order['remark'];
                    }
                    Db::name('withdrawals')->where(['id' => $order['id']])->update([
                        'status' => $status,
                        'check_time' => time(),
                        'remark' => $remark,
                        "trade_no" => $withdrawal_id,
                        "pay_time" => timeToStr(),
                        "result" => json_encode($params)
                    ]);
                }
                // 提交事务
                Db::commit();
                return 'SUCCESS';
            } catch (\think\Exception $exc) {
                Log::write($exc->getMessage() . "\n" . $exc->getTraceAsString(), "error");
                // 回滚事务
                Db::rollback();
                return 'fail';
            }
        }
        return 'fail';
    }

}
