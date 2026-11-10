<?php

namespace app\api\transform;

use app\admin\transform\Transform;

class PddPrizeTransform extends Transform
{
    public function transform($items): array
    {
        return [
            'id'           => $items['id'],
            'name'         => $items['name'],
            'value'        => $items['value'],
            'status'       => $items['status'],
            'img'          => $items['img'],
            'sort'         => $items['sort'],
            'is_big_prize' => $items['is_big_prize'],
        ];
    }

}
