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

use app\common\model\PddUserFakerModel;
use think\facade\View;

class PddFaker extends Base
{

    //用户列表
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
                $where[] = ['user_name', 'like', '%' . $param['searchText'] . '%'];
            }
            $selectResult = PddUserFakerModel::where($where)->limit($offset, $limit)->order(['sort' => 'ASC'])->select();
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = PddUserFakerModel::where($where)->count();  //总数据
            $return['rows'] = $selectResult;
            return json($return);
        }
        return View::fetch();
    }

    public function add()
    {
        $id = $this->request->param("id/d");
        $model = new PddUserFakerModel();
        if (request()->isPost()) {
            $param = input('post.');
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
            $this->success(lang('提交成功'), url('index')->build());
        }
        $info = $model->where('id', $id)->find();
        if (empty($info)) {
            $sort = PddUserFakerModel::max('sort');
            //id: 15
            //user_name: 1131****21
            //earnings: 128.00
            //level_id: 2
            //sort: 2
            $info = [
                'id' => '',
                'user_name' => '',
                'earnings' => '',
                'level_id' => 1,
                'sort' => intval($sort) + 1
            ];
        }
        View::assign("info", $info);
        return View::fetch();
    }

    public function del()
    {
        $id = $this->request->param('id/d');
        $info = PddUserFakerModel::find($id);
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
                'auth' => 'pdd_faker/add',
                'href' => url('pdd_faker/add', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'pdd_faker/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

}
