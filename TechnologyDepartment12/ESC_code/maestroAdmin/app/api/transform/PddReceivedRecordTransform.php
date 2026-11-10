<?php

namespace app\api\transform;

use app\admin\transform\Transform;

class PddReceivedRecordTransform extends Transform
{
    public function transform($items): array
    {
        return [
            'id'          => $items['id'],
            'user_name'   => $items['user_name'],
            'create_time' => $items['create_time'],
            'earnings'    => $items['earnings'],
        ];
    }
}
