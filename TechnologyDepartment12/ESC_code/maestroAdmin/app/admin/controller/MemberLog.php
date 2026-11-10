<?php

namespace app\admin\controller;


use app\admin\model\MemberModel;
use app\common\model\MemberIpModel;
use app\common\model\MemberLogModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\facade\View;

class MemberLog extends Base
{
    /**
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     */
    public function index()
    {
        if (request()->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = $whereTime = [];
            if (isset($param['type']) && $param['type'] != '') {
                $where[] = ['type', '=', $param['type']];
            }

            if (isset($param['ip']) && $param['ip'] != '') {
                $where[] = ['member_log_model.ip', '=', $param['ip']];
            }
            if (isset($param['uuid']) && $param['uuid'] != '') {
                $where[] = ['member_log_model.uuid','=', $param['uuid']];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time = strtotime($param['endTime'] . " 23:59:59");
                $whereTime['member_log_model.create_time'] = ['BETWEEN', [$start_time, $end_time]];
            }
            $result = MemberLogModel::hasWhere('user', function ($query) use ($param) {
                if (isset($param['user_name']) && $param['user_name'] != '') {
                    $query->where('user_name', $param['user_name']);
                }
            })->with('user')
                ->with('user_ip')
                ->where($where)
                ->where($whereTime)
                ->limit($offset, $limit)
                ->order(['id' => 'DESC'])
                ->select()->toArray();

            $total = MemberLogModel::hasWhere('user', function ($query) use ($param) {
                if (isset($param['user_name']) && $param['user_name'] != '') {
                    $query->where('user_name', $param['user_name']);
                }
            })->where($where)
                ->where($whereTime)
                ->count();

            // 拼装参数
            foreach ($result as $key => $vo) {
                $ip_count = count($vo['user_ip']);
                if ($ip_count > 0) {
                    $ip_count -= 1;
                }
                $result[$key]['user_name'] = $vo['user']['user_name'];
                $result[$key]['type'] = MemberLogModel::$typeMap[$vo['type']];
                $result[$key]['area'] =ip2location($vo['ip']);
                $result[$key]['ip_count'] = '<span style="color: #0c80fe;cursor: pointer" class="ip-count" data-ip="'. $vo['ip'] .'" data-user_id="'. $vo['user_id'] .'">'.$ip_count.'</span>';
                unset($vo['user_ip']);
            }

            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }
        $user_id = $this->request->param('user_id');
        $user_name = '';
        if ($user_id) {
            $user_name = MemberModel::where('id', $user_id)->value('user_name');
        }
        View::assign('types', MemberLogModel::$typeMap);
        View::assign('user_name', $user_name);
        return View::fetch();
    }

    public function getSameIpUser()
    {
        $user_id = request()->param('user_id');
        $ip = request()->param('ip');

        $result = MemberIpModel::with('user')
            ->where('ip', $ip)
            ->where('user_id', '<>', $user_id)
            ->select();
        $total = MemberIpModel::where('ip', $ip)
            ->where('user_id', '<>', $user_id)
            ->count();

        // 拼装参数
        foreach ($result as $key => $vo) {
            $result[$key]['user_name'] = $vo['user']['user_name'];
        }

        $data = [
            'code' => '',
            'msg' => '',
            'count' => $total,
            'data' => $result,
        ];

        return json($data);
    }
}
