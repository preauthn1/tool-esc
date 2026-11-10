<?php

namespace app\admin\transform;

use app\api\transform\Transform;

class MemberTransform extends Transform
{

    public function transform($items): array
    {
        return [
            'id'                 => $items['id'],
            'user_name'          => $items['user_name'],
            'avatar'             => toMedia($items['avatar'], '/static/admin/images/heads/', 1),
            'user_money'         => i18n_sprintf($items['user_money']),
            'mobile'             => $items['mobile'],
            'phone_code'         => $items['phone_code'],
            'frozen_money'       => i18n_sprintf($items['frozen_money']),
            'recharge_money'     => i18n_sprintf($items['recharge_money']),
//            'brokerage_money'    => $items['brokerage_money'],
//            'earnings_money'     => $items['earnings_money'],
            'balance_money'      => i18n_sprintf($items['balance_money']),
            'level_id'           => $items['level_id'],
            'plan_id'            => $items['plan_id'],
            'bank_code'          => $items['bank_code'],
            'bank_name'          => $items['bank_name'],
            'true_name'          => $items['true_name'],
            'bank_number'        => $items['bank_number'],
            'invite_code'        => $items['invite_code'],
            'status'             => $items['status'],
            'is_activate'        => $items['is_activate'],
            'recharge_address'   => $items['recharge_address'],
            'withdraw_address'   => $items['withdraw_address'],
            'kyc_status'         => $items['kyc_status'],
            'sign_in_day'        => $items['sign_in_day'],
            'sign_last_time'     => $items['sign_last_time'],
            'lottery_num'        => $items['lottery_num'],
            'lottery_num_invite' => $items['lottery_num_invite'],
            'remark'             => $items['remark'],
            'create_time'        => $items['create_time'],
            'tree_id'            => $items['tree_id'],
        ];
    }
}
