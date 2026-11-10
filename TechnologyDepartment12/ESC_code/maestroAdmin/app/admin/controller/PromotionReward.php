<?php

namespace app\admin\controller;

use app\common\repository\PromotionRewardDayRepository;
use app\common\repository\PromotionRewardRepository;
use think\facade\View;

class PromotionReward extends Base
{

    // 连续推广任务列表
    public function index()
    {
        View::assign('title', lang('连续推广任务列表'));
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['name']) && $param['name'] != '') {
                $where[] = ['name', 'LIKE', "%{$param['name']}%"];
            }
            $model = (new PromotionRewardRepository())->model();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            $sign_status = config('app.status');
            foreach ($result as $key => $vo) {
//                $result[$key]['create_time'] = date('Y-m-d H:i:s', $vo['create_time']);
                $result[$key]['status_str'] = $sign_status[$vo['status']];
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = $total;  // 总数据
            $return['rows'] = $result;
            return json($return);
        }
        return View::fetch();
    }

    // 签到奖励详情列表
    public function view()
    {
        $sign_id = $this->request->param("sign_id/d");;
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [
                ['sign_id' , '=',  $sign_id]
            ];
            if (isset($param['day']) && $param['day'] != '') {
                $where[] = ['day', 'LIKE', "%{$param['day']}%"];
            }
            $model = (new PromotionRewardDayRepository())->model();
            $result = $model->where($where)->limit($offset, $limit)->order('day ASC')->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            $award_type_list = PromotionRewardDayRepository::$award_type_list;
            foreach ($result as $key => $vo) {
//                $result[$key]['create_time'] = date('Y-m-d H:i:s', $vo['create_time']);
                $result[$key]['award_type_str'] = $award_type_list[$vo['award_type']];
                $result[$key]['operate'] = showOperate($this->makeItemButton($vo['id']));
            }
            $return['total'] = $total;  // 总数据
            $return['rows'] = $result;
            return json($return);
        }
        View::assign('sign_id', $sign_id);
        return View::fetch();
    }

    public function add()
    {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.');
            $model = (new PromotionRewardRepository())->model();
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
            $this->success(lang('提交成功'), '', 'close');
        }
        $info = $model = (new PromotionRewardRepository())->model()->where('id', $id)->find();
        if (empty($info)) {
            $info = [
                'id' => '',
                'name' => '',
                'day' => '',
                'status' => 1,
            ];
        }
        View::assign("info", $info);
        View::assign("status", config('app.status'));
        if (empty($id)) {
            return View::fetch('add');
        } else {
            return View::fetch('add');
        }

    }

    public function addItem()
    {
        $id = $this->request->param("id/d");
        $sign_id = $this->request->param("sign_id/d");
        if (request()->isPost()) {
            $param = input('post.');
            $model = (new PromotionRewardDayRepository())->model();
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
            $this->success(lang('提交成功'), '', 'close');
        }
        $info = (new PromotionRewardDayRepository())->model()->where('id', $id)->find();
        View::assign("sign_id", $sign_id);
        //1基础账户  2佣金账户
        View::assign("award_type_list", PromotionRewardDayRepository::$award_type_list);
        View::assign("info", $info);
        return View::fetch('addItem');
    }

    /**
     * 删除任务
     * @param $id
     */
    public function del($id)
    {
        $ids = implode(',', $id);
        $plan_model = (new PromotionRewardRepository())->model();
        $plan_model->where('id', 'in', $ids)->delete();
        $this->success(lang('删除成功'), url('index')->build());
    }

    /**
     * 删除任务详情
     * @param $id
     */
    public function delItem($id)
    {
        $ids = implode(',', $id);
        $plan_model = (new PromotionRewardDayRepository())->model();
        $plan_model->where('id', 'in', $ids)->delete();
        $this->success(lang('删除成功'), url('index'));
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id)
    {
        return [
            '详情' => [
                'auth' => 'promotion_reward/view',
                'href' => url('view', ['sign_id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '编辑' => [
                'auth' => 'promotion_reward/add',
                'href' => url('add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ]
        ];
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeItemButton($id)
    {
        return [
            '编辑' => [
                'auth' => '',
                'href' => "javascript:add('{$id}');",
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ]
        ];
    }
}
