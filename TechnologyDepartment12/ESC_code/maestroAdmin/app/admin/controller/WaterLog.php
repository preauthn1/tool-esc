<?php

namespace app\admin\controller;

use app\admin\model\MemberModel;
use app\admin\model\UserModel as AdminModel;
use app\admin\transform\MoneyLogTransform;
use app\common\model\MemberWaterModel;
use app\common\model\MoneyLogModel;
use app\common\model\UserModel;
use app\common\repository\MemberWaterRepository;
use app\common\repository\MoneyLogRepository;
use think\facade\Cache;
use think\facade\View;

class WaterLog extends Base
{

    public function index()
    {
        $detail_type = MoneyLogModel::$DETAIL_TYPE_NAMES;
        if ($this->request->isAjax() || $this->request->isPost()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $export = $this->request->param("export/d", 0);
            if ($export) {
                if ($param['startTime'] != $param['endTime'] && empty($param['username']) && empty($param['user_id'])) {
                    $this->error(lang("仅支持导出一天的数据"));
                }
            }
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (!empty($param['username'])) {
                $user_id = UserModel::where('mobile|user_name', $param['username'])->value('id');
                $where[] = ['user_id', '=', $user_id ?? $param['username']];
            }
            if (isset($param['user_id']) && $param['user_id'] !== '') {
                $where[] = ['user_id', '=', $param['user_id']];
            }
            if ($param['type'] !== '') {
                $where[] = ['type', '=', $param['type']];
            }
            if ($param['detail_type'] !== '') {
                $where[] = ['detail_type', '=', $param['detail_type']];
            }
            if (!empty($param['change'])) {
                $where[] = ['change', '=', $param['change']];
            }
            if (empty($param['startTime'])) {
                $param[] = ['startTime', '=', getDateStr()];
            }
            if (empty($param['endTime'])) {
                $param[] = ['endTime', '=', getDateStr()];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time = strtotime($param['endTime'] . " 23:59:59");
                $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            $field = "id,is_xuni,user_id,admin_id,order_id,change,title,remarks,amount,rate,water,user_water,last_water,type,detail_type,create_time";
            if (!$export) {
                $result = MemberWaterModel::with(['user'])
                    ->field($field)
                    ->where($where)
                    ->limit($offset, $limit)
                    ->order(['create_time' => 'desc', 'id' => 'desc'])
                    ->select();
            } else {
                $result = MemberWaterModel::with(['user'])
                    ->field($field)
                    ->where($where)
                    ->order(['create_time' => 'desc', 'id' => 'desc'])
                    ->select();
            }

            $total = MemberWaterModel::where($where)->count();
            foreach ($result as $key => &$vo) {
                $vo['detail_type_str'] = $detail_type[$vo['detail_type']];
                if ($vo['admin_id'] > 0) {
                    $vo['admin_name'] = AdminModel::where('id', $vo['admin_id'])->value('user_name');
                } else {
                    $vo['admin_name'] = '-';
                }
                $vo['user_info'] = $vo['user']['user_name'];
                $vo['from_user_info'] = isset($vo['from_user']) ? $vo['from_user']['user_name'] : '';
//                $vo['create_time'] = date('Y-m-d H:i:s', $vo['create_time']);
            }

            if ($export) {
                if (empty($result)) {
                    $this->error(lang("无数据可导出"));
                }
                $header = [
                    "id" => "ID",
                    "user_id" => "用户id",
                    "user_info" => '用户信息',
                    'title' => '账单标题',
                    'type_str' => '钱包类型',
                    'detail_type_str' => '明细类型',
                    'user_money' => '操作前金额',
                    'money' => '操作金额',
                    'last_money' => '操作后金额',
                    'remarks' => '备注',
                    'admin_name' => '管理员',
                    'from_user_info' => '下级用户',
                    'create_time' => '添加时间',

                ];
                return genXlsx($header, $result, "会员流水");
            }

            $return['total'] = $total;  //总数据
            $return['rows'] = $result;

//            $stat = MemberWaterModel::where($where)->group('type')->field('type, sum(water) AS total')->select();
//            if ($stat) {
//                $stat = $stat->toArray();
//                foreach ($stat as $k => $v) {
//                    $stat[$k]['total'] = sprintf4f($v['total']);
//                }
//            }
//            $return['stat'] = $stat;
            return json($return);
        }
        $user_id = $this->request->param('user_id');
        $user_name = '';
        $start_time = '';
        if ($user_id) {
            $user = MemberModel::where('id', $user_id)->find();
            $user_name = $user->user_name;
            $start_time = date('Y-m-d', strtotime($user->create_time));
        }
        View::assign('start_time', $start_time);
        View::assign('user_name', $user_name);
        View::assign('detail_type', $detail_type);
        return View::fetch();
    }


    // 调整余额
    public function add()
    {
        if (request()->isPost()) {
            $param = $this->request->param();
            unset($param['code']);
            if ($param['water'] == 0) {
                $this->error(lang('金额有误'));
            }

            if (!in_array($param['usertype'], [1])) {
                $this->error(lang('请选择操作类型'));
            }
            $redis = Cache::store('redis');
            $redis_key = "water_add:{$param['user_id']}";
            if ($redis->has($redis_key)) {
                $this->error(lang('请勿重复操作'));
            }
            $user = MemberModel::where('id', $param['user_id'])->find();
            $redis->set($redis_key, 1, 3);
            $userid = $user->id;
            $data = [
                'code' => 11,
                'msg' => '操作失败'
            ];

            switch ($param['usertype']) {
                case 1:
                    if ($param['water'] > 0) {
                        $data = (new MemberWaterRepository())->addWater($userid, 0, 0,$param['water'],session('id', ''));
                    } else {
                        $data = (new MemberWaterRepository())->subWater($userid, 0, 0,$param['water'],session('id', ''), false);
                    }
                    break;
            }
            if ($data['code'] == 1) {
                $this->success(lang('操作成功'), url('member/water', ['uid' => $param['user_id']]));
            } else {
                $this->error(lang($data['msg']));
            }
        }
    }
}
