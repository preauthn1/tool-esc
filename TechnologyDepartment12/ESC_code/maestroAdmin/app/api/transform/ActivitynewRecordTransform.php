<?php

namespace app\api\transform;

use app\admin\transform\Transform;

class ActivitynewRecordTransform extends Transform
{
    public function transform($items): array
    {
        $reward = $items['reward'] ?? 0;
        return [
            'reward'          => i18n_currency($reward),
            'reward_value'    => $reward,
            'recharge_amount' => i18n_currency($items['recharge_amount'] ?? 0),
        ];
    }

}
