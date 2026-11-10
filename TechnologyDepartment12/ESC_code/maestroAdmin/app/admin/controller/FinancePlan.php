<?php

namespace app\admin\controller;

use app\admin\model\RechargeModel;
use app\admin\model\WithdrawModel;
use app\common\model\FinancePlanModel;
use app\common\model\FinancePlanTaskModel;
use app\common\model\PlanTaskModel;
use app\common\model\UserModel;
use think\facade\View;

class FinancePlan extends Base
{

    // 计划任务列表
    public function index()
    {
        $type = FinancePlanModel::$TYPE_NAME;
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

            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time = strtotime($param['endTime'] . " 23:59:59");
                $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            $model = new FinancePlanModel();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            foreach ($result as $key => $vo) {
                $result[$key]['type_str'] = $type[$vo['type']];
//                $result[$key]['create_time'] = date('Y-m-d H:i:s', $vo['create_time']);
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = $total;  // 总数据
            $return['rows'] = $result;
            return json($return);
        }
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
                ['plan_id' , '',  $plan_id]
            ];
            if (isset($param['name']) && $param['name'] != '') {
                $where[] = ['name', 'LIKE', "%{$param['name']}%"];
            }
//            if (!empty($param['startTime']) && !empty($param['endTime'])) {
//                $start_time = strtotime($param['startTime'] . " 00:00:00");
//                $end_time = strtotime($param['endTime'] . " 23:59:59");
//                $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
//            }
            $model = new FinancePlanTaskModel();
            $result = $model->where($where)->limit($offset, $limit)->order('sort ASC')->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            foreach ($result as $key => $vo) {
//                $result[$key]['create_time'] = date('Y-m-d H:i:s', $vo['create_time']);
                $result[$key]['operate'] = showOperate($this->makeItemButton($vo['id']));
            }
            $return['total'] = $total;  // 总数据
            $return['rows'] = $result;
            return json($return);
        }
        View::assign('plan_id', $plan_id);
        return View::fetch();

    }

    public function add()
    {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.');
            $model = new FinancePlanModel();
            if (!isset($param['status'])) {
                $param['status'] = 0;
            }
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
        $info = FinancePlanModel::where('id', $id)->find();
        if (empty($info)) {
            $info = [
                'type' => 1,
            ];
        }
        $type = FinancePlanModel::$TYPE_NAME;
        if (!empty($id)) {
            $plan_list = FinancePlanModel::where('id', "<>", $id)->select();
        } else {
            $plan_list = FinancePlanModel::select();
        }
        View::assign("info", $info);
        View::assign('type_list', $type);
        View::assign('plan_list', $plan_list);
        return View::fetch();
    }

    public function addItem()
    {
        $id = $this->request->param("id/d");
        $plan_id = $this->request->param("plan_id/d");
        $index = $this->request->param("index/d");
        if (request()->isPost()) {
            $param = input('post.');
            $model = new FinancePlanTaskModel();
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
        $info = FinancePlanTaskModel::where('id', $id)->find();
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
        $plan_model = new FinancePlanModel();
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
        $plan_model = new FinancePlanTaskModel();
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
                'auth' => 'finance_plan/view',
                'href' => url('view', ['plan_id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '会员' => [
                'auth' => 'finance_plan/member',
                'href' => url('member', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '编辑' => [
                'auth' => 'finance_plan/add',
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


    // 修改任务状态
    public function changeStatus()
    {
        $param = $this->request->param();

        $field = '';
        switch($param['type']) {
            case 'status':
                $field = 'status';
                break;
            default:
                $this->error(lang('参数错误'));
        }
        $res = FinancePlanModel::update([$field => !$param['val']], ['id' => $param['id']]);
        if ($res) {
            $this->success(lang('修改成功'));
        }

        $this->error(lang('修改失败'));
    }
}
