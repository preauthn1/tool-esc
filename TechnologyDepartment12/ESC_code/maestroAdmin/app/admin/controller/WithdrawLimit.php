<?php

namespace app\admin\controller;

use app\common\model\WithdrawLimitModel AS Model;
use app\admin\model\MemberModel;

class WithdrawLimit extends Base {

    public function index() {
        if (request()->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['user_id']) && $param['user_id'] != '') {
                $uid = getUseridByMobile($param['user_id']);
                $where[] = ['user_id', $uid ?? $param['user_id']];
            }
            $model = new Model();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->count();
            foreach ($result as $key => $vo) {
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
                $result[$key]['status'] = $vo['status'] == 1 ? '启用' : '停用';
                $result[$key]['mobile'] = MemberModel::where('id', $vo['user_id'])->value("mobile");
            }
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }
        return View::fetch();
    }

    public function add() {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.');
            $model = new Model;
            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                $user = MemberModel::where('id', $param['user_id'])->find();
                if (!$user) {
                    $user = MemberModel::where('mobile', $param['user_id'])->find();
                }
                if (!$user) {
                    return $this->error('用户信息不存在');
                }
                $param['user_id'] = $user['id'];
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            } else {
                insert_operation_log('限制用户提现设置:' . ($id > 0 ? '修改' : '添加'), $param);
            }
            $url = url('index')->build();
            $this->success('提交成功', $url);
        }
        $info = Model::where('id', $id)->find();
        if (empty($info)) {
            $info['status'] = 1;
        }
        View::assign("info", $info);
        return View::fetch();
    }

    // 删除用户
    public function del() {
        $id = input('param.id');
        $info = Model::find($id);
        if ($info) {
            insert_operation_log('限制用户提现设置:删除', $info->toArray());
            $info->delete();
        }
        $this->success('删除成功', url('index')->build());
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id) {
        return [
            '编辑' => [
                'auth' => 'withdraw_limit/add',
                'href' => url('withdraw_limit/add', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'withdraw_limit/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }
}
