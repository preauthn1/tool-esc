<?php


namespace pay;


use app\services\UserService;
use GuzzleHttp\Client;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class Dingpay extends Paybase
{
    public $url = "https://zflgw.cn/newbankPay/crtOrder.do";
    public $config = [];
    public function __construct($config) {
        if (empty($config['APP_ID']) || empty($config['APP_KEY'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = [
            'appId' => $this->config['APP_ID'],
            'appOrderNo' => $order['order_id'],
            'orderAmt' => sprintf('%.2f',$order['money']),
            'payId'  => 201,
            'sign' => ''
        ];
        unset($params['sign'],$params['notifyURL'],$params['jumpURL']);
        ksort($params);
        $params_str = '';
        foreach ($params as $key => $val) {
            $params_str .=  $key . '=' . $val.'&';
        }
        $params_str = $params_str.'key='.$this->config['APP_KEY'];
        $params['sign'] = strtoupper(md5($params_str));
        $params['notifyURL'] = $this->config['notify_url'];
        $params['jumpURL'] = $this->config['jump_url'];

        Log::write(["DingpayParams", $params], 'pay');
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
//        dump($contents);
        $result = json_decode($contents, true);
//        dump($result);die;
        if (empty($result)) {
            Log::write(["DingPayError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["DingPayResponse", $result], 'pay');

        if($result['code'] === '0000'){
            return redirect($result['data']['payUrl']);
        }else{
            $msg = isset($result['msg']) ? $result['msg'] : "unkown error";
        }
        throw new Exception($msg);
    }

    public function notify(){
        $params = request()->param();
        Log::write(["DingPayNotify", $params], 'pay');
        $sign = $params['sign'];
        unset($params['sign'],$params['gcashOrder'],$params['pay_id']);
        ksort($params);
        $signed = '';
        foreach($params AS $key => $value ){
            $signed .= $key.'='.$value.'&';
        }
        $signed .= 'key='.$this->config['APP_KEY'];
        if ($sign != strtoupper(md5($signed))){
            return json(msg('-1','',"Abnormal request"));
        }
        $info = Db::name('recharge')->where('order_id', $params['appOrderNo'])->where("status", 1)->lock()->find();
        if (!$info){
            return 'SUCCESS';
        }
        $data = [
            'username' => 'system',
            'action' => $params['orderStatus']=='00'?'审核充值:自动充值-成功':'审核充值:自动充值-驳回',
            'content' => "订单号:{$params['appOrderNo']}",
        ];
        \app\admin\model\OperationModel::create($data);
        if ($params['orderStatus']=='00'){
//            Db::name('home_user')->where('id',$info['user_id'])->inc('user_money',$info['money'])->update();
            UserService::addRechargeMoneyLog($info['user_id'], $params['payAmt'], $info['id']);
        }
        $ret = Db::name('recharge')->where(['id' => $info['id']])->update([
            'status' => $params['orderStatus']=='00'?2:3,
            'money'    => $params['payAmt'],
            'pay_time' => date('Y-m-d H:i:s',time()),
            'trade_no' => $params['orderNo']
        ]);
        return 'SUCCESS';
    }
}
