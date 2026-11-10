<?php

namespace app\api\transform;

class FinanceProductOrderTransform extends Transform
{

    public function transform($items): array
    {
        switch ($items['invest_period_type']) {
            case 'minute':
                $items['cycle_str'] = ceil($items['cycle']).'m';
                break;
            case 'hour':
                $hour = $items['cycle'];
                $hoursInt = floor($hour);
                $minutes = ceil(($hour - $hoursInt) * 60);
                $items['cycle_str'] = ($hoursInt ? $hoursInt.'h ' : '').($minutes ? $minutes.'m' : '');
                break;
            case 'day':
                $days = $items['cycle'];
                $daysInt = floor($days);
                $hoursDecimal = ($days - $daysInt) * 24;
                $hoursInt = floor($hoursDecimal);
                $minutes = ceil(($hoursDecimal - $hoursInt) * 60);
                $items['cycle_str'] = ($daysInt ? $daysInt.'d ' : '').($hoursInt ? $hoursInt.'h ' : '').($minutes ? $minutes.'m' : '');
                break;
        }
        return [
            'id'                   => $items['id'],
            'user_id'              => $items['user_id'],
            'level_id'             => $items['level_id'],
            'order_no'             => $items['order_no'],
            'cycle'                => $items['cycle'],
            'cycle_str'            => $items['cycle_str'],
            'invest_period_type'   => $items['invest_period_type'],
            'end_time'             => $items['end_time'] ? date('Y/m/d H:i:s', $items['end_time']) : '',
            'count_down'           => $items['end_time'] - time(),
            'price'                => sprintf4f($items['price']),
            'money'                => sprintf4f($items['money']),
//            'finance_product_id'   => $items['finance_product_id'],
            'finance_product_name' => $items['finance_product_name'],
            'profit'               => $items['profit'],
            'profit_money'         => sprintf4f($items['profit_money']),
            'profit_type'          => $items['profit_type'],
            'settlement_time'      => $items['settlement_time'] ? date('Y/m/d H:i:s', $items['settlement_time']) : '',
            'start_time'           => $items['start_time'] ? date('Y/m/d H:i:s', $items['start_time']) : '',
            'status'               => $items['status'],
            'fee_rate'             => $items['fee_rate'],
            'fee'                  => $items['fee'],
            'other_info'           => json_decode($items['other_info']) ?? [],
        ];
    }
}
