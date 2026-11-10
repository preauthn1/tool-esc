<?php

namespace app\admin\transform;

use app\api\transform\Transform;
use app\common\model\UserModel;
use app\common\repository\FinanceProductOrderRepository;

class InvestmentProductOrderTransform extends Transform
{

    public function transform($items): array
    {
        $top_leader = UserModel::getTopLeader($items['user']['id']);
        $top_leader_remark = '';
        if ($top_leader) {
            $top_leader_remark = $top_leader['remark'];
        }
        return [
            'id'                   => $items['id'],
            'order_no'             => $items['order_no'],
            'cycle'                => $items['cycle'],
            'end_time'             => $items['end_time'] ? date('Y/m/d H:i:s', $items['end_time']) : '',
            'count_down'           => $items['end_time'] - time(),
            'money'                => i18n_sprintf($items['money']),
//            'investment_product_id'   => $items['investment_product_id'],
            'investment_product_name' => $items['investment_product_name'],
            'profit'               => $items['profit'],
            'profit_money'         => i18n_sprintf($items['profit_money']),
            'profit_type'          => $items['profit_type'],
            'settlement_time'      => $items['settlement_time'] ? date('Y/m/d H:i:s', $items['settlement_time']) : '',
            'start_time'           => $items['start_time'] ? date('Y/m/d H:i:s', $items['start_time']) : '',
            'status'               => $items['status'],
            'status_str'           => FinanceProductOrderRepository::$statusMap[$items['status']],
            'level_name'           => $items['level']['level_name'],
            'user_info'            => $items['user']['id'].'/'.$items['user']['user_name'],
            'create_time'          => $items['create_time'],
            'top_leader_remark'    => $top_leader_remark,
            'is_xuni'              => $items['user']['is_xuni'],
            'is_xuni_text'         => $items['user']['is_xuni'] ? '是' : '否',
        ];
    }
}
