<?php

namespace app\admin\transform;

use app\api\transform\Transform;
use app\common\model\UserModel;

class EconnoisseurTransform extends Transform
{

    public function transform($items): array
    {
        $top_leader = UserModel::getTopLeader($items['id']);
        $top_leader_remark = '';
        if ($top_leader) {
            $top_leader_remark = $top_leader['remark'];
        }
        return [
            'id'                            => $items['id'],
            'user_name'                     => $items['user_name'],
            'user_money'                    => sprintf4f($items['user_money']),
            'balance_money'                 => sprintf4f($items['balance_money']),
            'frozen_money'                  => sprintf4f($items['frozen_money']),
            'recharge_money'                => sprintf4f($items['recharge_money']),
            'total_withdrawal'              => $items['total_withdrawal'],
            'total_purchased_finance_money' => $items['total_purchased_finance_money'],
            'finance_product_order_count'   => $items['finance_product_order_count'],
            'top_leader_remark'             => $top_leader_remark,
            'recharge_withdrawal'           => sprintf4f($items['recharge_money'] - $items['total_withdrawal']),
            'is_xuni'                       => $items['is_xuni'] == 1 ? '是' : '否',
            'is_xuni_text'                  => $items['is_xuni'] == 1 ? '<span style="color:red">是</span>' : '否',
        ];
    }
}
