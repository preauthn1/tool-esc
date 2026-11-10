<?php

namespace app\api\transform;

use app\admin\transform\Transform;

class PddFinanceProductRecordTransform extends Transform
{
    public function transform($items): array
    {
        return [
            'id'          => $items['id'],
            'create_time' => i18n_datetime2str(strtotime($items['create_time'])),
        ];
    }
}
