<?php

namespace app\api\transform;

use app\admin\transform\Transform;

class PddDrawRecordTransform extends Transform
{
    public function transform($items): array
    {
        return [
            'id'          => $items['id'],
            'create_time' => $items['record']['create_time'],
            'type'        => $items['type'],
            'value'       => $items['value']
        ];
    }
}
