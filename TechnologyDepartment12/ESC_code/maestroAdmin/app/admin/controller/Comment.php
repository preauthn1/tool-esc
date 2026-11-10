<?php

namespace app\admin\controller;

use app\common\model\CommentTextModel AS Model;
use think\facade\View;

class Comment extends Base {

    public function index() {
        if (request()->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['text']) && $param['text'] != '') {
                $where[] = ['text', 'LIKE', "%{$param['text']}%"];
            }
            $model = new Model();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
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
            $param = input('post.');
            $model = new Model;
            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            $url = url('index');
            $this->success(lang('提交成功'), $url);
        }
        $info = Model::where('id', $id)->find();
        View::assign("info", $info);
        return View::fetch();
    }

    // 删除用户
    public function del() {
        $id = input('param.id');
        $info = Model::find($id);
        if ($info) {
            $info->delete();
        }
        return json(msg(1, url('index'), lang('删除成功')));
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id) {
        return [
            '编辑' => [
                'auth' => 'comment/add',
                'href' => url('comment/add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'comment/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }
}
