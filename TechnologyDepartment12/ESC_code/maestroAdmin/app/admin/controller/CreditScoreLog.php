<?php

namespace app\admin\controller;

use app\admin\model\MemberModel;
use app\common\model\CreditScoreLogModel;
use app\common\model\UserModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\db\Where;
use think\exception\DbException;
use think\facade\View;
use util\Locker;

class CreditScoreLog extends Base
{
    /**
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     */
    public function index()
    {
        if (request()->isAjax() || $this->request->isPost()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $export = $this->request->param("export/d", 0);
            $is_xuni = $param['is_xuni'];
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['type']) && $param['type'] != '') {
                $where[] = ['type', '=', $param['type']];
            }
            if (!empty($param['username'])) {
                $user_id = UserModel::where('mobile|user_name', $param['username'])->value('id');
                $where[] = ['user_id', '=', $user_id ?? $param['username']];
            }
            if (isset($param['user_id']) && $param['user_id'] !== '') {
                $where[] = ['user_id', '=', $param['user_id']];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime']);
                $end_time = strtotime($param['endTime']);
                $where[] = ['credit_score_log_model.create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            $query = (new CreditScoreLogModel());
            $total = 0;
            if (in_array($is_xuni, [0, 1])) {
                $query = $query->hasWhere('user', ['is_xuni' => $is_xuni]);
            } else {
                $query = $query->hasWhere('user');
            }
            if (!$export) {
                $total = $query->with(['user', 'admin'])
                    ->where($where)->count();
                $result = $query->with(['user', 'admin'])
                    ->where($where)
                    ->limit($offset, $limit)
                    ->order(['id' => 'DESC'])
                    ->select();
            } else {
                $result = $query->with(['user', 'admin'])
                    ->where($where)
                    ->order(['id' => 'DESC'])
                    ->select();
            }

            foreach ($result as $key => $vo) {
                $result[$key]['user_name'] = $vo['user']['user_name'] ?? '';
                $result[$key]['admin_name'] = $vo['admin']['user_name'] ?? '';
                $result[$key]['type_str'] = CreditScoreLogModel::$typeMap[$vo['type']] ?? $vo['type'];
                $result[$key]['is_xuni'] = $vo['user']['is_xuni'];
                $result[$key]['is_xuni_text'] = $vo['user']['is_xuni'] ? '是' : '否';

                $top_leader = UserModel::getTopLeader($vo['user']['id']);
                $result[$key]['top_leader_remark'] = '';
                if ($top_leader) {
                    $result[$key]['top_leader_remark'] = $top_leader['remark'];
                }

                // 所在层级
                preg_match_all("/\d+/", $vo['user']['tree_id'], $matches);
                $count = count($matches[0]);
                $result[$key]['tree_level'] = $count;
            }

            if ($export) {
                if (empty($result)) {
                    $this->error(lang("无数据可导出"));
                }
                $header = [
                    "id"                => "ID",
                    "user_id"           => "用户id",
                    "user_name"         => '用户名',
                    'type_str'          => '类型',
                    'top_leader_remark' => '所属代理',
                    'before'            => '操作前信用分',
                    'score'             => '操作信用分',
                    'after'             => '操作后信用分',
                    'title'             => '标题',
                    'remarks'           => '备注',
                    'admin_name'        => '管理员',
                    'tree_level'        => '所在层级',
                    'is_xuni_text'      => '虚拟账号',
                    'create_time'       => '添加时间',

                ];
                return genXlsx($header, $result, "信用分记录");
            }

            $return['total'] = $total;
            $return['rows'] = $result;
            return json($return);
        }
        $user_id = $this->request->param('user_id');
        $user_name = '';
        if ($user_id) {
            $user_name = MemberModel::where('id', $user_id)->value('user_name');
        }
        View::assign('types', CreditScoreLogModel::$typeMap);
        View::assign('user_name', $user_name);
        return View::fetch();
    }

    public function creditScoreAdd()
    {
        if (request()->isPost()) {
            $param = $this->request->param();
            (new Locker('credit_score_add_lock'.$param['uid']))->lock();

            if (!filter_var($param['score'], FILTER_VALIDATE_INT) || $param['score'] == 0) {
                $this->error(lang('请输入正确的信用分'));
            }
            if (!isset($param['type']) || !in_array($param['type'], array_keys(CreditScoreLogModel::$typeMap))) {
                $this->error(lang('请选择操作类型'));
            }
            $user = MemberModel::where('id', $param['uid'])->find();
            if (!$user) {
                $this->error(lang('请输入正确的用户id'));
            }
            $res = CreditScoreLogModel::changeCreditScore($param['uid'], $param['score'], $param['type'], 0, 0,
                0,session('id'));
            if ($res['code']) {
                $this->success(lang('操作成功'), url('credit_score_log/index', ['uid' => $param['uid']])->build());
            } else {
                $this->error(lang($res['msg']));
            }
        }
    }
}
