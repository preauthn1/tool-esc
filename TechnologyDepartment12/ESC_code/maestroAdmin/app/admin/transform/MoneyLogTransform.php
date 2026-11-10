<?php

namespace app\admin\transform;

use app\api\transform\Transform;
use app\common\model\MoneyLogModel;
use app\common\model\UserModel;

class MoneyLogTransform extends Transform
{

    public function transform($items): array
    {
        $top_leader = UserModel::getTopLeader($items['user']['id']);
        $top_leader_remark = '';
        if ($top_leader) {
            $top_leader_remark = $top_leader['remark'];
        }
        return [
            'id'                => $items['id'],
            'user_id'           => $items['user_id'],
            'user_info'         => $items['user']['id'].'/'.$items['user']['user_name'],
            'title'             => $items['title'],
            'type'              => $items['type'],
            'type_str'          => MoneyLogModel::$TYPE_NAMES[$items['type']],
            'detail_type'       => $items['detail_type'],
            'user_money'        => $items['user_money'],
            'money'             => $items['money'],
            'last_money'        => $items['last_money'],
            'change'            => $items['change'],
            'change_str'        => $items['change'] == 1 ? '收入' : '支出',
            'remarks'           => $items['remarks'],
            'create_time'       => $items['create_time'],
            'top_leader_remark' => $top_leader_remark,
            'is_xuni_text'      => $items['user']['is_xuni'] ? '是' : '否',
        ];
    }
}
