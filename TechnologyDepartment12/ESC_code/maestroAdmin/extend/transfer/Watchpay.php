<?php

namespace transfer;

use app\common\model\MoneyLogModel;
use GuzzleHttp\Client;
use think\facade\Db;
use think\Exception;
use think\facade\Log;

class Watchpay extends Transferbase
{
    public $url = "https://api.watchglb.com/pay/transfer";
    public $config = [];

    public function __construct($config)
    {
        if (empty($config['withdrawal_merchant_key']) || empty($config['mch_id'])) {
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


        $apply_date = date('Y-m-d H:i:s');
        $bank_code = 'PIXPAY'; // 收款银行代码 入款网银才需要填写银行编码，代付固定填 PIXPAY 即可
        $document_id = $order['document_id']; // 巴西代付和 receive_account 一样均填收款人的PIX账号；

        $document_type = $order['document_type']; // 证件类型

        $mch_id = $this->config["mch_id"]; // 商户代码

        $mch_transferId = $order['order_id']; // 商家转账订单号, 保证每笔订单唯一

        $receive_account = $order["document_id"]; // 收款银行账号, 银行账号(巴西PIX代付填对应的类型账号)

        $receive_name = $order["true_name"]; // 收款银行户名

        $transfer_amount = round($order["original_money"], 2); // 转账金额,以当地货币 精确到元

        $sign_type = 'MD5'; // 固定值MD5，不参与签名

        $back_url = $this->config['back_url'];


        $signStr = "";

        $signStr = $signStr."apply_date=".$apply_date."&";
        if ($back_url != "") {
            $signStr = $signStr."back_url=".$back_url."&";
        }

        $signStr = $signStr."bank_code=".$bank_code."&";
        $signStr = $signStr."document_id=".$document_id."&";
        $signStr = $signStr."document_type=".$document_type."&";

        $signStr = $signStr."mch_id=".$mch_id."&";

        $signStr = $signStr."mch_transferId=".$mch_transferId."&";

        $signStr = $signStr."receive_account=".$receive_account."&";

        $signStr = $signStr."receive_name=".$receive_name."&";

        $signStr = $signStr."transfer_amount=".$transfer_amount;


        $reqUrl = "";

        $merchant_key = $this->config['withdrawal_merchant_key'];//支付秘钥

        $sign = $this->sign($signStr, $merchant_key);

        $postdata = [
            'apply_date'      => $apply_date,
            'back_url'        => $back_url,
            'bank_code'       => $bank_code,
            'document_type'   => $document_type,
            'document_id'     => $document_id,
            'mch_id'          => $mch_id,
            'mch_transferId'  => $mch_transferId,
            'receive_account' => $receive_account,
            'receive_name'    => $receive_name,
            'transfer_amount' => $transfer_amount,
            'sign_type'       => $sign_type,
            'sign'            => $sign
        ];

//        dump($postdata);die;
        Log::write(["WatchpayTransferParams", $postdata], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'form_params' => $postdata,
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
            Log::write($contents, "WatchpayTransferError");
            throw new Exception(lang('error'));
        }

        Log::write(["WatchpayTransferResponse", $result], 'transfer');
        if (isset($result['respCode']) && $result['respCode'] === 'SUCCESS') {
            return $result;
        } else {
            throw new Exception($result['errorMsg'] ?? "unknown error");
        }
    }

    public function notify()
    {
        $params = request()->param();
        Log::write(["WatchpayTransferNotifyParams", $params], 'transfer');
        if (empty($params) || !isset($params['sign'])) {
            return 'requestParams error';
        }
        $merchant_key = $this->config['withdrawal_merchant_key']; // 代付密钥
        $tradeResult = $params["tradeResult"]; // 订单状态 1成功 2失败
        $merTransferId = $params["merTransferId"]; // 商家转账单号
        $merNo = $params["merNo"]; // 商户代码
        $tradeNo = $params["tradeNo"]; // 平台订单号
        $transferAmount = $params["transferAmount"]; // 代付金额
        $applyDate = $params["applyDate"]; // 订单时间
        $version = $params["version"]; // 版本号 默认1.0
        $respCode = $params["respCode"]; // 回调状态 默认SUCCESS
        $sign = $params["sign"]; //签名
//        $signType = $params["signType"]; //固定值MD5，不参与签名

        $signStr = "";
        $signStr = $signStr."applyDate=".$applyDate."&";
        $signStr = $signStr."merNo=".$merNo."&";
        $signStr = $signStr."merTransferId=".$merTransferId."&";
        $signStr = $signStr."respCode=".$respCode."&";
        $signStr = $signStr."tradeNo=".$tradeNo."&";
        $signStr = $signStr."tradeResult=".$tradeResult."&";
        $signStr = $signStr."transferAmount=".$transferAmount."&";
        $signStr = $signStr."version=".$version;

        // applyDate=2024-12-26 15:33:37
        //&merNo=887003002&merTransferId=W2612202415270394611128&respCode=SUCCESS&tradeNo=6265562676&tradeResult=1transferAmount=1.00&version=1.0


        //applyDate=2024-12-26 15:33:37
        //&merNo=887003002
        //&merTransferId=W2612202415270394611128
        //&respCode=SUCCESS
        //&tradeNo=6265562676
        //&tradeResult=1
        //&transferAmount=1.00
        //&version=1.0

        $flag = $this->validateSignByKey($signStr, $merchant_key, $sign);
        if ($flag) {
            Db::startTrans();
            try {
                $order = Db::name('withdrawals')->where('order_id', $params["merTransferId"])->lock(true)->find();
                if (!empty($order) && ($order['status'] == 2 || $order['status'] == 4)) {
                    // 会出现先回调成功，后回调失败的情况,银行先返回成功，后面打款发现账号有问题就返回失败了
                    $money = $order['original_money'];
                    $remark = '';
                    // tradeResult 0:申请成功 1:转账成功 2:转账失败 3:转账拒绝 4:处理中;
                    $money_log = new MoneyLogModel();
                    $user_id = $order['user_id'];
                    if ($tradeResult == 1) { //成功
                        $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_FROZEN, 'sub', '提现成功',
                            $remark, 2);
                        $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_WITHDRAWAL, 'add', '', '', 2);
                        $status = 4;
                    } else { //失败
                        if ($order['status'] == 4) {
                            $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_FROZEN, 'add', '提现成功后失败',
                                $remark, 2);
                        }
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
                        "trade_no"   => $params['tradeNo'],
                        "pay_time"   => timeToStr(),
                        "result"     => json_encode($params)
                    ]);
                }
                // 提交事务
                Db::commit();
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

    public static function sign($signSource, $key)
    {
        if (!empty($key)) {
            $signSource = $signSource."&key=".$key;
        }
        return md5($signSource);
    }


    public static function validateSignByKey($signSource, $key, $retsign)
    {
        if (!empty($key)) {
            $signSource = $signSource."&key=".$key;
        }
        $signkey = md5($signSource);
        if ($signkey == $retsign) {
            return true;
        }
        return false;
    }

}
