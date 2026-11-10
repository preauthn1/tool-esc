<?php

namespace app\admin\controller;

use app\common\model\ActivitynewRecordModel AS Model;
use app\common\model\ActivitynewModel;
use app\common\model\UserModel;
use think\db\Where;
use think\facade\View;

class ActivitynewRecord extends Base {

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
                $where[] = ['user_id', '=', $user_id ?? $param['username']];
            }
            if ($param['activity_id'] !== '') {
                $where[] = ['activity_id', '=', $param['activity_id']];
            }
            if (empty($param['startTime']) && empty($param['endTime'])) {
                $param[] = ['startTime', '=', getDateStr()];
                $param[] = ['endTime', '=', getDateStr()];
            }
            if ($param['from_user_id'] !== '') {
                $where[] = ['from_user_id', '=', $param['from_user_id']];
            }
            if ($param['is_xuni'] != -1) {
                $where[] = ['u.is_xuni', '=', $param['is_xuni']];
            }
            if ($param['status'] != -1) {
                $where[] = ['ar.status', '=', $param['status']];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time   = strtotime($param['endTime'] . " 23:59:59");
                $where[] = ['ar.create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            if (!empty($param['startTime']) && empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                 $where[] = ['ar.create_time', '>=', $start_time];
            }
            if (!empty($param['endTime']) && empty($param['startTime'])) {
                $end_time   = strtotime($param['endTime'] . " 23:59:59");
                 $where[] = ['ar.create_time', '<=', $end_time];
            }
            $model = new Model();
            $result = $model->alias('ar')
                ->field('ar.*,u.is_xuni, a.type')
                ->join('home_user u', 'u.id = ar.user_id')
                ->join('activitys a', 'a.id = ar.activity_id')
                ->where($where)
                ->limit($offset, $limit)
                ->order(['ar.id' => 'desc'])
                ->select()->toArray();
            $total = $model->alias('ar')
                ->join('home_user u', 'u.id = ar.user_id')
                ->join('activitys a', 'a.id = ar.activity_id')
                ->where($where)
                ->count();
            $a_title = ActivitynewModel::column('title', 'id');
            $activity_reward_type = config("my.activity_reward_type");
            $activity_new_type = config("my.activity_new_type");
            foreach ($result as $key => &$vo) {
                $vo['activity_id'] = $a_title[$vo['activity_id']];
                $vo['reward_type'] = $activity_reward_type[$vo['reward_type']];
                $vo['type_str'] = $activity_new_type[$vo['type']];
                $vo['reward'] = i18n_sprintf($vo['reward']);
                $vo['befor_num'] = i18n_sprintf($vo['befor_num']);
                $vo['from_user_id'] = $vo['from_user_id'] > 0 ? $vo['from_user_id'] : '-';
                $vo['is_xuni_text'] = $vo['is_xuni'] == 1 ? '<span style="color:red">是</span>' : '否';
                $vo['status'] = $vo['status'] == 1 ? '已领取' : '<span style="color:red">未领取</span>';
            }
            unset($vo);
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            $stat = $model->alias('ar')
                ->join('home_user u', 'u.id = ar.user_id')
                ->where($where)
                ->group('activity_id')
                ->field('activity_id, sum(reward) AS total')
                ->select();
            if ($stat) {
                foreach ($stat as $k => $v) {
                    $stat[$k]['text'] = $a_title[$v['activity_id']];
                    $stat[$k]['total'] = i18n_sprintf($v['total']);
                }
            }
            $return['stat'] = $stat;
            return json($return);
        }
        $activitys = ActivitynewModel::field('id,title')->select();
        View::assign('activitys', $activitys);
        return View::fetch();
    }
}
