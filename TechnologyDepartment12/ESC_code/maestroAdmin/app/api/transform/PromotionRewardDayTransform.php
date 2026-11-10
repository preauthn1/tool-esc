<?php

namespace app\api\transform;


class PromotionRewardDayTransform extends Transform
{
    public function transform($items): array
    {
        return [
            'day'    => $items['day'],
            'award'  => i18n_currency($items['award']),
            'status' => $items['status'],
        ];
    }
}
