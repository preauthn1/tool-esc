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

use app\admin\model\UserModel as AdminModel;
use app\common\model\ShareTaskModel;
use app\common\model\UserModel;

class ShareAudit extends Base
{

    // 分享任务列表
    public function index()
    {
        $account_type = ShareTaskModel::$ACCOUNT_TYPE_NAME;
        $status_type = ShareTaskModel::$STATUS__NAME;
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (!empty($param['account_type'])) {
                $where[] = ['account_type', '', $param['account_type']];
            }
            $model = new ShareTaskModel();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            foreach ($result as $key => $vo) {
                $user_info = UserModel::getOneUser($vo['user_id'], 'id', 'user_name,nick_name,mobile');
                $result[$key]['user_info'] = $vo['user_id'] . "/" . (empty($user_info['user_name']) ? $user_info['mobile'] : $user_info['user_name']);
                $result[$key]['account_type_str'] = "奖励" . $account_type[$vo['account_type']];
                $result[$key]['status_str'] = $status_type[$vo['status']];
                $result[$key]['img'] = $vo['img'] ? '<img src="' . toMedia($vo['img']) . '" width="40px" height="40px">' : '-';
                if (empty($vo['check_time'])) {
                    $result[$key]['check_time'] = "--";
                } else {
                    $result[$key]['check_time'] = date('Y-m-d H:i:s', $vo['check_time']);
                }
                $admin_name = AdminModel::getUserName($vo['check_admin']);
                if ($vo['status'] == 1) {
                    $admin_name = "--";
                } else {
                    $admin_name = empty($admin_name) ? '自动' : $admin_name;
                }
                $result[$key]['check_admin_str'] = empty($admin_name) ? '自动' : $admin_name;
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = $total;  // 总数据
            $return['rows'] = $result;
            return json($return);
        }
        return View::fetch();
    }

    /**
     * 审核任务
     * @param $id
     */
    public function check()
    {
        $param = input('param.');
        $ids = $param['id'];
        $share_task_model = new ShareTaskModel();
        $share_task_model->check($ids, $param['remark'], $param['status'], session('id', ''));
        $this->success(lang('审核成功'), url('index')->build());
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id)
    {
        return [
            '审核' => [
                'auth' => 'shareAudit/add',
                'href' => url('check', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ]
        ];
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeItemButton($id)
    {
        return [
            '编辑' => [
                'auth' => '',
                'href' => "javascript:add('{$id}');",
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ]
        ];
    }
}
