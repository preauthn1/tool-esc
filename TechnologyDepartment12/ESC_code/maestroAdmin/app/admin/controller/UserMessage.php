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
use app\admin\model\MemberModel;
use app\admin\model\UserMessageModel;
use app\common\model\UserModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\db\exception\DbException;
use think\facade\View;

class UserMessage extends Base
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

            // 用户名搜索
            if (!empty($param['user_name'])) {
                $user_ids = UserModel::whereLike('user_name', "%{$param['user_name']}%")->column('id');
                if (!empty($user_ids)) {
                    $where[] = ['user_id', 'in', $user_ids];
                }
            }

            // 标题搜索
            if (!empty($param['title'])) {
                $where[] = ['title', 'like', "%{$param['title']}%"];
            }

            // 内容搜索
            if (!empty($param['content'])) {
                $where[] = ['content', 'like', "%{$param['content']}%"];
            }

            // 类型搜索
            if (isset($param['type']) && $param['type'] !== '') {
                $where[] = ['type', '=', $param['type']];
            }

            // 是否已读
            if (isset($param['is_read']) && $param['is_read'] !== '') {
                $where[] = ['is_read', '=', $param['is_read']];
            }

            // 是否弹窗
            if (isset($param['is_pop']) && $param['is_pop'] !== '') {
                $where[] = ['is_pop', '=', $param['is_pop']];
            }

            $message = new UserMessageModel();
            $selectResult = $message->getUserMessageWhere($where, $offset, $limit)->toArray();
            $status = config('app.status');
            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                $user = UserModel::where('id', $vo['user_id'])->find();
                $selectResult[$key]['user_info'] = $user['id'] . ' / ' . $user['user_name'];
                if ($vo['admin_id'] == 0) {
                    $selectResult[$key]['admin_id'] = '系统消息';
                } else {
                    $admin = \app\admin\model\UserModel::where('id', $vo['admin_id'])->find();
                    $selectResult[$key]['admin_id'] = $admin['user_name'];
                }
                $selectResult[$key]['type_str'] = UserMessageModel::$typeMap[$vo['type']];
                $selectResult[$key]['is_read'] = $vo['is_read'] ? '已读' : '未读';
                $selectResult[$key]['is_pop'] = $vo['is_pop'] ? '是' : '否';
                $selectResult[$key]['read_time'] = $vo['read_time'] ? date('Y-m-d H:i:s', $vo['read_time']) : '--';
                // 截取内容前100个字符
                $selectResult[$key]['content_short'] = mb_substr($vo['content'], 0, 100, 'utf-8') . (mb_strlen($vo['content'], 'utf-8') > 100 ? '...' : '');
                $selectResult[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = $message->getAllUserMessage($where);  //总数据
            $return['rows'] = $selectResult;
            return json($return);
        }

        // 获取消息类型列表
        View::assign('typeMap', UserMessageModel::$typeMap);
        return View::fetch();
    }

    /**
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws DataNotFoundException
     */
    public function add()
    {
        $id = $this->request->param("id/d");
        $model = new UserMessageModel();
        if (request()->isPost()) {
            $param = input('post.');

            $result = $this->validate($param, 'UserMessageValidate.add');
            if (true !== $result) {
                // 验证失败 输出错误信息
                $this->error($result);
            }

            if ($param['batch_edit_type'] == 1 && ($param['specify_member'] == '' && !array_key_exists('level_id',
                        $param))) {
                $this->error(lang('请指定会员或选择接收对象'));
            }

            if ($param['batch_edit_type'] == 2 && ($param['account'] == '')) {
                $this->error(lang('请输入会员账号'));
            }

            $user_ids = [];
            if ($param['batch_edit_type'] == 1) {
                // 获取指定会员的id和接收对象的id
                $specify_member = explode(',', $param['specify_member']);
                $query = UserModel::whereIn('user_name', $specify_member);
                if (isset($param['level_id'])) {
                    if ($param['level_id']) {
                        $query->whereOr(function ($query) use ($param) {
                            $query->whereIn('level_id', array_values($param['level_id']));
                        });
                    }
                }
                $user_ids = $query->column('id');
            }

            if ($param['batch_edit_type'] == 2) {
                // 获取提交用户账户的所有下级
                $user = MemberModel::where('user_name', $param['account'])->find();
                if (!$user) {
                    $this->error('用户不存在');
                }
                // 获取用户的下级
                $user_ids = MemberModel::whereLike('tree_id', "%/{$user->id}/%")
                    ->where('id', '<>', $user->id)
                    ->column('id');
                if (count($user_ids) == 0) {
                    $this->error('用户不存在下级');
                }
            }
            $data = [];
            foreach ($user_ids as $user_id) {
                $data[] = [
                    'user_id' => $user_id,
                    'admin_id' => session('id'),
                    'type' => UserMessageModel::TYPE_CUSTOM,
                    'title' => $param['title'],
                    'content' => $param['content'],
                    'is_pop' => $param['is_pop'],
                ];
            }
            $result = $model->saveAll($data);

            if (false === $result) {
                $this->error($model->getError());
            } else {
                insert_operation_log('站内信:' . ($id > 0 ? '修改' : '添加'), $param);
            }
            $this->success(lang('提交成功'), url('index')->build());
        }
        $levels = LevelModel::field('id, level_id,level_name')->select();
        View::assign("levels", $levels);
        return View::fetch();
    }

    public function del($id)
    {
        if (is_array($id)) {
            $ids = implode(',', $id);
        } else {
            $ids = $id;
        }
        $user_message_model = new UserMessageModel();
        $user_message_model->where('id', 'in', $ids)->delete();
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
            '详情' => [
                'auth' => 'userMessage/detail',
                'href' => "javascript:showDetail(" . $id . ")",
                'btnStyle' => 'info',
                'icon' => 'fa fa-eye'
            ],
            '删除' => [
                'auth' => 'userMessage/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

}
