<?php

namespace app\admin\transform;

use app\api\transform\Transform;

class InvestmentProductSettlementTimeTransform extends Transform
{

    public function transform($items): array
    {
        return [
            'id'              => $items['id'],
            'settlement_time' => $items['settlement_time'],
            'create_time'     => $items['create_time'],
            'sort'            => $items['sort'],
            'status'          => $items['status'],
            'operate'         => showOperate($this->makeButton($items['id'])),
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
                'auth'     => 'investment_product_settlement_time/edit',
                'href'     => url('investment_product_settlementTime/edit', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon'     => 'fa fa-paste'
            ],
            '删除' => [
                'auth'     => 'investment_product_settlement_time/del',
                'href'     => "javascript:del(".$id.")",
                'btnStyle' => 'danger',
                'icon'     => 'fa fa-trash-o'
            ]
        ];
    }
}
