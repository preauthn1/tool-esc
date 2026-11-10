<?php

namespace app\api\transform;

use app\admin\transform\Transform;
use app\common\model\VideoModel;

class TaskTransform extends Transform
{
    public function transform($items): array
    {
        return [
            'id'          => $items['id'],
            'money'       => $items['money'],
//            'score'      => $items['score'],
            'video_id'    => $items['product_id'],
            'image'       => toMedia($items['product_img'], 1),
            'video_name'  => $items['product_name'],
            'level_id'    => $items['level_id'],
            'user_id'     => $items['user_id'],
            'level_name'  => lang($items['level']['level_name']),
            'status'      => $items['status'],
            'create_time' => date('Y/m/d H:i:s', strtotime($items['create_time'])),
        ];
    }

}
