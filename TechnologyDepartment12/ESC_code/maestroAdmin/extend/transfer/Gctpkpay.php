<?php

namespace transfer;

use app\common\model\MoneyLogModel;
use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class Gctpkpay extends Transferbase
{
    public $url = "https://taslk.gctpk.com/payout/singleOrder";
    public $config = [];

    public function __construct($config)
    {
        if (empty($config['sha256_key']) || empty($config['mer_no']) || empty($config['mch_private_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order)
    {
        //生成订单号
        $out_trade_no = $order['order_id'];

        $notifyUrl = $this->config['payout_notify_url'];

        $data['bankCode'] = $order['bank_name'];##	银行编码

        if ($data['bankCode'] == "IMPS") {
            $data['province'] = 'test';
        }

        $data['accName'] = $order["true_name"];
        $data['accNo'] = $order['bank_number'];
        $data['busiCode'] = $this->config['payout_busi_code'];
        $data['currency'] = $this->config['coin'];
        $data['email'] = 'test@mail.com';
        $data['merNo'] = $this->config['mer_no'];
        $data['merOrderNo'] = $out_trade_no;
        $data['notifyUrl'] = $notifyUrl;//回调地址 代付成功失败都走回调
        $data['orderAmount'] = round($order["original_money"], 2);
        $data['phone'] = '123456789';//限制只能为数字号码
        $data['timestamp'] = $this->msectime();

        $data["sign"] = $this->encrypt($data, $this->config['sha256_key'], $this->config['mch_private_key']);//返回加密sign


//        dump($postdata);die;
        Log::write(["GctpkpayTransferParams", $data], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'json'  => $data,
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
            Log::write($contents, "GctpkpayTransferError");
            throw new Exception(lang('error'));
        }

        Log::write(["GctpkpayTransferResponse", $result], 'transfer');
        $data = $result['data'];
        if (is_array($data) && isset($data['status']) && in_array($data['status'], [0, 9])) {
           // status 0或9=处理中,6或8=失败(代付下单接口失败的订单没有回调)
            return $result;
        } else {
            throw new Exception($result['msg'] ?? "unknown error");
        }
    }

    public function notify()
    {
        $params = request()->param();
        Log::write(["GctpkpayTransferNotifyParams", $params], 'transfer');
        if (empty($params) || !isset($params['sign'])) {
            return 'requestParams error';
        }

        $data['merNo'] = $params['merNo'];
        $data['merOrderNo'] = $params['merOrderNo'];
        $data['orderNo'] = $params['orderNo'];
        $data['orderAmount'] = $params['orderAmount'];
        $data['payTime'] = $params['payTime'];
        $data['status'] = $params['status'];
        $data['resultCode'] = $params['resultCode'];
        $data['resultMsg'] = $params['resultMsg'];

        $sign = $this->makeSign($data, $this->config['sha256_key']);
        $flag = $sign === $params['sign'];
        $status = $params['status']; // status=7表示订单成功,status=2或8表示订单失败
        if ($flag) {
            Db::startTrans();
            try {
                $order = Db::name('withdrawals')->where('order_id', $params["merOrderNo"])->lock(true)->find();
                if (!empty($order) && $order['status'] == 2) {
                    $money = $order['original_money'];
                    $remark = '';
                    // tradeResult 0:申请成功 1:转账成功 2:转账失败 3:转账拒绝 4:处理中;
                    $money_log = new MoneyLogModel();
                    $user_id = $order['user_id'];
                    if ($status == 7) { //成功
                        $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_FROZEN, 'sub', '提现成功',
                            $remark, 2);
                        $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_WITHDRAWAL, 'add', '', '', 2);
                        $status = 4;
                    } else { //失败
//                        $title = '提现失败';
                        $remark = '提现转账失败';
//                        // 提现失败，退回冻结资金，退回手续费
//                        $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_FROZEN, 'sub', $title,
//                            $remark, 2);
//                        $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_BROKERAGE, 'add', $title,
//                            $remark, 2);
//                        if ($order['handling_fee'] > 0) {
//                            $money_log->changeMoney($user_id, $order['handling_fee'], MoneyLogModel::TYPE_BROKERAGE,
//                                'add', '用户提现手续费', '用户提现手续费', 2);
//                        }
                        $status = 5;
                    }
                    $remark .= '_'.$order['remark'];
                    Db::name('withdrawals')->where(['id' => $order['id']])->update([
                        'status'     => $status,
                        'check_time' => time(),
                        'remark'     => $remark,
                        "trade_no"   => $params['orderNo'],
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


    public static function sign($signSource, $key)
    {
        if (!empty($key)) {
            $signSource = $signSource."&key=".$key;
        }
        return md5($signSource);
    }


    //13位时间戳
    public function msectime()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float) sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return intval($msectime);
    }

    //生成代付加密签名
    function encrypt($data, $sha256_key, $mch_private_key)
    {


        //进行HamcSHA256加密
        $signA = $this->makeSign($data, $sha256_key);


        //再进行rsa加密
        $encrypted = '';
        $pem = chunk_split($mch_private_key, 64, "\n");
        $pem = "REDACTED\n";
        $private_key = openssl_pkey_get_private($pem);
        openssl_private_encrypt($signA, $encryptData, $private_key);


        //base64编码
        $signB = base64_encode($encryptData);
        return $signB;
    }

    //生成签名
    function makeSign($data, $sha256_key)
    {
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            if (!empty($v)) {
                $str .= (string) $k.'='.$v.'&';
            }
        }
        $str = rtrim($str, '&');
        //HamcSHA256加密
        $sign = hash_hmac("sha256", $str, $sha256_key);

        return $sign;

    }
}
