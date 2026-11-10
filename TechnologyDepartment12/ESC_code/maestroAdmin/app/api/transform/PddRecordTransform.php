<?php

namespace app\api\transform;

use app\admin\transform\Transform;
use app\common\model\PddTurntableCountModel;

class PddRecordTransform extends Transform
{
    public function transform($items): array
    {
        $time_left = (strtotime($items['create_time']) + 3600 * $items['task_period']) - time();
        if ($time_left < 0) {
            $time_left = 0;
        }
        $turntable_count = PddTurntableCountModel::getAvailableNum($items['user_id']);
        return [
            'id'              => $items['id'],
            'turntable_id'    => $items['turntable_id'],
            'prize_id'        => $items['prize_id'],
//            'user_id'      => $items['user_id'],
//            'is_big_prize' => $items['is_big_prize'],
            'amount'          => $items['amount'],
            'progress'        => $items['progress'],
            'is_receive'      => $items['is_receive'],
            'task_period'     => $items['task_period'],
            'create_time'     => $items['create_time'],
//            'update_time'  => $items['update_time'],
            'time_left'       => $time_left,
            'turntable_count' => $turntable_count,
            'min_invite'      => $items['min_invite'],
            'max_invite'      => $items['max_invite'],
        ];
    }

}
