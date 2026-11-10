<?php

namespace app\admin\transform;

use app\api\transform\Transform;
use app\common\model\UserModel;

class UserIncomeTransform extends Transform
{

    public function transform($items): array
    {
        $top_leader = UserModel::getTopLeader($items['id']);
        $top_leader_remark = '';
        if ($top_leader) {
            $top_leader_remark = $top_leader['remark'];
        }
        return [
            'id'                 => $items['id'],
            'user_name'          => $items['user_name'],
            'day_invite_income'  => i18n_sprintf($items['day_invite_income']),
            'week_invite_income' => i18n_sprintf($items['week_invite_income']),
            'month_invite_money' => i18n_sprintf($items['month_invite_money']),
            'day_active_income'  => i18n_sprintf($items['day_active_income']),
            'sign_in_income'     => i18n_sprintf($items['sign_in_income']),
            'balance_income'     => i18n_sprintf($items['balance_income']),
            'top_leader_remark'  => $top_leader_remark,
            'is_xuni'            => $items['is_xuni'] == 1 ? '是' : '否',
        ];
    }
}
