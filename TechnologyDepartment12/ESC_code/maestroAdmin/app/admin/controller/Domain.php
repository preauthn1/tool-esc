<?php

namespace app\admin\controller;

use app\common\model\DomainModel AS Model;
use think\facade\View;
use util\BtApi;

class Domain extends Base {

    public function index() {
        if (request()->isAjax()) {
            $langs = getLangsColumn();
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['name']) && $param['name'] != '') {
                $where[] = ['title', 'LIKE', "%{$param['name']}%"];
            }
            $model = new Model();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            foreach ($result as $key => $vo) {
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
                $result[$key]['status'] = $vo['status'] == 1 ? lang('启用') : lang('停用');
                $result[$key]['lang'] = isset($langs[$vo['lang']]) ? $langs[$vo['lang']] : '-';
                $result[$key]['logo'] = $vo['logo'] ? '<img src="' . toMedia($vo['logo']) . '" width="40px" height="40px">' : '-';
            }
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }
        View::assign("langs", getLangs());
        return View::fetch();
    }

    public function add() {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.');
            $result = $this->validate($param, 'DomainValidate');
            if (true !== $result) {
                return json(msg(-1, '', lang($result)));
            }
            $model = new Model;
            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                $result = $model->save($param);
            }
            $bt = new \util\BtApi();
            $list = $bt->addDomain($param['domain']);
            if (!empty($list) && isset($list['status']) && $list['status']) {
                $model->where('domain', $param['domain'])->update(['is_bind' => 1]);
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            $url = url('index')->build();
            $this->success(lang('提交成功'), $url);
        }
        $info = Model::where('id', $id)->find();
        if (empty($info)) {
            $model = new Model();
            $info = array_fill_keys($model->getTableFields(), '');
            $info['status'] = 1;
        }
        View::assign("langs", getLangs());
        View::assign("info", $info);
        View::assign("SERVER_ADDR", \think\facade\Env::get('SERVER_ADDR'));

        return View::fetch();
    }

    // 删除用户
    public function del() {
        $id = input('param.id');
        $info = Model::find($id);
        if ($info) {
            $info->delete();
        }
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
                'auth' => 'domain/add',
                'href' => url('domain/add', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'domain/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

}
