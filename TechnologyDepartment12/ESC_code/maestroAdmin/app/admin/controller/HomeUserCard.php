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

use app\admin\model\HomeUserCardModel;
use think\facade\View;

class HomeUserCard extends Base
{

    //用户卡片列表
    public function index()
    {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (!empty($param['searchText'])) {
                $where[] = ['bank_name', 'like', '%' . $param['searchText'] . '%'];
                $where[] = ['true_name', 'like', '%' . $param['searchText'] . '%'];
                $where[] = ['bank_number', 'like', '%' . $param['searchText'] . '%'];
            }
            $return['total'] = HomeUserCardModel::where($where)->count();
            $selectResult = HomeUserCardModel::where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['operate'] = showOperate($this->makeButton($vo['id']));
                $selectResult[$key]['type_text'] = $vo['type'] == 'Bank' ? '银行卡' : '支付宝';
            }
            $return['rows'] = $selectResult;
            return json($return);
        }
        return View::fetch();
    }

    public function add()
    {
        $id = $this->request->param("id/d");
        $model = new HomeUserCardModel();
        if (request()->isPost()) {
            $param = input('post.');
            if ($id > 0) {
                $param['update_time'] = time();
                $map = [
                    'bank_number' => $param['bank_number']
                ];
                $checkData = $model->where($map)->where('user_id', '<>', $param['user_id'])->find();
                if (!empty($checkData)) {
                    $this->error(lang('根据平台的政策，禁止同一IP地址或同一人注册多个账户进行推广活动。'));
                }
                $map = [
                    'user_id' => $param['user_id'],
                    'type' => $param['type']
                ];
                $checkData = $model->where($map)->find();
                if (!empty($checkData) && $param['id'] !== $checkData['id']) {
                    $this->error(lang('每种类型只能绑定一个账户'));
                }
                $result = $model->where('id', $id)->update($param);
            } else {
                unset($param['id']);
                $map = [
                    'bank_number' => $param['bank_number']
                ];
                $checkData = $model->where($map)->find();
                if (!empty($checkData)) {
                    $this->error(lang('根据平台的政策，禁止同一IP地址或同一人注册多个账户进行推广活动。'));
                }
                $map = [
                    'user_id' => $param['user_id'],
                    'type' => $param['type']
                ];
                $checkData = $model->where($map)->find();
                if (!empty($checkData)) {
                    $this->error(lang('每种类型只能绑定一个账户'));
                }
                $param['create_time'] = time();
                $param['update_time'] = time();
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            $this->success(lang('提交成功'), url('index')->build());
        }
        $info = $model->where('id', $id)->find();
        if (empty($info)) {
            $info = [
                'id' => '',
                'user_id' => '',
                'type' => 'Bank',
                'bank_id' => '',
                'bank_name' => '',
                'true_name' => '',
                'bank_number' => '',
                'bank_kh' => '',
                'bank_address' => ''
            ];
        }
        View::assign("info", $info);
        return View::fetch();
    }

    public function del()
    {
        $id = $this->request->param('id/d');
        $info = HomeUserCardModel::find($id);
        if ($info) {
            $info->delete();
        }
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
            '编辑' => [
                'auth' => 'homeUserCard/add',
                'href' => url('homeUserCard/add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'homeUserCard/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

}
