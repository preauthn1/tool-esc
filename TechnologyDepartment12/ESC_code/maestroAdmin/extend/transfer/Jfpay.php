<?php

namespace transfer;

use app\common\model\BankModel;
use app\common\model\UserModel;
use GuzzleHttp\Client;
use think\Exception;
use think\facade\Log;

class Jfpay extends Transferbase{
    public $url = "https://api.jfynp168.com/withdrawal/creatWithdrawal";
    public $get_order_url = "https://api.jfynp168.com/withdrawal/queryOrder";
    public $config = [];
    public function __construct($config) {
        if (empty($config['app_id']) || empty($config['app_key'])) {
            throw new Exception('config error');
        }
        $this->config = $config;
    }

    public function pay($order) {
        $mobile = UserModel::where('id', $order['user_id'])->value('mobile');
        $params = [
            "appid" => $this->config['app_id'],
            "out_trade_no" => $order['order_id'],
            'bank_type' => 2,
            "money" => sprintf("%.2f", $order['money']),
            "account" => $order['bank_number'],
            "name" => $order['true_name'],
            "bank_id" => $this->getBankId($order['bank_name']),
            "ifsc" => !isset($order['bank_kh']) || empty($order['bank_kh']) ? $this->getBankIfsc($order['bank_name']) : $order['bank_kh'],
            "remark" => strval($mobile),
            "callback" => $this->config['notify_url'],
        ];

        $params['sign'] = $this->getSign($params);
        dump($params);
        Log::write(["JfpayTransferParams", $params], 'transfer');
        try {
            $client = new Client();
            $response = $client->post($this->url, [
                'json' => $params,
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $response = $ex->getResponse();
            if (is_null($response)) {
                throw new Exception($ex->getMessage());
            }
        }
        return $this->validateResponse($response);
    }

    public function find($order_id) {
        $params = array(
            'appid'        => $this->config['app_id'],
            'out_trade_no' => $order_id,
        );
        $params['sign'] = $this->getSign($params);
        Log::write(["JfpayFindParams", $params], 'transfer');
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
            Log::write(["JfpayTransferError", $result], 'transfer');
            throw new Exception(lang('error'));
        }

        Log::write(["JfpayTransferResponse", $result], 'transfer');
        if(isset($result['code']) && ( in_array($result['code'], [1]) || strexists($result['msg'], 'repeated') )){
            return $type == 'pay' ? true : $result['data'];
        }else{
            throw new Exception(isset($result['msg']) ? $result['msg'] : "unkown error");
        }
    }

    public function notify() {
        $params = request()->param();
        Log::write(["JfpayTransferNotifyParams", $params], 'transfer');
        if (empty($params)) {
            return 'requestParams error';
        }
        $data = isset($params['data']) ? $params['data'] : [];
        if (empty($data)) {
            return 'data empty error';
        }

        $sign = $this->getSign(myArrayFilter($data));
        if ($sign != $data["sign"]) {
            return 'sign error';
        }
        $find = $this->find($data["out_trade_no"]);
        Log::write(["JfpayFindNotifyParams", $find], 'transfer');
        if ($find && in_array($find["status"], [1, 3])) {
            $remark = '';
            if (in_array($find['status'], [1])) { //成功
                $status = 2;
            }else{ //失败
                $status = 3;
                $remark = '提现转账失败';
            }
            $trade_no = $data['order_no'];
            $ret = $this->complete($data["out_trade_no"], $status, $remark, $trade_no);
            if ($ret === true) {
                return 'success';
            }
            return $ret;
        }
        return 'error';
    }

    private function getSign($params) {
        unset($params['sign']);
        unset($params['channel']);
        ksort($params);
        $md5str = urldecode(http_build_query($params));
        $md5str .= "&key=" . $this->config['app_key'];
        return strtoupper(md5($md5str));
    }

    private function getBankIfsc($code) {
        $extra = BankModel::where('bank_code', $code)->value('extra');
        return $extra ?? $code;
    }
    private function getBankId($code) {
        $_bank = [
            "ICIC" => 1, "AXIS" => 2, "HDFC" => 3, "CENTRALBK" => 4, "CBI" => 4,
            "SBIN" => 5, "PNB" => 6, "PUNB" => 6, "BOI" => 7, "BOB" => 8, "CANBK" => 9, 'CB' => 9,
            "RBI" => 10,
            "UBI" => 11,
            "IOB" => 12,
            "KOTAKBANK" => 13,
            "KKBK" => 13,
            "FEDERALBNK" => 14,
            "FDRL" => 14,
            "BANDHANBNK" => 15,
            "IDBI" => 16,
            "SOUTHBANK" => 17,
            "DCBBANK" => 18,
            "INDIANB" => 19,
            "IB" => 19,
            "SyndicateBank" => 20,
            "SYNB" => 20,
            "KARURVYSYA" => 21,
            "UNIONBANK" => 22,
            "UNBK" => 22,
            "IDFCFIRSTB" => 23,
            "AndhraBank" => 24,
            "ANDB" => 24,
            "KTKBANK" => 25,
            "ICICcorporatebank" => 26,
            "UCOBANK" => 27,
            "UCO" => 27,
            "YESBANK" => 28,
            "NSE" => 28,
            "StandardCharteredBank" => 29,
            "AllahabadBank" => 30,
            "ALLA" => 30,
            "MAHABANK" => 31,
            "DenaBank" => 32,
            "PSB" => 33,
            "KVGB" => 34,
            "UjjivanSmallFinanceBank" => 35,
            "UGB" => 36,
            "UtkarshSmallFinanceBank" => 37,
            "UttarBiharGraminBank" => 38,
            "UttarakhandGraminBank" => 39,
            "UBKGB" => 40,
            "VKGB" => 41,
            "PunjabGraminBank" => 42,
            "PurvanchalBank" => 43,
            "RajasthanMarudharaGraminBank" => 44,
            "RBLBANK" => 45,
            "SaptagiriGrameenaBank" => 46,
            "SarvaHaryanaGraminBank" => 47,
            "SaurashtraGraminBank" => 48,
            "SmallIndustriesDevelopmentBankofIndia" => 49,
            "SubhadraLocalAreaBankLtd" => 50,
            "SuryodaySmallFinanceBankLtd" => 51,
            "TamilNaduGramaBank" => 52,
            "TamilnadMercantileBankLtd" => 53,
            "TelanganaGrameenaBank" => 54,
            "TripuraGraminBank" => 55,
            "LakshmiVilasBank" => 56,
            "MadhyaPradeshGraminBank" => 57,
            "MadhyanchalGraminBank" => 58,
            "MaharashtraGraminBank" => 59,
            "ManipurRuralBank" => 60,
            "MeghalayaRuralBank" => 61,
            "MizoramRuralBank" => 62,
            "Nagaland Rural Bank" => 63,
            "NainitalBank" => 64,
            "NationalBankforAgricultureandRuralDevelopment" => 65,
            "NationalHousingBank" => 66,
            "NorthEastSmallFinanceBank" => 67,
            "NSDLPaymentsBankLimited" => 68,
            "OdishaGramyaBank" => 69,
            "PaschimBangaGraminBank" => 70,
            "PaytmPaymentsBank" => 71,
            "PYTM" => 71,
            "PrathamaUPGraminBank" => 72,
            "PuduvaiBharathiarGramaBank" => 73,
            "IDBIBank" => 74,
            "IndiaPostPaymentsBank" => 75,
            "IPPB" => 75,
            "lndusIndBank" => 76,
            "J&KGrameenBank" => 77,
            "Jammu&KashmirBank" => 78,
            "Jana Small Finance Bank" => 79,
            "JharkhandRajyaGraminBank" => 80,
            "JioPaymentsBank" => 81,
            "KarnatakaGraminBank" => 82,
            "KarnatakaVikasGrameenaBank" => 83,
            "KVBL" => 83,
            "KashiGomtiSamyutGraminBank" => 84,
            "KeralaGraminBank" => 85,
            "CapitalSmallFinanceBankLimited" => 86,
            "ChaitanyaGodavariGrameenaBank" => 87,
            "ChhattisgarhRajyaGraminBank" => 88,
            "CUB" => 89,
            "CoastalLocalAreaBankLimited" => 90,
            "CSBBANK" => 91,
            "CSB" => 91,
            "DakshinBiharGraminBank" => 92,
            "DhanlaxmiBank" => 93,
            "EllaquaiDehatiBank" => 94,
            "EquitasSmallFinanceBank" => 95,
            "ESAFSmallFinanceBank" => 96,
            "EximBank" => 97,
            "FincareSmallFinanceBank" => 98,
            "FinoPaymentsBank" => 99,
            "HimachalPradeshGraminBank" => 100,
            "AdityaBirlaPaymentsBank" => 101,
            "AirtelPaymentsBank" => 102,
            "AndhraPradeshGrameenaVikasBank" => 103,
            "AndhraPragathiGrameenaBank" => 104,
            "APGB" => 104,
            "ArunachalPradeshRuralBank" => 105,
            "AryavartBank" => 106,
            "AssamGraminVikashBank" => 107,
            "AUSmallFinanceBank" => 108,
            "BangiyaGraminVikashBank" => 109,
            "BarodaGujaratGraminBank" => 111,
            "BarodaRajasthanKshetriyaGraminBank" => 112,
            "BarodaUttarPradeshGraminBank" => 113,
//            "DBSS" => "DBSS0IN0811",
        ];
        return isset($_bank[$code]) ? $_bank[$code] : 1;
    }
}
