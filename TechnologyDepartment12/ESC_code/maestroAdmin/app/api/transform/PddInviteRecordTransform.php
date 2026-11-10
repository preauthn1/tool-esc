<?php

namespace app\api\transform;

use app\admin\transform\Transform;

class PddInviteRecordTransform extends Transform
{
    public function transform($items): array
    {
        return [
            'id'          => $items['id'],
            'user_name'   => $items['from_user']['user_name'],
            'create_time' => i18n_datetime2str(strtotime($items['create_time'])),
        ];
    }
}
