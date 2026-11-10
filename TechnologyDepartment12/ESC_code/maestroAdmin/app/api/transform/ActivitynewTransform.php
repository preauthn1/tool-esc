<?php

namespace app\api\transform;

use app\admin\transform\Transform;

class ActivitynewTransform extends Transform
{
    public function transform($items): array
    {
        return [
            'id' => $items['id'],
            'records_count' => $items['records_count'] ?? 0,
            'user_count' => $items['user_count'] ?? 0,
            'can_receive' => $items['can_receive'] ?? 0,
            'ratio' => $items['ratio'] ?? 0,
            'joined' => $items['joined'] ?? 0,
            'is_reward' => $items['is_reward'] ?? 0,
            'expiration_time' => $items['expiration_time'] ?? 1,
            'title' => $items['title'],
            'content' => $items['content'],
            'reward' => $items['reward'],
            'start_time' => i18n_datetime2str($items['start_time']),
            'end_time' => i18n_datetime2str($items['end_time']),
        ];
    }

}
