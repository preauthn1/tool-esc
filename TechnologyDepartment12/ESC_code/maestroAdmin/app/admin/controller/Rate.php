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

use app\admin\model\RateModel;
use think\facade\View;

class Rate extends Base {

    //用户列表
    public function index() {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            $rate = new RateModel();
            $selectResult = $rate->getRateByWhere($where, $offset, $limit);
            $status = config('app.status');
            // 拼装参数

            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['status'] = $status[$vo['status']];
                $P = 10000;
                if ($vo['days'] > 0) {
                    $i = floatval($vo['rate'] / 10000 / $vo['days']);
                    $n = $vo['days'];
                    $income = round($P * pow(1 + $i, $n) - $P, 2);
                    $day_rate = sprintf4f($vo['rate']/$vo['days']/100) . "%";
                }else{
                    $income = sprintf4f($P * $vo['rate'] / 10000);
                    $day_rate = '-';
                }
                $selectResult[$key]['income'] = $income;
                $selectResult[$key]['rate'] = $day_rate . " / " . ($vo['rate']/100) . "%";
                $selectResult[$key]['redemption_fee'] = ($vo['redemption_fee']/100) . "%";
                $selectResult[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = $rate->getAllRate($where);  //总数据
            $return['rows'] = $selectResult;
            return json($return);
        }
        return View::fetch();
    }

    public function add() {
        $id = $this->request->param("id/d");
        $model = new RateModel();
        if (request()->isPost()) {
            $param = input('post.');
            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            } else {
                insert_operation_log('余额宝设置:' . ($id > 0 ? '修改' : '添加'), $param);
            }
            $this->success(lang('提交成功'), url('index')->build());
        }
        $info = $model->where('id', $id)->find();
        if (empty($info)) {
            $info = [
                'status' => 1
            ];
        }
        View::assign("rate", $info);
        View::assign("status", config('app.status'));
        return View::fetch();
    }

    public function del() {
        $id = input('param.id');
        $info = RateModel::find($id);
        if ($info) {
            insert_operation_log('余额宝设置:删除', $info->toArray());
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
                'auth' => 'rate/add',
                'href' => url('rate/add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'rate/ratedel',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

}
