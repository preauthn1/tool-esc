<?php

namespace transfer;

use app\common\model\MoneyLogModel;
use GuzzleHttp\Client;
use pay\Paybase;
use think\facade\Db;
use think\Exception;
use think\facade\Log;

class Odpay extends Paybase
{
    public $config = [];

    public function __construct($config)
    {
        if (empty($config['merchantId']) || empty($config['key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order)
    {
        if (!$order['document_type']) {
            throw new Exception('用户提现账户类型未填写，请联系用户完善');
        }
        if (!$order['document_id']) {
            throw new Exception('用户提现账户账号未填写，请联系用户完善');
        }
        $params = [
            'merchantId' => $this->config['merchantId'],
            'country'    => 2, // 默认填2，巴西
            'pixType'    => $order->document_type, // EMAIL/PHONE/CPF/CNPJ/RANDOM
            'orderNo'    => $order->order_id,
            'amount'     => sprintf("%.2f", $order['original_money']),
            'cert'       => $order->document_type == 'CPF' ? $order->document_id : '12345678901', // 当pixType为CPF的时候，cert和accountNum都填cpf帐号
            'accountNum' => $order->document_id,
            'notifyUrl'  => $this->config['payout_notification_url'],
        ];

        $params['sign'] = $this->getSignature($this->ascii_params($params), $this->config['key']);

        Log::write(["OdpayTransferParams", $params], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->config['api_url'].'/payout/createOrder', [
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

    public function validateResponse($response)
    {
        $contents = $response->getBody()->getContents();
        $result = json_decode($contents, true);
        if (empty($result)) {
            Log::write(["OdpayTransferError", $contents], 'transfer');
            throw new Exception(lang('error'));
        }
        Log::write(["OdpayTransferResponse", $result], 'transfer');

        if (is_array($result) && isset($result['code']) && $result['code'] == 200) {
            return $result;
        } else {
            $message = $result['msg'] ?? 'unknown';
        }
        throw new Exception($message);
    }

    public function notify()
    {
        $params = request()->param();
        Log::write(["OdpayTransferNotifyParams", $params], 'transfer');
        if (!isset($params['sign'])) {
            return 'requestParams error';
        }
        $data = [
            'merchantId' => $params['merchantId'],
            'orderNo'    => $params['orderNo'],
            'payNo'      => $params['payNo'],
            'channel'    => $params['channel'],
            'amount'     => $params['amount'],
            'amountReal' => $params['amountReal'],
            'status'     => $params['status'],
        ];
        if (isset($params['failMsg'])) {
            $data['failMsg'] = $params['failMsg'];
        }
        $sign = $this->getSignature($this->ascii_params($data), $this->config['key']);
        $flag = $sign == $params['sign'];
        if ($flag) {
            $status = $params['status'];
            Db::startTrans();
            try {
                $order = Db::name('withdrawals')->where('order_id', $params["orderNo"])->lock(true)->find();
                if (!empty($order) && (($order['status'] == 2) || (($status == 3||$status == 5) && $order['status'] == 4))) {//之前成功，后面失败
                    $money = $params['amountReal'];
                    $remark = '';
                    // 订单状态 “1” 交易中 “2”： 成功 “3”：失败 5：“退款， 成功转失败”
                    $money_log = new MoneyLogModel();
                    $user_id = $order['user_id'];
                    if ($status == 2) { //成功
                        $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_FROZEN, 'sub', '提现成功',
                            $remark, 2);
                        $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_WITHDRAWAL, 'add', '', '', 2);
                        $status = 4;
                    } else { //失败
                        $remark = '提现转账失败';
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
                        "trade_no"   => $params['payNo'],
                        "pay_time"   => timeToStr(),
                        "result"     => json_encode($params)
                    ]);
                }
                // 提交事务
                Db::commit();
                // 直接返回字符串 “success”表示成功 否则失败 (“success”代表success字符)
                return 'success';
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
     * 自定义ascii排序 返回字符串
     * @param  array  $params
     * @return string
     */
    function ascii_params($params = array())
    {
        if (!empty($params)) {
            $p = ksort($params);
            if ($p) {
                $str = '';
                foreach ($params as $k => $val) {
                    $str .= $k.'='.$val.'&';
                }
                $strs = rtrim($str, '&');
                return $strs;
            }
        }
        return '参数错误';
    }

    /**
     * @brief 使用HMAC-SHA1算法生成oauth_signature签名值
     *
     * @param  $str   string 源串
     * @param $keystring 密钥
     *
     * @return string
     */
    function getSignature($str, $key)
    {
        $signature = "";
        if (function_exists('hash_hmac')) {
            $signature = base64_encode(hash_hmac("sha1", $str, $key, true));
        } else {
            $blocksize = 64;
            $hashfunc = 'sha1';
            if (strlen($key) > $blocksize) {
                $key = pack('H*', $hashfunc($key));
            }
            $key = str_pad($key, $blocksize, chr(0x00));
            $ipad = str_repeat(chr(0x36), $blocksize);
            $opad = str_repeat(chr(0x5c), $blocksize);
            $hmac = pack(
                'H*', $hashfunc(
                    ($key ^ $opad).pack(
                        'H*', $hashfunc(
                            ($key ^ $ipad).$str
                        )
                    )
                )
            );
            $signature = base64_encode($hmac);
        }
//    echo($signature);
        return $signature;
    }
}
