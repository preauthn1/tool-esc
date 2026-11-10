<?php

namespace app\api\transform;

use app\admin\transform\Transform;
use app\common\model\PddTurntableCountModel;

class PddTransform extends Transform
{
    public function transform($items): array
    {
        $turntable_count = PddTurntableCountModel::getAvailableNum($items['user_id']);
        return [
            'id'              => $items['id'],
            'title'           => $items['title'],
            'start_time'      => $items['start_time'],
            'end_time'        => $items['end_time'],
            'status'          => $items['status'],
            'prizes'          => $items['prizes'] ? (new PddPrizeTransform())->transformCollection($items['prizes']) : [],
            'turntable_count' => $turntable_count,
        ];
    }

}
