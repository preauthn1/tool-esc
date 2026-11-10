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

use app\common\model\ProductInfoModel;
use think\facade\Cache;

class Productinfo extends Base
{

    //货币管理
    public function index()
    {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (!empty($param['account_type'])) {
                $where[] = ['account_type', '', $param['account_type']];
            }
            $model = new ProductInfoModel();
            $result = $model->where($where)->limit($offset, $limit)->order(['proorder' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            $redis = Cache::store('redis')->handler();
            $redis_key = "shop_markethuobi";
            foreach ($result as $key => $vo) {
                $cache = json_decode($redis->hget($redis_key, $vo['procode']), true);
                if ($cache) {
                    $temp = array_merge($vo->toArray(), $cache);
                    $temp['update_time'] = strtotime($temp['update_time']);
                    $temp['create_time'] = strtotime($temp['create_time']);
                    unset($temp['logo']);
                    (new ProductInfoModel)->save($temp, ['id' => $temp['id']]);
                }
                $result[$key] = $vo;
                $result[$key]['logo'] = '<img src="' . toMedia($vo['logo']) . '" style="height:40px">';
                $result[$key]['change_rate'] = ($vo['change_rate']) . "%";
                $result[$key]['random'] = floatval($vo['random_min']) . "~" . floatval($vo['random_max']);
                $result[$key]['isopen_str'] = $vo['isopen'] == 1 ? '开市' : '休市';
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = $total;  // 总数据
            $return['rows'] = $result;
            return json($return);
        }
        return View::fetch();
    }

    public function add()
    {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.');
            $model = new ProductInfoModel();
            if ($id > 0) {
                $param['update_time'] = time();
                $result = $model->where('id', $id)->update($param);
            } else {
                unset($param['id']);
                $param['create_time'] = time();
                $param['update_time'] = time();
                $result = $model->save($param);
                $param['id'] = $model->getLastInsID();
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            $this->success(lang('提交成功'), '', 'close');
        }
        $info = ProductInfoModel::where('id', $id)->find();
        View::assign("info", $info);
        return View::fetch('add');

    }

    /**
     * 删除任务
     * @param $id
     */
    public function del($id)
    {
        $ids = implode(',', $id);
        $plan_model = new ProductInfoModel();
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
            '编辑' => [
                'auth' => 'share/add',
                'href' => url('add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ]
        ];
    }
}
