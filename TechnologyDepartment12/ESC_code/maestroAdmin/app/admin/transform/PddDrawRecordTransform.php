<?php

namespace app\admin\transform;

use app\admin\transform\Transform;

class PddDrawRecordTransform extends Transform
{
    public function transform($items): array
    {
//        dd($items['record']);
        return [
            'id'             => $items['id'],
            'user_id'        => $items['user_id'],
            'record_id'      => $items['record_id'],
            'create_time'    => $items['record']['create_time'],
            'type'           => $items['type'],
            'type_str'       => $items['type'] == 1 ? '小奖' : '大奖',
            'value'          => $items['value'],
            'amount'         => $items['record']['amount'],
            'progress'       => $items['record']['progress'],
            'is_receive'     => $items['record']['is_receive'],
            'is_receive_str' => $items['record']['is_receive'] == 1 ? '已领取' : '未领取',
            'is_xuni'        => $items['user']['is_xuni'],
            'is_xuni_text'   => $items['user']['is_xuni'] == 1 ? '<span style="color:red">是</span>' : '否',
            'update_time'    => $items['update_time']
        ];
    }
}
