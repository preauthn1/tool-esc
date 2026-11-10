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

use app\admin\model\NoticeModel as Model;
use app\admin\model\MemberModel;
use app\common\model\LangModel;
use app\common\model\NoticeInfoModel;
use think\facade\View;

class Notice extends Base
{

    // 文章列表
    public function index()
    {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['searchText']) && $param['searchText'] != '') {
                $where[] = ['id', '', $param['searchText']];
            }
            $langs = getLangsColumn();
            $notice = new Model();
            $selectResult = $notice->getNoticeByWhere($where, $offset, $limit)->toArray();
            $status = config('app.status');
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['status'] = $status[$vo['status']];
//                $selectResult[$key]['create_time'] = date('Y-m-d H:i:s', $vo['create_time']);
                $selectResult[$key]['operate'] = showOperate($this->makeButton($vo['id']));
                $selectResult[$key]['lang'] = $langs[$vo['lang']];
                $selectResult[$key]['content'] = html_entity_decode($vo['content']);
            }
            $return['total'] = $notice->getAllNotice($where);  // 总数据
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
            $param['user_id'] = $this->request->param('user_id/d');
            $param['create_time'] = strtotime($param['create_time']);
            $model = new Model;
            if ($param['user_id'] > 0) {
                $user = MemberModel::where('id', $param['user_id'])->find();
                if (empty($user)) {
                    $this->error('用户ID有误');
                }
            }
            $tr_content = isset($param['tr_content']) ? $param['tr_content'] : [];
            unset($param['tr_content']);
            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                $result = $model->save($param);
                $param['id'] = $model->getLastInsID();
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            $add_list = [];
            foreach ($tr_content as $key => $value) {
                $add_list[] = [
                    'lang' => $key,
                    'notice_id' => $param['id'],
                    'content' => $tr_content[$key],
                    'create_time' => $param['create_time'],
                ];
            }
            NoticeInfoModel::addInfo($param['id'], $add_list);
            $this->clearCache();
            $this->success(lang('提交成功'), '', 'close');
        }
        $info = Model::where('id', $id)->find();
        if (empty($info)) {
            $info = [
                'status' => 1,
            ];
        }
        $lang_list = LangModel::where('status', 1)->order(['is_default' => 'DESC', 'id' => 'ASC'])->select();
        $tr_content = [];
        foreach ($lang_list as $value) {
            $tr_title[$value['lang']] = '';
            $tr_content[$value['lang']] = '';
        }
        if (!empty($id)) {
            $share_id = $id;
            $list = NoticeInfoModel::where('notice_id', $share_id)->select();
            foreach ($list as $value) {
                $tr_content[$value['lang']] = $value['content'];
            }
        }

        View::assign("info", $info);
        View::assign("langs", $lang_list);
        View::assign("status", config('app.status'));
        View::assign('tr_content', $tr_content);
        return View::fetch();
    }

    public function del()
    {
        $id = input('param.id');

        $notice = new Model();
        $flag = $notice->delNotice($id);
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
                'auth' => 'notice/add',
                'href' => url('add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'notice/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

    public function clearCache()
    {
        $lang = LangModel::where('status', 1)->column('lang');
        $redis = \think\facade\Cache::store('redis');
        foreach ($lang as $value) {
            $redis_key = config("redis_cache_key.notice") . md5($value);
            $redis->delete($redis_key);
        }
    }
}
