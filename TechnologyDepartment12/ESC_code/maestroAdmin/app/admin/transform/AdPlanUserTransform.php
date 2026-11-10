<?php

namespace app\admin\transform;

use app\common\model\AdPlanModel;

class AdPlanUserTransform extends Transform
{
    public function transform($items): array
    {
        $day = '-';
        if ($items['ad_plan_id']) {
            $day = AdPlanModel::getDayCount($items['id'], $items['ad_plan_id']);
        }
        $ad_plan = [];
        if ($items['ad_plan']) {
            $ad_plan = (new AdPlanTransform())->transform($items['ad_plan']);
        }
        return [
            'id'          => $items['id'],
            'user_name'   => $items['user_name'],
            'ad_plan'     => $ad_plan,
            'create_time' => $items['create_time'],
            'day'         => $day,
        ];
    }
}
