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

use app\common\model\LangModel;
use think\facade\Db;
use think\facade\View;

class Lang extends Base {

    //语言列表
    public function index() {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            $selectResult = LangModel::where($where)->limit($offset, $limit)->order(['is_default' => 'DESC', 'id' => 'ASC'])->select();
            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['is_default'] = lang($vo['is_default'] ? '默认地区' : '否');
                $selectResult[$key]['is_default_lang'] = lang($vo['is_default_lang'] ? '默认语言' : '否');
                $selectResult[$key]['status'] = lang($vo['status'] ? '启用' : '禁用');
                $selectResult[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = LangModel::where($where)->count();  //总数据
            $return['rows'] = $selectResult;
            return json($return);
        }
        return View::fetch();
    }

    // 添加语言
    public function add() {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.', '', 'trim');
            unset($param['id']);
            if ($param['is_default'] == 1) {
                LangModel::where('is_default', 1)->update(['is_default' => 0]);
            }
            if ($param['is_default_lang'] == 1) {
                LangModel::where('is_default_lang', 1)->update(['is_default_lang' => 0]);
            }
            if ($id > 0) {
                (new LangModel())->save($param, ['id' => $id]);
            } else {
                LangModel::create($param);
            }
            $this->success(lang('提交成功'),'','close');
        }
        $info = LangModel::where('id', $id)->find();
        View::assign("info", $info);
        return View::fetch();
    }

    // 删除语言
    public function del() {
        $id = $this->request->param('id/d');
        $model = new LangModel();
        $flag = $model->where('id', $id)->delete();
        if ($flag) {
            return json(msg(1, url('index')->build(), lang('删除成功')));
        }else{
            return json(msg(-1, url('index')->build(), lang('删除失败')));
        }
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id) {
        return [
            '编辑' => [
                'auth' => 'lang/add',
                'href' => url('lang/add', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'lang/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

}
