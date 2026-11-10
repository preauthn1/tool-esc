<?php

namespace app\admin\controller;

use app\common\model\ActivityRecordModel AS Model;
use app\common\model\ActivityModel;
use app\common\model\UserModel;
class ActivityRecord extends Base {

    public function index() {
        if (request()->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (!empty($param['username'])) {
                $user_id = UserModel::where('mobile', $param['username'])->value('id');
                $where[] = ['user_id', '', $user_id ?? $param['username']]
                ;
            }
            if ($param['activity_id'] !== '') {
                $where[] = ['activity_id', '', $param['activity_id']];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time   = strtotime($param['endTime'] . " 23:59:59");
                $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            if (!empty($param['startTime']) && empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                 $where[] = ['create_time', '>=', $start_time];
            }
            if (!empty($param['endTime']) && empty($param['startTime'])) {
                $end_time   = strtotime($param['endTime'] . " 23:59:59");
                 $where[] = ['create_time', '<=', $end_time];
            }
            $model = new Model();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            $a_title = ActivityModel::column('title', 'id');
            $status = [
                '0' => '待审核',
                '1' => '活动中',
                '2' => '已完成',
            ];
            foreach ($result as $key => $vo) {
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
                $result[$key]['status'] = getStatusText($vo['status'], $status[$vo['status']]);
                $result[$key]['activity_id'] = $a_title[$vo['activity_id']];
            }
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }
        $activitys = ActivityModel::field('id,title')->select();
        View::assign('activitys', $activitys);
        return View::fetch();
    }

    public function add() {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.');
            $model = new Model;
            if ($id > 0) {
                insert_operation_log('活动记录修改', $param);
                $result = $model->where('id', $id)->update($param);
            } else {
                insert_operation_log('活动记录添加', $param);
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            $url = url('index')->build();
            $this->success(lang('提交成功'), $url);
        }
        $info = Model::where('id', $id)->find();
        if (empty($info)) {
            $model = new Model();
            $info = array_fill_keys($model->getTableFields(), '');
            $info['status'] = 1;
        }
        $activitys = ActivityModel::field('id,title')->select();
        View::assign('activitys', $activitys);
        View::assign("info", $info);
        return View::fetch();
    }

    // 删除用户
    public function del() {
        $id = input('param.id');
        $info = Model::find($id);
        if ($info) {
            insert_operation_log('活动记录删除', $info);
            $info->delete();
        }
        return json(msg(1, url('index')->build(), lang('删除成功')));
    }
    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id) {
        return [
            '编辑' => [
                'auth' => 'activityRecord/add',
                'href' => url('add', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'activityRecord/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }
}
