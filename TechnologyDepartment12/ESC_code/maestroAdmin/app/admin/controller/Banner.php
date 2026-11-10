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

use app\admin\model\BannerModel as Model;
use think\facade\Db;
use think\facade\View;

class Banner extends Base
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
                $where[] = ['name', 'like', '%' . $param['searchText'] . '%'];
            }

            $banner = new Model();
            $selectResult = $banner->getBannerByWhere($where, $offset, $limit);
            $langs = getLangsColumn();
            $status = config('app.status');

            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['pic'] = '<img src="' . toMedia($vo['pic']) . '" style="height:40px">';
                $selectResult[$key]['status'] = $status[$vo['status']];
                $selectResult[$key]['lang'] = $langs[$vo['lang']];
                $selectResult[$key]['region'] = $vo['region'] == 'index' ? '首页' : '资产';
                $selectResult[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }

            $return['total'] = $banner->getAllBanner($where);  //总数据
            $return['rows'] = $selectResult;
            return json($return);
        }
        return View::fetch();
    }

    public function add()
    {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.');
            unset($param['file']);
            $model = new Model;
            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            $this->clearCache();
            $this->success(lang('提交成功'), '', 'close');
        }
        $info = Model::where('id', $id)->find();
        if (empty($info)) {
            $model = new Model();
            $info = array_fill_keys($model->getTableFields(), '');
            $info['status'] = 1;
        }
        $areas = [
            [
                'index' => 'index',
                'name' => '首页'
            ],
            [
                'index' => 'introduce',
                'name' => '资产'
            ]
        ];
        View::assign("areas", $areas);
        View::assign("info", $info);
        View::assign("langs", getLangs());
        View::assign("status", config('app.status'));
        return View::fetch();
    }

    // 删除用户
    public function del()
    {
        $id = input('param.id');

        $role = new Model();
        $flag = $role->delBanner($id);
        $this->clearCache();
        return json(msg($flag['code'], $flag['data'], lang($flag['msg'])));
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
                'auth' => 'banner/add',
                'href' => url('banner/add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'banner/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

    /**
     * 清空缓存
     */
    public function clearCache()
    {
        $redis = \think\facade\Cache::store('redis');
        $type = ['index', 'introduce'];
        foreach ($type as $vo) {
            $redis_key = config("redis_cache_key.banner") . $vo;
            $redis->delete($redis_key);
        }
    }
}
