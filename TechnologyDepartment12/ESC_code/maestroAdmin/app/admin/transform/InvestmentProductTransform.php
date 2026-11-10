<?php

namespace app\admin\transform;

use app\api\transform\Transform;

class InvestmentProductTransform extends Transform
{

    public function transform($items): array
    {
        return [
            'id' => $items['id'],
            'cycle' => $items['profit_type'] == 1 ? $items['cycle'] : '--',
            'day_num' => $items['day_num'],
            'give_account_type' => $items['give_account_type'],
            'give_money' => i18n_sprintf($items['give_money']),
            'description' => $items['description'],
            'income_text' => $items['income_text'],
            'is_shortly_rebate' => $items['is_shortly_rebate'],
            'level_id' => $items['level_id'],
            'product_name' => $items['product_name'],
            'profit' => i18n_sprintf($items['profit']),
            'start_time' => $items['start_time'],
            'end_time' => $items['end_time'],
            'profit_type' => $items['profit_type'] == 1 ? lang('长期') : lang('短期'),
            'rebate_time' => $items['rebate_time'],
            'rebate_type' => $items['rebate_type'],
            'sales_status' => $items['sales_status'],
            'min_balance' => $items['min_balance'],
            'sort' => $items['sort'],
            'status' => $items['status'],
            'operate' => showOperate($this->makeButton($items['id'])),
        ];
    }


    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id): array
    {
        return [
            '编辑' => [
                'auth' => 'investment_product/add',
                'href' => url('investment_product/add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'investment_product/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }
}
