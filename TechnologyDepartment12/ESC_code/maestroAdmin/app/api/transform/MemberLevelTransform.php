<?php

namespace app\api\transform;

class MemberLevelTransform extends Transform
{

    public function transform($items): array
    {
        return [
            'id'             => $items['id'],
            'user_id'        => $items['user_id'],
//            'from_level'     => $items['from_level'],
            'level_id'       => $items['to_level'],
//            'money'          => $items['money'],
//            'level_time'     => $items['level_time'],
//            'level_type'     => $items['level_type'],
//            'level_end_time' => date('Y/m/d H:i:s', $items['level_end_time']),
//            'create_time'    => date('Y/m/d H:i:s', strtotime($items['create_time'])),
        ];
    }
}
