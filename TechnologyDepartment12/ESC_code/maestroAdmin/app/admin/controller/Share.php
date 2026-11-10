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
use app\common\model\PlanModel;
use app\common\model\ShareInfoModel;
use app\common\model\ShareModel;
use think\facade\View;

class Share extends Base
{

    // 分享任务列表
    public function index()
    {
        $account_type = ShareModel::$ACCOUNT_TYPE_NAME;
        $status_type = [
            1 => '开启',
            2 => '关闭',
        ];
        $award_type = [
            1 => '总共一次',
            2 => '每天一次',
        ];
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
            $model = new ShareModel();
            $result = $model->where($where)->limit($offset, $limit)->order(['sort' => 'ASC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            foreach ($result as $key => $vo) {
                $result[$key]['account_type_str'] = $account_type[$vo['account_type']];
                $result[$key]['status_str'] = $status_type[$vo['status']];
                $result[$key]['award_str'] = $award_type[$vo['award']];
                $result[$key]['img'] = $vo['img'] ? '<img src="' . toMedia($vo['img']) . '" width="40px" height="40px">' : '-';
//                $result[$key]['create_time'] = date('Y-m-d H:i:s', $vo['create_time']);
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
            $model = new ShareModel();
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
            $tr_title = $param['tr_title'];
            $tr_step = $param['tr_step'];
            $tr_content = $param['tr_content'];
            $add_list = [];
            foreach ($tr_title as $key => $value) {
                $add_list[] = [
                    'lang' => $key,
                    'share_id' => $param['id'],
                    'title' => $tr_title[$key],
                    'step' => $tr_step[$key],
                    'content' => $tr_content[$key],
                ];
            }
            ShareInfoModel::addInfo($param['id'], $add_list);
            $this->success(lang('提交成功'),'','close');
        }
        $info = ShareModel::where('id', $id)->find();
        $account_type = ShareModel::$ACCOUNT_TYPE_NAME;
        $status_type = [
            1 => '开启',
            2 => '关闭',
        ];
        $award_type = [
            1 => '总共一次',
            2 => '每天一次',
        ];
        View::assign("info", $info);
        View::assign("account_type", $account_type);
        View::assign('status_type', $status_type);
        View::assign('award_type', $award_type);
        $lang_list = LangModel::where('status', 1)->field('title,remark,lang')->select();
        $tr_title = [];
        $tr_step = [];
        $tr_content = [];
        foreach ($lang_list as $value) {
            $tr_title[$value['lang']] = '';
            $tr_step[$value['lang']] = '';
            $tr_content[$value['lang']] = '';
        }
        if (!empty($id)) {
            $share_id = $id;
            $list = ShareInfoModel::where('share_id', $share_id)->select();
            foreach ($list as $value) {
                $tr_title[$value['lang']] = $value['title'];
                $tr_step[$value['lang']] = $value['step'];
                $tr_content[$value['lang']] = $value['content'];
            }
        }
        View::assign('lang_list', $lang_list);
        View::assign('tr_title', $tr_title);
        View::assign('tr_step', $tr_step);
        View::assign('tr_content', $tr_content);
        return View::fetch('add');

    }

    /**
     * 删除任务
     * @param $id
     */
    public function del($id)
    {
        $ids = implode(',', $id);
        $plan_model = new ShareModel();
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
                'href' => url('add', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ]
        ];
    }
}
