<?php

namespace app\admin\transform;

use app\api\transform\Transform;
use app\common\model\UserModel;
use app\common\repository\FinanceProductOrderRepository;

class FinanceProductOrderTransform extends Transform
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
            'invest_period_type'   => $items['invest_period_type'],
            'end_time'             => $items['end_time'] ? date('Y/m/d H:i:s', $items['end_time']) : '',
            'count_down'           => $items['end_time'] - time(),
            'balance'              => i18n_sprintf($items['balance']),
            'invest_rate'          => $items['invest_rate'].'%',
            'price'                => i18n_sprintf($items['price']),
            'money'                => i18n_sprintf($items['money']),
//            'finance_product_id'   => $items['finance_product_id'],
            'finance_product_name' => $items['finance_product_name'],
            'profit'               => $items['profit'] . '%',
            'profit_money'         => i18n_sprintf($items['profit_money']),
            'profit_type'          => $items['profit_type'],
            'settlement_time'      => $items['settlement_time'] ? date('Y/m/d H:i:s', $items['settlement_time']) : '',
            'start_time'           => $items['start_time'] ? date('Y/m/d H:i:s', $items['start_time']) : '',
            'status'               => $items['status'],
            'status_str'           => FinanceProductOrderRepository::$statusMap[$items['status']],
            'level_name'           => $items['level']['level_name'],
            'level_id'             => $items['level']['level_id'],
            'user_info'            => $items['user']['id'].'/'.$items['user']['user_name'],
            'match_time'           => $items['match_time'] ? date('Y/m/d H:i:s', $items['match_time']) : '',
            'buy_time'             => $items['buy_time'] ? date('Y/m/d H:i:s', $items['buy_time']) : '',
            'create_time'          => $items['create_time'],
            'session_no'           => $items['session_no'],
            'fee_rate'           => $items['fee_rate'].'%',
            'fee'           => $items['fee'],
            'stock_range_min'      => i18n_sprintf($items['stock_range_min']),
            'stock_range_max'      => i18n_sprintf($items['stock_range_max']),
            'top_leader_remark'    => $top_leader_remark,
            'match_type_str'       => FinanceProductOrderRepository::$matchTypeMap[$items['match_type']],
            'is_xuni'              => $items['user']['is_xuni'],
            'is_xuni_text'         => $items['user']['is_xuni'] ? '是' : '否',
            'operate'              => showOperate($this->makeButton($items))
        ];
    }

    /**
     * 拼装操作按钮
     * @param $items
     * @return array
     */
    private function makeButton($items): array
    {
        if ($items['status'] == FinanceProductOrderRepository::STATUS_PENDING) {
            return [
                '编辑' => [
                    'auth'     => 'finance_product_order/edit',
                    'href'     => url('finance_product_order/edit', ['id' => $items['id']]),
                    'btnStyle' => 'primary',
                    'icon'     => 'fa fa-paste'
                ],
            ];
        }
        return [];
    }
}
