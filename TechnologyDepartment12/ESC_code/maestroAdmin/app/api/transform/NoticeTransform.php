<?php

namespace app\api\transform;

class NoticeTransform extends Transform
{

    public function transform($items): array
    {
        $data = $items['info'];
        return [
            'id'          => $data['id'],
            'lang'        => $data['lang'],
//            'notice_id' => $data['notice_id'],
            'content'     => nl2br($data['content']),
            'create_time' => i18n_datetime2str(strtotime($data['create_time'])),
        ];
    }
}
