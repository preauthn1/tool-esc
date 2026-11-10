<?php


namespace transfer;


use GuzzleHttp\Client;
use think\facade\Db;
use think\Exception;
use think\facade\Log;

class Dingpay extends Transferbase
{
    public $url = "https://zflgw.cn//newbankPay/crtAgencyOrder.do";
    public $config = [];
    public function __construct($config) {
        if (empty($config['APP_ID']) || empty($config['APP_KEY'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = [
            'appId'     => $this->config['APP_ID'],
            'appOrderNo'=> $order['order_id'],
            'orderAmt'  => $order['money'],
            'payId'     => 101,
            'accNo'     => $order['bank_number'],
            'accName'   => $order['true_name'],
            'bankName'  => $order['bank_name'],
            'bankCode'  => $order['bank_name'],
            'notifyURL' => $this->config['back_url'],
            'sign'      => ''
        ];
        unset($params['sign'],$params['notifyURL'],$params['bankCode']);
        ksort($params);
        $params_str = '';
        foreach ($params as $key => $val) {
            $params_str .=  $key . '=' . $val.'&';
        }
        $params_str = $params_str.'key='.$this->config['APP_KEY'];
        $params['sign'] = strtoupper(md5($params_str));
        $params['notifyURL'] = $this->config['back_url'];
        Log::write(["DingPayTransferParams", $params], 'transfer');

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

    public function validateResponse($response) {
        $contents = $response->getBody()->getContents();
        $result = json_decode($contents, true);
        if (empty($result)) {
            Log::write($contents, "DingPayTransferError");
            throw new Exception(lang('error'));
        }

        Log::write(["DingPayTransferResponse", $result], 'transfer');
        if(isset($result['code']) && $result['code']=="0000"){
            return true;
        }else{
            return isset($result['msg']) ? $result['msg'] : "unkown error";
        }
    }

    public function notify(){
        $params = request()->param();
        Log::write(["DingPayTransferNotify", $params], 'pay');
        $sign = $params['sign'];
        unset($params['sign'],$params['signType'],$params['channel']);
        ksort($params);
        foreach($params AS $key => $value ){
            $signed .= $key.'='.$value.'&';
        }
        $signed .= 'key='.$this->config['APP_KEY'];
        if ($sign != strtoupper(md5($signed))){
            return json(msg('-1','',"Abnormal request"));
        }
        $info = Db::name('withdrawals')->where('order_id', $params['appOrderNo'])->where("status", 4)->lock()->find();
        if (!$info){
            return 'SUCCESS';
        }
        $data = [
            'username' => 'system',
            'action' => $params['orderStatus']=='00' ? '审核提现:自动付款-成功' : '审核提现:自动付款-驳回',
            'content' => "订单号:{$params['appOrderNo']}",
        ];
        \app\admin\model\OperationModel::create($data);
//        if ($params['orderStatus']!='02'){
//            Db::name('home_user')->where('id',$info['user_id'])->inc('user_money',$info['money'])->update();
//        }
        $ret = Db::name('withdrawals')->where(['id' => $info['id']])->update([
            'status'    => $params['orderStatus']=='02' ? 2 : 3,
            'pay_time'  => date('Y-m-d H:i:s',time()),
            'trade_no'  => $params['orderNo']
        ]);
        return 'SUCCESS';
    }

}
