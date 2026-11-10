<?php

namespace app\api\transform;

use app\admin\transform\Transform;
use app\common\model\MemberLevelModel;

class LevelTransform extends Transform
{
    public function transform($items): array
    {
        return [
            'id'                    => $items['id'],
            'level_id'              => $items['level_id'],
            'img'                   => toMedia($items['img'], 1),
            'income'                => $items['income'],
            'income_text'           => $items['income_text'],
            'invest_income_text'    => lang($items['invest_income_text']),
            'invest_num'            => $items['invest_num'],
            'task_num'              => $items['task_num'],
            'invite_num'            => $items['invite_num'],
            'invite_num_money'      => $items['invite_num_money'],
            'level_time'            => $items['level_time'],
            'level_time_text'       => lang($items['level_time_text']),
            'price'                 => $items['price'],
            'price_text'            => $items['price_text'],
            'level_name'            => lang($items['level_name']),
            'is_show'               => $items['is_show'],
            'is_can_buy'            => $items['is_can_buy'],
            'type'                  => $items['type'],
            'limit_unlock_num_text' => $items['limit_unlock_num_text'],
        ];
    }

}
