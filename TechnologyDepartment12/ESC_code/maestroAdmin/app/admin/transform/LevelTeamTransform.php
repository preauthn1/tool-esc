<?php

namespace app\admin\transform;


class LevelTeamTransform extends Transform
{
    public function transform($items): array
    {
        $level_team = count($items['level_team']) > 0 ? $items['level_team'][0] : [];
        return [
            'id'                 => $level_team['id'] ?? '',
            'level_id'           => $items['level_id'],
            'user_id'            => $items['user_id'],
            'income'             => $level_team['income'] ?? '',
            'active_income'      => $level_team['active_income'] ?? '',
            'income_max'         => $level_team['income_max'] ?? '',
            'invest_num'         => $level_team['invest_num'] ?? '',
            'invest_rate'        => $level_team['invest_rate'] ?? '',
            'invest_max_rate'    => $level_team['invest_max_rate'] ?? '',
            'active_rate'        => $level_team['active_rate'] ?? '',
            'invest_profit'      => $level_team['invest_profit'] ?? '',
            'invest_day'         => $level_team['invest_day'] ?? '',
            'invest_period_type' => $level_team['invest_period_type'] ?? '',
            'invest_password'    => $level_team['invest_password'] ?? '',
            'status'             => $level_team['status'] ?? '',
            'subordinate'        => $level_team['subordinate'] ?? '',
            'level_name'         => $items['level_name'],
            'type'               => $level_team['type'] ?? '',
            'create_time'        => $level_team['create_time'] ?? '',
            'update_time'        => $level_team['update_time'] ?? '',
            'overdue_time'       => isset($level_team['overdue_time']) ? i18n_datetime2str($level_team['overdue_time']) : '',
            'operate'            => showOperate($this->makeButton($level_team['id'] ?? '', $items['level_id'],
                $items['user_id']))
        ];
    }


    /**
     * 拼装操作按钮
     * @param $id
     * @param $level_id
     * @param $user_id
     * @return array
     */
    private function makeButton($id, $level_id, $user_id): array
    {
        return [
//            '广告位时段' => [
//                'auth'     => 'levelTeam/investTime',
//                'href'     => url('levelTeam/investTime', ['user_id' => $user_id, 'level_id' => $level_id]),
//                'btnStyle' => 'warning',
//                'icon'     => 'fa fa-clock-o'
//            ],
            '编辑' => [
                'auth'     => 'levelTeam/add',
                'href'     => url('levelTeam/add', ['id' => $id, 'user_id' => $user_id, 'level_id' => $level_id]),
                'btnStyle' => 'primary',
                'icon'     => 'fa fa-paste'
            ],
        ];
    }

}
