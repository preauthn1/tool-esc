<?php

namespace app\api\transform;

class UserTransform extends Transform
{

    public function transform($items): array
    {
        return [
            'id'                                => $items['id'],
            'user_name'                         => $items['user_name'],
            'avatar'                            => toMedia($items['avatar'], '/static/admin/images/heads/', 1),
            'user_money'                        => i18n_currency($items['user_money']),
            'user_money_value'                  => i18n_sprintf($items['user_money']),
            'mobile'                            => $items['mobile'],
            'phone_code'                        => $items['phone_code'],
            'frozen_money'                      => i18n_currency($items['frozen_money']),
            'recharge_money'                    => i18n_currency($items['recharge_money']),
//            'brokerage_money'    => $items['brokerage_money'],
//            'earnings_money'     => $items['earnings_money'],
            'balance_money'                     => i18n_currency($items['balance_money']),
            'brokerage_pool'                    => i18n_currency($items['brokerage_pool']),
            'withdrawal_amount'                 => i18n_currency($items['withdrawal_amount']),
            'level_id'                          => $items['level_id'],
            'plan_id'                           => $items['plan_id'],
            'bank_code'                         => $items['bank_code'] ?? '',
            'bank_name'                         => $items['bank_name'] ?? '',
            'true_name'                         => $items['true_name'] ?? '',
            'bank_number'                       => $items['bank_number'] ?? '',
            'invite_code'                       => $items['invite_code'],
            'status'                            => $items['status'],
            'is_activate'                       => $items['is_activate'],
            'recharge_address'                  => $items['recharge_address'] ?? '',
            'withdraw_address'                  => $items['withdraw_address'] ?? '',
            'kyc_status'                        => $items['kyc_status'],
            'sign_in_day'                       => $items['sign_in_day'],
            'sign_last_time'                    => $items['sign_last_time'],
            'lottery_num'                       => $items['lottery_num'],
            'lottery_num_invite'                => $items['lottery_num_invite'],
            'create_time'                       => $items['create_time'],
            'last_login_time'                   => $items['last_login_time'],
            'credit_score'                      => $items['credit_score'],
            'is_finance_product_count_down'     => $items['is_finance_product_count_down'],
            'document_type'                     => $items['document_type'],
            'document_id'                       => $items['document_id'],
            'finance_product_button_count_down' => $items['finance_product_button_count_down'],
            'transfer_money_status'             => $items['transfer_money_status'],
            'is_today_register'                 => intval(explode(' ', $items['create_time'])[0] == date('Y-m-d'))
        ];
    }
}
