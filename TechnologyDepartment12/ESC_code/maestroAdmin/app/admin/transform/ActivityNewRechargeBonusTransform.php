<?php

namespace app\admin\transform;


class ActivityNewRechargeBonusTransform extends Transform
{
    public function transform($items): array
    {

        return [
            'id'          => $items['id'],
            'activity_id' => $items['activity_id'],
            'amount'      => $items['amount'],
            'rate'        => $items['rate'],
            'num'         => $items['num'],
            'status'      => $items['status'],
            'remark'      => $items['remark'],
        ];
    }
}
