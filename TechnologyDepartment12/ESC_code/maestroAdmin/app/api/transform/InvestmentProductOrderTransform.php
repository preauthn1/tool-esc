<?php

namespace app\api\transform;

class InvestmentProductOrderTransform extends Transform
{

    public function transform($items): array
    {
        $settlement_time = $items['settlement_time'] ? date('Y/m/d H:i:s', $items['settlement_time']) : '';
        return [
            'id'                      => $items['id'],
            'user_id'                 => $items['user_id'],
            'level_id'                => $items['level_id'],
            'order_no'                => $items['order_no'],
            'cycle'                   => $items['cycle'],
            'end_time'                => $items['end_time'] ? date('Y/m/d H:i:s', $items['end_time']) : '',
            'count_down'              => $items['end_time'] - time(),
            'money'                   => sprintf4f($items['money']),
//            'investment_product_id'   => $items['investment_product_id'],
            'investment_product_name' => $items['investment_product_name'],
            'profit'                  => $items['profit'],
            'profit_money'            => sprintf4f($items['profit_money']),
            'profit_type'             => $items['profit_type'],
            'settlement_time'         => $settlement_time,
            'start_time'              => $items['start_time'] ? date('Y/m/d H:i:s', $items['start_time']) : '',
            'status'                  => $items['status'],
            'description'             => $items['product']['description'] ?? '',
        ];
    }
}
