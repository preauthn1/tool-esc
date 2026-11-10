<?php

namespace app\api\transform;

class PaymentChannelTransform extends Transform
{

    public function transform($items): array
    {
        return [
            'id'                  => $items['id'],
            'show_name'           => $items['name'],
            'min_extract_money'   => $items['min_withdraw_money'],
            'max_extract_money'   => $items['max_withdraw_money'],
            'fast_amount'         => $items['fast_amount'],
            'withdraw_money_type' => $items['withdraw_money_type'], // 1-用户自由输入；2-固定金额
            'exchange_rate'       => $items['exchange_rate'],
        ];
    }
}
