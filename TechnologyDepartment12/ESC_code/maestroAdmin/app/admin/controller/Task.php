<?php

// +----------------------------------------------------------------------
// | snake
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://baiyf.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: NickBai <1902822973@qq.com>
// +----------------------------------------------------------------------

namespace app\admin\controller;

use app\admin\model\LevelModel;
use app\admin\model\MemberModel;
use app\common\model\TaskModel;
use app\common\model\UserModel;
use think\facade\View;

class Task extends Base
{

    public function index()
    {
        $order_status = TaskModel::$STATUS_NAMES;
        $type_status = TaskModel::$TYPE_NAMES;
        $order_type = [
            1 => '自动派单',
            2 => '手动派单',
        ];
        $level_list = LevelModel::column('level_name', 'level_id');
        if (request()->isAjax() || $this->request->isPost()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            if (!empty($param['searchText'])) {
                $uid = getUseridByMobile($param['searchText']);
                if (!empty($uid)) {
                    $where[] = ['user_id', '', $uid];
                } else {
                    $where[] = ['user_id', '', $param['searchText']];
                }
            }
            if (isset($param['level_id']) && $param['level_id'] != '') {
                $where[] = ['level_id', '', $param['level_id']];
            }
            if (isset($param['task_type']) && $param['task_type'] != '') {
                $where[] = ['task_type', '', $param['task_type']];
            }
            if (isset($param['order_type']) && $param['order_type'] != '') {
                $where[] = ['order_type', '', $param['order_type']];
            }
            if (isset($param['status']) && $param['status'] != '') {
                $where[] = ['status', '', $param['status']];
            }
            if (!empty($param['leader1'])) {
                $where[] = ['user_model.tree_id', 'like', '%/'.$param['leader1'].'/%'];
                $where[] = ['user_model.tree_id', 'not like', '%/'.$param['leader1'].'/'];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time = strtotime($param['endTime'] . " 23:59:59");
                $where[] = ['task_model.create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            $where[] = ['task_model.status', 'BETWEEN', [TaskModel::STATUS_CANCEL, TaskModel::STATUS_END]];

            $export = $this->request->param("export/d", 0);
            if ($export) {
                $result = TaskModel::hasWhere('user')
                    ->with(['user' => function ($query) {
                    $query->field('id,user_name,mobile');
                }])->where($where)
                    ->order(['create_time' => 'desc'])
                    ->select();
                $header = [
                    'level_name'        => 'VIP等级',
                    'task_type_str'     => '任务类型',
                    'user_info'         => '用户信息',
                    'top_leader_remark' => '所属代理',
                    'product_name'      => '任务信息',
                    'income_profit_str' => '收益率',
                    'money'             => '佣金',
                    'finish_money'      => '完成任务金额',
                    'order_type_str'    => '派单类型',
                    'status_str'        => '状态',
                    'create_time_str'   => '添加时间',
                    'finish_time_str'   => '完成时间',
                ];
                foreach ($result as $key => $row) {
                    $top_leader = UserModel::getTopLeader($row['user_id']);
                    $top_leader_remark = '';
                    if ($top_leader) {
                        $top_leader_remark = $top_leader['remark'];
                    }
                    $result[$key]['level_name'] = isset($level_list[$row['level_id']]) ? $level_list[$row['level_id']] : '-';
                    $result[$key]['user_info'] = $row['user'] ? $row['user']['id'] . '/' . $row['user']['user_name'] :'';
                    $result[$key]['status_str'] = $order_status[$row['status']];
                    $result[$key]['task_type_str'] =  $type_status[$row['task_type']];
                    $result[$key]['income_profit_str'] = $row['income_profit'] . "%";
                    $result[$key]['order_type_str'] = $order_type[$row['order_type']];
                    $result[$key]['create_time_str'] = $row['create_time'];
                    $result[$key]['finish_time_str'] = empty($row['finish_time']) ? '--' : date("Y-m-d H:i:s", $row['finish_time']);
                    $result[$key]['top_leader_remark'] = $top_leader_remark;
                }
                return genXlsx($header, $result, "任务记录");
            } else {
                $result = TaskModel::hasWhere('user')->with(['user' => function ($query) {
                    $query->field('id,user_name,mobile');
                }])->where($where)
                    ->limit($offset, $limit)
                    ->order(['create_time' => 'desc'])
                    ->select();
                $total = TaskModel::hasWhere('user')->where($where)->count();
            }

            // 拼装参数
            foreach ($result as $key => $row) {
                $top_leader = UserModel::getTopLeader($row['user_id']);
                $top_leader_remark = '';
                if ($top_leader) {
                    $top_leader_remark = $top_leader['remark'];
                }
                $result[$key]['level_name'] = isset($level_list[$row['level_id']]) ? $level_list[$row['level_id']] : '-';
                $result[$key]['user_info'] = $row['user'] ? $row['user']['id'] . '/' . $row['user']['user_name'] :'';
                $result[$key]['status_str'] = getStatusText($row['status'], $order_status[$row['status']]);
                $result[$key]['task_type_str'] = getStatusText($row['task_type'], $type_status[$row['task_type']]);
                $result[$key]['income_profit_str'] = $row['income_profit'] . "%";
                $result[$key]['order_type_str'] = getStatusText($row['order_type'], $order_type[$row['order_type']]);
                $result[$key]['create_time_str'] = $row['create_time'];
                $result[$key]['finish_time_str'] = empty($row['finish_time']) ? '--' : date("Y-m-d H:i:s", $row['finish_time']);
                $result[$key]['top_leader_remark'] = $top_leader_remark;
            }
            $return = [];
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }

        $agent = MemberModel::where('is_proxy', 1)->select();
        View::assign('agent', $agent);
        View::assign([
            'order_status' => $order_status,
            'type_status' => $type_status,
            'order_type' => $order_type,
            'level_list' => $level_list,
        ]);
        return View::fetch();
    }

    // 变更状态
    public function checkStatus($id, $status, $type = 0)
    {
        $model = new TaskModel();
        if ($type == 1) {
            $ids = implode(',', $id);
            $ret = $model->updateStatus(['status' => $status], ['id' => ['in', $ids]]);
        } else {
            $ret = $model->updateStatus(['status' => $status], ['id' => $id]);
        }
        return json($ret);
    }
}
