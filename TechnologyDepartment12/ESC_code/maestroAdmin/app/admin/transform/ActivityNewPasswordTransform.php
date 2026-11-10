<?php

namespace app\admin\transform;


class ActivityNewPasswordTransform extends Transform
{
    public function transform($items): array
    {

        return [
            'id'                   => $items['id'],
            'activity_id'          => $items['activity_id'],
            'password'             => $items['password'],
            'password_update_time' => i18n_datetime2str($items['password_update_time']),
            'num'                  => $items['num'],
//            'status'               => $items['status'],
            'remark'               => $items['remark'],
        ];
    }
}
