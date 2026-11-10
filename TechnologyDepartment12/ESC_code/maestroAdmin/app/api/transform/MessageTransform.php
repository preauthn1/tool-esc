<?php

namespace app\api\transform;

class MessageTransform extends Transform
{

    public function transform($items): array
    {
        return [
            //  "id": 6715,
            //                "user_id": 2084,
            //                "admin_id": 0,
            //                "type": 2,
            //                "title": "充值到账",
            //                "content": "420MXN",
            //                "create_time": "2025-01-29 19:51:43",
            //                "is_read": 0,
            //                "read_time": null,
            //                "is_pop": 0,
            //                "update_time": "2025-01-29 19:51:43"
            'id'          => $items['id'],
            'user_id'     => $items['user_id'],
//            'admin_id' => $items['admin_id'],
            'type'        => $items['type'],
            'title'       => lang($items['title']),
            'content'     => $items['content'],
            'create_time' => $items['create_time'],
            'is_read'     => $items['is_read'],
//           'read_time' => $items['read_time'],
//            'is_pop' => $items['is_pop'],
//            'update_time' => $items['update_time']
        ];
    }
}
