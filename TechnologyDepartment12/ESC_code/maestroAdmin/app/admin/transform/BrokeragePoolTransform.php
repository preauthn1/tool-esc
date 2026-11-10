<?php

namespace app\admin\transform;

use app\api\transform\Transform;

class BrokeragePoolTransform extends Transform
{

    public function transform($items): array
    {
        $brokerage_pool_receivable = $brokerage_pool = $receivable = $received = 0;
        if ($items['user']['user_brokerage_pool']) {
            $brokerage_pool = $items['user']['brokerage_pool'];
            $receivable = min($items['user']['user_brokerage_pool']['receivable'] - $items['user']['user_brokerage_pool']['received'],
                $items['user']['brokerage_pool'] - $items['user']['user_brokerage_pool']['received']);
            $brokerage_pool_receivable = $items['user']['user_brokerage_pool']['receivable'] - $items['user']['user_brokerage_pool']['received'];
            $received = $items['user']['user_brokerage_pool']['received'];
        }
        return [
            'id'                        => $items['id'],
            'user_info'                 => $items['user']['user_name'].'/'.$items['user']['id'],
            'money'                     => i18n_sprintf($items['money']),
            'create_time'               => $items['create_time'],
            'brokerage_pool'            => i18n_sprintf($brokerage_pool),
            'receivable'                => i18n_sprintf($receivable),
            'received'                  => i18n_sprintf($received),
            'brokerage_pool_receivable' => i18n_sprintf($brokerage_pool_receivable),
        ];
    }
}
