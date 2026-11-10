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

use app\admin\model\FakerModel;
use think\facade\View;

class Faker extends Base {

    // 备份首页列表
    public function index() {

        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (!empty($param['user_id'])) {
                $where[] = ['user_id', '', $param['user_id']];
            }
            $model = new FakerModel();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            // 拼装参数
            foreach ($result as $key => $vo) {
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
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
            $param = input('post.', '', 'trim');
            unset($param['id']);
            if ($id > 0) {
                (new FakerModel())->save($param, ['id' => $id]);
            }else{
                FakerModel::create($param);
            }
            return json(msg(1, url('index')->build(), lang('提交成功')));
        }
        $info = FakerModel::where('id', $id)->find();
        View::assign("info", $info);
        return View::fetch();
    }

    // 删除用户
    public function del() {
        $id = input('param.id');
        FakerModel::where('id', $id)->delete();
        return json(msg(1, url('index')->build(), lang('删除成功')));
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id) {
        return [
            '编辑' => [
                'auth' => 'fake/add',
                'href' => url('fake/add', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'fake/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

}
