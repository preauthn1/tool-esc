<?php

namespace app\admin\controller;

use app\admin\model\MemberModel;
use app\admin\model\UserModel as AdminModel;
use app\admin\transform\MoneyLogTransform;
use app\common\model\MoneyLogModel;
use app\common\model\UserModel;
use app\common\repository\MoneyLogRepository;
use think\facade\Db;
use think\db\Where;
use think\facade\View;
use think\response\Json;

class MoneyLog extends Base
{

    public function index()
    {
        $money_type = MoneyLogModel::$TYPE_NAMES;
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
            $field = "id,is_xuni,user_id,admin_id,order_id,from_user_id,change,title,remarks,money,user_money,last_money,type,detail_type,status,create_time";
            if (!$export) {
                $result = MoneyLogModel::with(['user', 'from_user'])
                    ->field($field)
                    ->where($where)
                    ->limit($offset, $limit)
                    ->order(['create_time' => 'desc', 'id' => 'desc'])
                    ->select();
            } else {
                $result = MoneyLogModel::with(['user', 'from_user'])
                    ->field($field)
                    ->where($where)
                    ->order(['create_time' => 'desc', 'id' => 'desc'])
                    ->select();
            }

            $total = Db::table("qd_money_log")->where($where)->count();
            foreach ($result as $key => &$vo) {
                $vo['type_str'] = $money_type[$vo['type']];
                $vo['detail_type_str'] = $detail_type[$vo['detail_type']];
                if ($vo['admin_id'] > 0) {
                    $vo['admin_name'] = AdminModel::where('id', $vo['admin_id'])->value('user_name');
                } else {
                    $vo['admin_name'] = '-';
                }
                $vo['user_info'] = $vo['user']['user_name'];
                $vo['from_user_info'] = isset($vo['from_user']) ? $vo['from_user']['user_name'] : '';
//                $vo['create_time'] = date('Y-m-d H:i:s', $vo['create_time']);
                $vo['change_str'] = $vo['change'] == 1 ? '收入' : '支出';
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
                    'change_str' => '收支类型',
                    'remarks' => '备注',
                    'admin_name' => '管理员',
                    'from_user_info' => '下级用户',
                    'create_time' => '添加时间',

                ];
                return genXlsx($header, $result, "会员流水");
            }

            $return['total'] = $total;  //总数据
            $return['rows'] = $result;

            $stat = MoneyLogModel::where($where)->group('type')->field('type, sum(money) AS total')->select();
            if ($stat) {
                $stat = $stat->toArray();
                foreach ($stat as $k => $v) {
                    $stat[$k]['text'] = $money_type[$v['type']];
                    $stat[$k]['total'] = sprintf4f($v['total']);
                }
            }
            $return['stat'] = $stat;
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
        View::assign('money_type', $money_type);
        View::assign('detail_type', $detail_type);
        return View::fetch();
    }


    // 会员充值提现流水
    public function recharge_withdrawal()
    {
        if (request()->isAjax() || request()->isPost()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $page = $param['pageNumber'];
            $is_xuni = $param['is_xuni'];
            $where = [];
            $where[] = ['type', '=', MoneyLogModel::TYPE_USER];
            if (!empty($param['username'])) {
                $where[] = ['user_name', '=', $param['username']];
            }
            if (!empty($param['user_id'])) {
                $where[] = ['user_id', '=', $param['user_id']];
            }
            if (!empty($param['type'])) {
                $where[] = ['type', '=', $param['type']];
            }
            if (!empty($param['change'])) {
                $where[] = ['change', '=', $param['change']];
            }
            if (!empty($param['detail_type'])) {
                $where[] = ['detail_type', '=', $param['detail_type']];
            } else {
                $where[] = ['detail_type', 'in', [1, 2]];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time = strtotime($param['endTime'] . " 23:59:59");
                $where[] = ['money_log_model.create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            if (!empty($param['startTime']) && empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $where[] = ['money_log_model.create_time', '>=', $start_time];
            }
            if (!empty($param['endTime']) && empty($param['startTime'])) {
                $end_time = strtotime($param['endTime'] . " 23:59:59");
                $where[] = ['money_log_model.create_time', '<=', $end_time];
            }
            if (!empty($param['leader1'])) {
                $where[] = ['tree_id', 'like', '%/' . $param['leader1'] . '/%'];
                $where[] = ['tree_id', 'not like', '%/' . $param['leader1'] . '/'];
            }
            $export = $this->request->param("export/d", 0);
            if ($export) {
                $header = [
                    "id" => "ID",
                    "user_info" => '用户信息',
                    'title' => '账单标题',
                    'type_str' => '明细种类',
                    'money' => '操作金额',
                    'last_money' => '用户余额',
                    'create_time' => '添加时间',
                    'top_leader_remark' => '所属代理',
                    "is_xuni_text" => "是否虚拟账号",
                ];
                $query = (new MoneyLogRepository)->model();
                if (in_array($is_xuni, [0, 1])) {
                    $query = $query->hasWhere('user', ['is_xuni' => $is_xuni]);
                } else {
                    $query = $query->hasWhere('user');
                }
                $result = $query->with(['user'])
                    ->where($where)
                    ->select();
                $data = (new MoneyLogTransform)->transformCollection($result);

                return genXlsx($header, $data, "会员账单明细");
            }
            $query = (new MoneyLogRepository)->model();
            if (in_array($is_xuni, [0, 1])) {
                $query = $query->hasWhere('user', ['is_xuni' => $is_xuni]);
            } else {
                $query = $query->hasWhere('user');
            }
            $result = $query->with(['user'])
                ->where($where)
                ->paginate(['page' => $page, 'list_rows' => $limit]);
            $data = (new MoneyLogTransform)->transformPages($result->toArray());


            $query = (new MoneyLogRepository)->model();
            if (in_array($is_xuni, [0, 1])) {
                $query = $query->hasWhere('user', ['is_xuni' => $is_xuni]);
            } else {
                $query = $query->hasWhere('user');
            }
            $stat = $query->where($where)->group('detail_type')->field('detail_type, sum(money) AS total')->select();
            if ($stat) {
                foreach ($stat as $k => $v) {
                    $stat[$k]['text'] = $v['detail_type'] == 1 ? '充值' : '提现';
                    $stat[$k]['total'] = sprintf4f($v['total']);
                }
            }
            $data['stat'] = $stat;
            return json($data);
        }
        $user_name = '';
        View::assign('user_name', $user_name);
//        View::assign('start_time', $start_time);
//        View::assign('user_name', $user_name);
        $agent = MemberModel::where('is_proxy', 1)->select();
        View::assign('agent', $agent);
        return View::fetch();
    }

    /**
     * 切换流水状态
     */
    public function changeStatus(): Json
    {
        $id = input('param.id/d');
        $moneyLogModel = new MoneyLogModel();

        $result = $moneyLogModel->toggleStatus($id);
        if ($result !== false) {
            insert_operation_log('会员流水:修改状态', ['id' => $id]);
            return json(msg(1, [], lang('修改成功')));
        }
        return json(msg(-1, [], lang('修改失败')));
    }
}
