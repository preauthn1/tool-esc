<?php

namespace app\api\transform;

use app\admin\model\ConfigModel;
use app\admin\transform\Transform;

class PaymentPlatformTransform extends Transform
{
    public function transform($items): array
    {
        $offline_pay_account = [];
        $config = json_decode($items['config'], true);
        if(!empty($config)){
            if (array_key_exists('class_name', $config) && $config['class_name'] == 'Offlinepay') {
                $offline_pay_account = json_decode(ConfigModel::getConfigValue('offline_pay_account'), true);
                if (empty($offline_pay_account)) {
                    $offline_pay_account = [];
                }
            }
        }
        return [
            'id' => $items['id'],
            'name' => $items['name'],
            'exchange_rate' => sprintf4f($items['exchange_rate']),
            'withdraw_money_type' => $items['withdraw_money_type'],
            'min_withdraw_money' => sprintf4f($items['min_withdraw_money']),
            'max_withdraw_money' => sprintf4f($items['max_withdraw_money']),
            'fast_amount' => $items['fast_amount'],
            'currency_type' => $items['currency_type'],
            'currency_code' => $items['currency_code'],
            'unit' => $items['unit'],
            'image' => $items['image'],
            'min_recharge_money' => sprintf4f($items['min_recharge_money']),
            'instruction' => $items['instruction'],
            'offline_pay_account' => $offline_pay_account
        ];
    }
}
