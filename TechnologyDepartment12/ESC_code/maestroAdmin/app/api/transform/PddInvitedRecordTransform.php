<?php

namespace app\api\transform;

use app\admin\transform\Transform;

class PddInvitedRecordTransform extends Transform
{
    public function transform($items): array
    {
        return [
            'user_name' => $items['user_name'],
            'earnings'  => $items['earnings'],
        ];
    }
}
