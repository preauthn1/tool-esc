<?php

namespace app\admin\transform;

class AdPlanTransform extends Transform
{
    public function transform($items): array
    {
        $next_plan = null;
        if ($items['next_plan']) {
            $next_plan = (new AdPlanTransform())->transform($items['next_plan']);
        }
        return [
            'id'        => $items['id'],
            'name'      => $items['name'],
            'next_id'   => $items['next_id'],
            'period'    => $items['period'],
            'sort'      => $items['sort'],
            'status'    => $items['status'],
            'next_plan' => $next_plan
        ];
    }
}
