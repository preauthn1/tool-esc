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

use app\admin\model\LevelModel;
use app\common\model\GoodsModel;
use think\facade\Cache;
use think\facade\View;

class Goods extends Base
{

    public function index()
    {
        $level_list = LevelModel::column('level_name', 'level_id');
        if (request()->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['level_id']) && $param['level_id'] != '') {
                $where[] = ['level_id', '', $param['level_id']];
            }

            if (isset($param['name']) && $param['name'] != '') {
                $where[] = ['name', 'LIKE', "%{$param['name']}%"];
            }
//            print_r($where);exit;
            $model = new GoodsModel();
            $result = $model->where($where)->limit($offset, $limit)->order(['sort' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            $status = config('app.status');
            foreach ($result as $key => $vo) {
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
                $result[$key]['status'] = $status[$vo['status']];
                $result[$key]['level_name'] = isset($level_list[$vo['level_id']]) ? $level_list[$vo['level_id']] : '-';
                $result[$key]['pic'] = $vo['pic'] ? '<img src="' . toMedia($vo['pic']) . '" width="40px" height="40px">' : '-';
            }
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }
        View::assign('level_list', $level_list);
        return View::fetch();
    }

    public function add()
    {
        $goods_id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = $this->request->param();
            $model = new GoodsModel();
            $param['name'] = str_replace('"','',$param['name']);
            $param['name'] = str_replace("'",'',$param['name']);
            if ($goods_id > 0) {
                $result = $model->where('id', $goods_id)->update($param);
            } else {
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            $url = url('index')->build();
            $redis = Cache::store('redis');
            $redis_key = "index_vip_data";
            $redis->set($redis_key, [],1);
            $this->success(lang('提交成功'), $url, 'close');
        }
        $info = GoodsModel::where('id', $goods_id)->find();
        if ($info) {
            $info['main_pic_arr'] = explode(",", $info['main_pic']);
        }
        $config = getConfig();
        $level_list = LevelModel::column('level_name', 'level_id');
        View::assign('level_list', $level_list);
        View::assign("status", config('app.status'));
        View::assign("config", $config);
        View::assign("info", $info);
        return View::fetch();
    }

    public function del()
    {
        $id = $this->request->param('id/d');
        $info = GoodsModel::find($id);
        if ($info) {
            $info->delete();
        }
        $this->success(lang('删除成功'), url('index'));
    }

    // 变更商品状态
    public function checkStatus($id, $status, $type = 0)
    {
        $goods = new GoodsModel();
        if ($type == 1) {
            $ids = implode(',', $id);
            $ret = $goods->updateStatus(['status' => $status], ['id' => ['in', $ids]]);
        } else {
            $ret = $goods->updateStatus(['status' => $status], ['id' => $id]);
        }
        return json($ret);
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
                'auth' => 'goods/add',
                'href' => url('goods/add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'goods/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

}
