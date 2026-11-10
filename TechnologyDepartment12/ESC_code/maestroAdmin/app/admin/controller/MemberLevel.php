<?php

namespace app\admin\controller;


use app\admin\model\MemberModel;
use app\common\model\MemberLevelModel;
use app\common\model\MemberLogModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\facade\View;

class MemberLevel extends Base
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
            if (isset($param['level_type']) && $param['level_type'] != '') {
                $where[] = ['level_type', '=', $param['level_type']];
            }

            if (isset($param['to_level']) && $param['to_level'] != '') {
                $where[] = ['to_level', '=', $param['to_level']];
            }

            if (isset($param['user_id']) && $param['user_id'] != '') {
                $where[] = ['user_id', '=', $param['user_id']];
            }

            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time = strtotime($param['endTime'] . " 23:59:59");
                $whereTime[] = ['member_level_model.create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            $result = MemberLevelModel::hasWhere('user', function ($query) use ($param) {
                if (isset($param['user_name']) && $param['user_name'] != '') {
                    $query->where('user_name', $param['user_name']);
                }
            })->with('user')
                ->where($where)
                ->where($whereTime)
                ->limit($offset, $limit)
                ->order(['id' => 'DESC'])
                ->select()->toArray();

            $total = MemberLevelModel::hasWhere('user', function ($query) use ($param) {
                if (isset($param['user_name']) && $param['user_name'] != '') {
                    $query->where('user_name', $param['user_name']);
                }
            })->where($where)
                ->where($whereTime)
                ->count();

            // 拼装参数
            foreach ($result as $key => $vo) {
                $result[$key]['user_name'] = $vo['user_id']. '/'. $vo['user']['user_name'];
                $result[$key]['level_end_time'] = date('Y-m-d H:i:s', $vo['level_end_time']);
                $result[$key]['level_type'] = MemberLevelModel::$levelTypeMap[$vo['level_type']];
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
}
