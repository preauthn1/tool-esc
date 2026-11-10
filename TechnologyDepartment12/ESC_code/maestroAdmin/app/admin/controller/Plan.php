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

use app\admin\model\RechargeModel;
use app\admin\model\WithdrawModel;
use app\common\model\PlanModel;
use app\common\model\PlanTaskModel;
use app\common\model\UserModel;
use think\facade\View;

class Plan extends Base
{

    // 计划任务列表
    public function index()
    {
        $loop = PlanModel::$LOOP_NAME;
        $type = PlanModel::$TYPE_NAME;
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['name']) && $param['name'] != '') {
                $where[] = ['name', 'LIKE', "%{$param['name']}%"];
            }
            if (!empty($param['loop'])) {
                $where[] = ['loop', '=', $param['loop']];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time = strtotime($param['endTime'] . " 23:59:59");
                $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            $model = new PlanModel();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            foreach ($result as $key => $vo) {
                $result[$key]['loop_str'] = $loop[$vo['loop']];
                $result[$key]['type_str'] = $type[$vo['type']];
                $result[$key]['next_str'] = PlanModel::getPlanName($vo['next_id']);
//                $result[$key]['create_time'] = date('Y-m-d H:i:s', $vo['create_time']);
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = $total;  // 总数据
            $return['rows'] = $result;
            return json($return);
        }
        View::assign('loop_list', $loop);
        return View::fetch();
    }

    // 计划任务会员列表
    public function member()
    {
        $plan_id = $this->request->param("id/d");;
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = ['plan_id' => $param['plan_id']];
            $model = new UserModel();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            foreach ($result as $key => $vo) {
//                $result[$key]['create_time'] = date('Y-m-d H:i:s', $vo['create_time']);
//                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
                $user_info = UserModel::getOneUser($vo['id'], 'id', 'user_name,nick_name,mobile');
                $result[$key]['user_info'] = (empty($user_info['user_name']) ? "" : $user_info['user_name']."/") . $user_info['mobile'];
                $result[$key]['recharge'] = RechargeModel::getRecharge($vo['id']);
                $result[$key]['withdraw'] = WithdrawModel::getWithdraw($vo['id']);
            }
            $return['total'] = $total;  // 总数据
            $return['rows'] = $result;
            return json($return);
        }
        View::assign('plan_id', $plan_id);
        return View::fetch();
    }

    // 计划任务详情列表
    public function view()
    {
        $plan_id = $this->request->param("plan_id/d");;
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [
                ['plan_id' , '=',  $plan_id]
            ];
            if (isset($param['name']) && $param['name'] != '') {
                $where[] = ['name', 'LIKE', "%{$param['name']}%"];
            }
//            if (!empty($param['startTime']) && !empty($param['endTime'])) {
//                $start_time = strtotime($param['startTime'] . " 00:00:00");
//                $end_time = strtotime($param['endTime'] . " 23:59:59");
//                $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
//            }
            $model = new PlanTaskModel();
            $result = $model->where($where)->limit($offset, $limit)->order(['sort' => 'ASC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            foreach ($result as $key => $vo) {
//                $result[$key]['create_time'] = date('Y-m-d H:i:s', $vo['create_time']);
                $result[$key]['operate'] = showOperate($this->makeItemButton($vo['id']));
            }
            $return['total'] = $total;  // 总数据
            $return['rows'] = $result;
            return json($return);
        }
        $plan_data = PlanModel::where('id', $plan_id)->find();
        View::assign('plan_id', $plan_id);

        if ($plan_data['type'] == PlanModel::TYPE_MULTIPLE) {
            return View::fetch();
        } else {
            return View::fetch('view2');
        }

    }

    public function add()
    {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.');
            $model = new PlanModel();
            if ($id > 0) {
                $param['update_time'] = time();
                $result = $model->where('id', $id)->update($param);
            } else {
                unset($param['id']);
                $param['create_time'] = time();
                $param['update_time'] = time();
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            $this->success(lang('提交成功'),'','close');
        }
        $info = PlanModel::where('id', $id)->find();
        if (empty($info)) {
            $info = [
                'loop' => 1,
                'type' => 1,
                'next_id' => 0,
            ];
        }
        $loop = PlanModel::$LOOP_NAME;
        $type = PlanModel::$TYPE_NAME;
        if (!empty($id)) {
            $plan_list = PlanModel::where('id', "<>", $id)->select();
        } else {
            $plan_list = PlanModel::select();
        }
        View::assign("info", $info);
        View::assign("loop_list", $loop);
        View::assign('type_list', $type);
        View::assign('plan_list', $plan_list);
        if (empty($id)) {
            return View::fetch('add');
        } else {
            return View::fetch('edit');
        }

    }

    public function addItem()
    {
        $id = $this->request->param("id/d");
        $plan_id = $this->request->param("plan_id/d");
        $index = $this->request->param("index/d");
        if (request()->isPost()) {
            $param = input('post.');
            $model = new PlanTaskModel();
            if ($id > 0) {
                $param['update_time'] = time();
                $result = $model->where('id', $id)->update($param);
            } else {
                unset($param['id']);
                $param['create_time'] = time();
                $param['update_time'] = time();
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            $this->success(lang('提交成功'),'','close');
        }
        $info = PlanTaskModel::where('id', $id)->find();
        View::assign("plan_id", $plan_id);
        View::assign("index", $index);
        View::assign("info", $info);
        return View::fetch('addItem');
    }

    public function addItem2()
    {
        $id = $this->request->param("id/d");
        $plan_id = $this->request->param("plan_id/d");
        $index = $this->request->param("index/d");
        if (request()->isPost()) {
            $param = input('post.');
            $model = new PlanTaskModel();
            if ($id > 0) {
                $param['update_time'] = time();
                $result = $model->where('id', $id)->update($param);
            } else {
                unset($param['id']);
                $param['create_time'] = time();
                $param['update_time'] = time();
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            $this->success(lang('提交成功'),'','close');
        }
        $info = PlanTaskModel::where('id', $id)->find();
        View::assign("plan_id", $plan_id);
        View::assign("index", $index);
        View::assign("info", $info);
        return View::fetch('addItem2');
    }

    /**
     * 删除任务
     * @param $id
     */
    public function del($id)
    {
        $ids = implode(',', $id);
        $plan_model = new PlanModel();
        $plan_model->where('id', 'in', $ids)->delete();
        $this->success(lang('删除成功'), url('index')->build());
    }

    /**
     * 清除用户绑定计划
     * @param $id
     */
    public function memberdel($id)
    {
        $ids = implode(',', $id);
        $user_model = new UserModel();
        $user_model->where('id', 'in', $ids)->update(['plan_id' => 0]);
        $this->success(lang('删除成功'),url('index')->build());
    }

    /**
     * 删除任务详情
     * @param $id
     */
    public function delItem($id)
    {
        $ids = implode(',', $id);
        $plan_model = new PlanTaskModel();
        $plan_model->where('id', 'in', $ids)->delete();
        $this->success(lang('删除成功'), url('index')->build());
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id)
    {
        return [
            '详情' => [
                'auth' => 'plan/view',
                'href' => url('view', ['plan_id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '会员' => [
                'auth' => 'plan/member',
                'href' => url('member', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '编辑' => [
                'auth' => 'plan/add',
                'href' => url('add', ['id' => $id])->build(),
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
