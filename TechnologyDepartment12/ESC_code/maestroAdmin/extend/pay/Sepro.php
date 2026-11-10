<?php

namespace pay;

use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Sepro extends Paybase{
    public $url = "https://pay.sepropay.com/sepro/pay/web";
    public $get_order_url = "https://pay.sepropay.com/query/order";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $params = [
            'mch_id'        => $this->config['app_id'],
            'notify_url' => $this->config['notify_url'],
            'page_url'  => $this->success(lang('付款成功')),
            'mch_order_no' => $order['order_id'],
            'pay_type'     => $this->config['pay_type'],
            'trade_amount' => intval($order['money']),
            'order_date' => timeToStr(),
            'goods_name' => 'recharge',
            'mch_return_msg' => strval($order['user_id']),
        ];
        $params['sign'] = $this->getSign($params);
        $params['sign_type'] = 'MD5';
        if (isset($this->config['pay_receive_bankcode']) && $this->config['pay_receive_bankcode']) {
            $params['bank_code'] = $this->config['pay_receive_bankcode'];
        }
//        dump($params);die;
        Log::write(["SeproParams", $params], 'pay');
        return view(__DIR__ . '/wap.html', ['gateway' => $this->url, 'params' => $params]);
    }

    public function find($order_id) {
        $params = array(
            'mch_id' => $this->config['app_id'],
            'mch_order_no' => $order_id,
            'sign_type' => 'MD5',
        );
        $params['sign'] = $this->getSign($params);
        Log::write(["SeproFindParams", $params], 'pay');
        try {
            $client = new Client();
            $response = $client->post($this->get_order_url, [
                'form_params' => $params
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response, 'find');
    }

    public function validateResponse($response, $type = 'pay') {
        $contents = $response->getBody()->getContents();
//        dump($contents);
        $result = json_decode($contents, true);
//        dump($result);die;
        if (empty($result)) {
            Log::write(["SeproError", $contents], 'pay');
            throw new Exception(lang('error'));
        }
        Log::write(["SeproResponse", $result], 'pay');
        if ($type == 'find') {
            if(isset($result['respCode']) && $result['respCode'] == 'SUCCESS'){
                return $result;
            }else{
                $msg = isset($result['result_message']) ? $result['result_message'] : "unkown error";
            }
        }else{
            if(isset($result['code']) && $result['code'] == 200){
                return redirect($result['data']['url']);
            }else{
                $msg = isset($result['message']) ? $result['message'] : "unkown error";
            }
        }
        throw new Exception(isset($result['message']) ? $result['message'] : "unkown error");
    }

    public function notify() {
        $params = request()->param();
        Log::write(["SeproNotifyParams", $params], 'pay');
        $sign = $this->getSign(myArrayFilter($params));
        if ($sign != $params["sign"]) {
            return 'sign error';
        }
//        $find = $this->find($params["mchOrderNo"]);
//        Log::write(["SeproFindNotifyParams", $find], 'pay');
        if (in_array($params["tradeResult"], [1])) {
            $remark = '';
            if (in_array($params['tradeResult'], [1])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = 'Payment Fail';
            }
            $trade_no = $params['orderNo'];
            $amount_true = $params['amount'];
            $ret = $this->complete($params["mchOrderNo"], $status, $remark, $trade_no, $amount_true);
            if ($ret === true) {
                return 'success';
            }
            return $ret;
        }
        return 'fail';
    }

    private function getSign($params) {
        unset($params['sign']);
        unset($params['signType']);
        unset($params['pay_id']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= "&key=" . $this->config['app_key'];
        return md5($md5str);
    }
}
