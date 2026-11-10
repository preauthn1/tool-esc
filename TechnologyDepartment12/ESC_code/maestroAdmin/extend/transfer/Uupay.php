<?php

namespace transfer;

use app\common\model\MoneyLogModel;
use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class Uupay extends Transferbase
{
    public $config = [];

    public function __construct($config)
    {
        if (empty($config['merchant_no']) || empty($config['md5_key']) || empty($config['des_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order)
    {
        //业务请求参数
        $params = [];
        $params['method'] = "payout.order.create";
        $params['amount'] = round($order["original_money"], 2);
        $params['merTransNo'] = $order['order_id'];
        $params['bankName'] = $order['bank_name']; // 银行卡号
        $params['bankRef'] = $order['bank_number'];
        $params['userName'] = $order["true_name"];

//排序，组装加密参数
        $url_params = $this->PARAM_BUILD($params);
//out>> amount=1.00&method=payout.order.create&merTransNo=1668419396633&bankName=HSBC&userName=RYAN HAUS SAPI DE CV&bankRef=021180040661524978
//AES加密
        $aes_str = openssl_encrypt($url_params, 'aes-256-ecb', $this->config['des_key'], 0);
//out>>> Afs0aZgsVQrVW6Qkz2FIGiNmjZqCFRo/inn00LH8KMwalWFupVQkENS80mdx4Vyn222O7KDzM242rvNsWQ6eAlmBx/9Zi6VtbFArEp6/PaWYNpAO6VZs2CkV5HrJS/idrgVpQVfxi8YX7FmCWR7O02qyBOqJHnxkjtMipIfRig1dJmG8l9M9Udk1uTQg/5sO

//MD5加密
        $sign = md5($aes_str . $this->config['md5_key']);
//out>>> 3a20d25a1a56c655be967d6227afee37

//请求参数
        $requestParams = [];
        $requestParams['params'] = $aes_str;
        $requestParams['sign'] = $sign;
        $requestParams['merchantNo'] = $this->config['merchant_no'];
//out>>> {"params":"Afs0aZgsVQrVW6Qkz2FIGiNmjZqCFRo/inn00LH8KMwalWFupVQkENS80mdx4Vyn222O7KDzM242rvNsWQ6eAlmBx/9Zi6VtbFArEp6/PaWYNpAO6VZs2CkV5HrJS/idrgVpQVfxi8YX7FmCWR7O02qyBOqJHnxkjtMipIfRig1dJmG8l9M9Udk1uTQg/5sO","$sign":"3a20d25a1a56c655be967d6227afee37","merchantNo":"Test1111"}
//请求

//        dump($postdata);die;
        Log::write(["UupayTransferParams", $params], 'transfer');
        Log::write(["UupayTransferParams", $requestParams], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->config['api_url'], [
                'json'  => $requestParams,
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
            Log::write($contents, "UupayTransferError");
            throw new Exception(lang('error'));
        }

        Log::write(["UupayTransferResponse", $result], 'transfer');
        if (is_array($result) && $result['success'] === true) {
            return $result;
        } else {
            throw new Exception($result['message'] ?? "unknown error");
        }
    }

    public function notify()
    {
        $params = request()->param();
        Log::write(["UupayTransferNotifyParams", $params], 'transfer');
        if (empty($params) || !isset($params['callbackParams'])) {
            return 'requestParams error';
        }

        $des_key = $this->config['des_key'];//请在商户管理界面获取
//获取post body请求数据
//out>> {"callbackParams":"RKEKM2N481MuMSdXHpuaxNk45SOCw06Cu3br4D1+ieLSgcMRvRYZzaISudpRts4ncyT7yPYAcgd/jM5OtjBOBbWXVi5NMMrDdyy2VxemBVGEWgbMcW8B+IrsRp3NqPzrNMoqSofzicO5nSJZvV8ORxM8QmXmC9sevXlAxrKnCM5tgwabiq/u7x4BdmNQ1JN/"}
//加密请求字符串
        $callbackParams = $params['callbackParams'];
//out>> RKEKM2N481MuMSdXHpuaxNk45SOCw06Cu3br4D1+ieLSgcMRvRYZzaISudpRts4ncyT7yPYAcgd/jM5OtjBOBbWXVi5NMMrDdyy2VxemBVGEWgbMcW8B+IrsRp3NqPzrNMoqSofzicO5nSJZvV8ORxM8QmXmC9sevXlAxrKnCM5tgwabiq/u7x4BdmNQ1JN/
//解密
        $params = openssl_decrypt($callbackParams,'aes-256-ecb',$des_key,0);
        if (!$params) {
            return 'params error';
        }
//out>> {"amount":380.00,"merTransNo":"WT2211040816276801","merUserNo":"2022-TOP13","message":"succeed","referenceId":"24756","success":true}
        $params = json_decode($params, true);
        $status = $params['success']; // true 成功 false 失败
        Db::startTrans();
        try {
            $order = Db::name('withdrawals')->where('order_id', $params["merTransNo"])->lock(true)->find();
            if (!empty($order) && $order['status'] == 2) {
                $money = $order['original_money'];
                $remark = '';
                // tradeResult 0:申请成功 1:转账成功 2:转账失败 3:转账拒绝 4:处理中;
                $money_log = new MoneyLogModel();
                $user_id = $order['user_id'];
                if ($status) { //成功
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
                $remark .= '_'.$order['remark'];
                Db::name('withdrawals')->where(['id' => $order['id']])->update([
                    'status'     => $status,
                    'check_time' => time(),
                    'remark'     => $remark,
                    "trade_no"   => $params['referenceId'],
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

        return 'fail';
    }

    public function PARAM_BUILD($data){
        ksort($data);
        $url = "";
        foreach ($data as $key => $val) {
            if ($val != '') {
                if($url==""){
                    $url = $url . $key . "=" . $val;
                }else{
                    $url = $url . "&" . $key . "=" . $val;
                }
            }
        }
        return $url;
    }

    // 检查银行卡是否有效
    public function checkBankInfo($bank_number, $bank_name)
    {
        //业务请求参数
        $params = [];
        $params['method'] = "payout.bank.resolve";
        $params['bankRef'] = $bank_number; // 银行卡号
        $params['bankName'] = $bank_name; // 银行名称（银行名称或银行代码都可以）

        $url_params = $this->PARAM_BUILD($params);
        $aes_str = openssl_encrypt($url_params, 'aes-256-ecb', $this->config['des_key'], 0);
        $sign = md5($aes_str . $this->config['md5_key']);
        $requestParams = [];
        $requestParams['params'] = $aes_str;
        $requestParams['sign'] = $sign;
        $requestParams['merchantNo'] = $this->config['merchant_no'];
        Log::write(["UupayCheckBankParams", $params], 'transfer');
        Log::write(["UupayCheckBankParams", $requestParams], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->config['api_url'], [
                'json'  => $requestParams,
            ]);
            $contents = $response->getBody()->getContents();
            $result = json_decode($contents, true);
            return $result;
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response);
    }
}
