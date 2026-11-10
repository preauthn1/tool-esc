<?php

namespace app\admin\controller;

use app\common\model\ActivitynewModel;
use app\common\model\UserModel;
use think\facade\View;

class ActivitynewMultiplier extends Base
{

    public function index()
    {
        $param = $this->request->param();
        if (request()->isAjax()) {
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            $where[] = ['reward_multiplier', '>', 1];
            if (!empty($param['username'])) {
                $user_ids = UserModel::where('id|mobile', "like", "%" . $param['username'] . "%")->column('id');
                if (!empty($user_ids)) {
                    $where[] = ['id', 'in', $user_ids];
                }
            }
            $activity_id = isset($param['activity_id']) ? $param['activity_id'] : '';
            $activity_info = ActivitynewModel::where('id', $activity_id)->find();
            if (empty($activity_info)) {
                return json(['code' => 0, 'msg' => '活动不存在']);
            }
            $activity_info = $activity_info->toArray();
            $reward = $activity_info['reward'];
            $result = UserModel::field('id,user_name,reward_multiplier')
                ->where($where)
                ->limit($offset, $limit)
                ->order(['id' => 'desc'])
                ->select()->toArray();
            $total = UserModel::where($where)->count();
            foreach ($result as $key => &$vo) {
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
                $vo['reward'] = $reward * $vo['reward_multiplier'];
            }
            unset($vo);
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }
        $activity_id = isset($param['activity_id']) ? $param['activity_id'] : '';
        View::assign('activity_id', $activity_id);
        return View::fetch();
    }

    public function add()
    {
        if (request()->isAjax()) {
            $param = $this->request->param();
            $id = isset($param['id']) ? $param['id'] : '';
            $reward_multiplier = isset($param['reward_multiplier']) ? $param['reward_multiplier'] : '';
            if (empty($id)) {
                return json(['code' => 0, 'msg' => '请选择用户']);
            }
            if (empty($reward_multiplier) || $reward_multiplier <= 1) {
                return json(['code' => 0, 'msg' => '请填写倍数大于 1']);
            }
            $user_info = UserModel::where('id', $id)->find();
            if (empty($user_info)) {
                return json(['code' => 0, 'msg' => '用户不存在']);
            }
            $user_info->reward_multiplier = $reward_multiplier;
            if ($user_info->save()) {
                return json(['code' => 1, 'msg' => '设置成功']);
            }
            return json(['code' => 0, 'msg' => '设置失败']);
        }
        // 获取活动列表
        $activitys = ActivitynewModel::where('status', 1)->select();
        View::assign('activitys', $activitys);
        View::assign('info', ['id' => '', 'reward_multiplier' => 1]);
        return View::fetch();
    }

    public function del()
    {
        if (request()->isAjax()) {
            $param = $this->request->param();
            $id = isset($param['id']) ? $param['id'] : '';
            if (empty($id)) {
                return json(['code' => 0, 'msg' => '请选择用户']);
            }
            $user_info = UserModel::where('id', $id)->find();
            if (empty($user_info)) {
                return json(['code' => 0, 'msg' => '用户不存在']);
            }
            $user_info->reward_multiplier = 1;
            if ($user_info->save()) {
                return json(['code' => 1, 'msg' => '删除成功']);
            }
            return json(['code' => 0, 'msg' => '删除失败']);
        }
        return json(['code' => 0, 'msg' => '删除失败']);
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id)
    {
        return [
            '删除' => [
                'auth' => 'ActivitynewMultiplier/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }
}
