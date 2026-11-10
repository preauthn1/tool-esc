<?php

namespace app\api\transform;

class InvestmentProductTransform extends Transform
{

    public function transform($items): array
    {
        return [
            'id'                => $items['id'],
            'cycle'             => $items['cycle'],
//            'day_num'           => $items['day_num'],
//            'give_account_type' => $items['give_account_type'],
//            'give_money'        => sprintf4f($items['give_money']),
//            'is_shortly_rebate' => $items['is_shortly_rebate'],
//            'level_id'          => $items['level_id'],
            'product_name'      => $items['product_name'],
            'profit'            => sprintf4f($items['profit']),
//            'start_time'        => $items['start_time'],
//            'end_time'          => $items['end_time'],
            'profit_type'       => $items['profit_type'],
//            'rebate_time'       => $items['rebate_time'],
//            'rebate_type'       => $items['rebate_type'],
//            'sales_status'      => $items['sales_status'],
            'min_balance'      => $items['min_balance'],
            'description'       => $items['description'],
            'income_text'       => $items['income_text'],
            'income'            => ''
        ];
    }
}
