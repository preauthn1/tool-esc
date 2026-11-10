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

use app\admin\model\OperationModel;
use app\admin\model\MemberModel;
use think\facade\View;

class Operation extends Base
{
    public function index()
    {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $keyword = isset($param['keyword']) ? $param['keyword'] : '';
            $member = isset($param['member']) ? $param['member'] : '';
            $admin = isset($param['admin']) ? $param['admin'] : '';

            $where = [];
            $memberIds = [];

            // 处理会员账号搜索
            if (!empty($member)) {
                $memberModel = new MemberModel();
                $members = $memberModel->where('user_name', 'LIKE', "%{$member}%")->select();

                if (!empty($members)) {
                    foreach ($members as $m) {
                        $memberIds[] = $m['id'];
                    }
                } else {
                    // 没找到匹配的会员，确保查询结果为空
                    $memberIds[] = 0;
                }

                // 添加会员ID条件
                $where[] = ['user_id', 'IN', $memberIds];
            }

            // 整合关键词查询条件
            if (!empty($keyword)) {
                $where[] = ['username|action|content', 'LIKE', "%{$keyword}%"];
            }

            // 管理员账号直接搜索username字段
            if (!empty($admin)) {
                $where[] = ['username', 'LIKE', "%{$admin}%"];
            }

            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = $param['startTime'];
                $end_time   = $param['endTime'];
                $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
            }

            $selectResult = OperationModel::where($where)
                ->with('user')
                ->limit($offset, $limit)
                ->order(['id' => 'DESC'])
                ->select()->toArray();
            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['user_name'] = '';
                if (isset($vo['user']['user_name'])) {
                    $selectResult[$key]['user_name'] = $vo['user']['user_name'];
                }
            }
            $return['total'] = OperationModel::where($where)->count();  //总数据
            $return['rows'] = $selectResult;
            return json($return);
        }
        return View::fetch();
    }
}
