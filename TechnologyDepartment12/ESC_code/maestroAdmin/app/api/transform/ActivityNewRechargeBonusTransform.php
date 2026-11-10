<?php

namespace app\api\transform;


use app\admin\transform\Transform;

class ActivityNewRechargeBonusTransform extends Transform
{
    public function transform($items): array
    {

        return [
            'id'          => $items['id'],
            'activity_id' => $items['activity_id'],
            'amount'      => i18n_currency($items['amount']),
            'rate'        => $items['rate'] . ' %',
            'remark'      => $items['remark'],
        ];
    }
}
