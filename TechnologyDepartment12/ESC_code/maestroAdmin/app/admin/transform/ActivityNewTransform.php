<?php

namespace app\admin\transform;

use app\common\repository\ActivityNewPasswordRepository;
use app\common\repository\ActivityNewRechargeBonusRepository;

class ActivityNewTransform extends Transform
{
    public function transform($items): array
    {

        $data =  [
            'id'          => $items['id'],
            'title'       => $items['title'],
            'thumb'       => $items['thumb'],
            'type'        => $items['type'],
            'reward_type' => $items['reward_type'],
            'reward'      => $items['reward'],
            'status'      => $items['status'],
            'content'     => $items['content'],
            'expiration_time'        => $items['expiration_time'],
            'start_time'  => date("Y-m-d H:i:s", $items['start_time']),
            'end_time'    => date("Y-m-d H:i:s", $items['end_time'])
        ];
        // 类型 8 和 9 都需要返回 passwords 和 recharge_bonuses
        if (in_array($data['type'], [8, 9])) {
            $passwords = (new ActivityNewPasswordRepository())->getPasswordByActivityId($items['id']);
            $passwords = (new ActivityNewPasswordTransform)->transformCollection($passwords);
            $data['passwords'] = $passwords;

            $recharge_bonuses = (new ActivityNewRechargeBonusRepository())->getRechargeBonusByActivityId($items['id']);
            $recharge_bonuses = (new ActivityNewRechargeBonusTransform)->transformCollection($recharge_bonuses);
            $data['recharge_bonuses'] = $recharge_bonuses;
        }
        return $data;
    }
}
