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

use app\admin\model\CollectionModel;
use app\admin\model\LevelModel;
use app\common\model\ActivitynewRecordModel;
use app\common\model\ConfigModel;
use app\common\model\FinanceProductOrderModel;
use app\common\model\InvestmentProductOrderModel;
use app\common\model\MoneyLogModel;
use app\common\model\PaymentPlatformModel;
use app\common\model\PddTurntableRecordModel;
use app\common\model\SignRecordModel;
use app\common\model\TaskModel;
use app\common\model\UserModel;
use app\common\repository\InvestmentProductOrderRepository;
use app\common\repository\RechargeRepository;
use app\lib\exception\LockException;
use think\facade\Db;
use think\db\Where;
use think\facade\Cache;
use app\admin\model\MemberModel;
use app\admin\model\RechargeModel;
use app\admin\model\WithdrawModel;
use app\admin\model\MoneyModel;
use app\common\model\OrderModel;
use app\admin\model\DayReportModel;
use app\common\repository\FinanceProductOrderRepository;
use Carbon\Carbon;
use think\facade\View;
use util\Locker;

class Stat extends Base
{

    public function index()
    {

        if (request()->isAjax()) {
            $param = input('param.');
            $start = $param['start'];
            $end = $param['end'];
            $mobile = $param['mobile'];
            $where = null;
            $where2 = null;
            $where3 = null;
            $userid = [];
            if ($mobile) {
                $userids = getTeamUseridByMobile($mobile);
                $userid = getUseridByMobile($mobile);
                if ($userid > 0) {
                    $sub = Db::name('home_user')->where('is_xuni', 0)->whereLike("tree_id", "%/{$userid}/%")->field("id")->buildSql();
                    $sub2 = Db::name('home_user')->where('is_xuni', 0)->where("leader1", $userid)->field("id")->buildSql();
                    $where = "user_id IN({$sub})";
                    $where2 = "id IN({$sub})";
                    $where3 = "user_id IN({$sub2})";
                }
            }
            //充值金额统计
            $recharge = Db::name('recharge')
                ->alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)
                ->where("r.create_time", ">=", strtotime($start . " 00:00:00"))
                ->where("r.create_time", "<=", strtotime($end . " 23:59:59"))
                ->where($where)
                ->field("r.status, sum(r.money) AS total")->group('r.status')
                ->column("SUM(r.money)", "r.status");
            //提现金额统计
            $withdrawals = Db::name('withdrawals')
                ->alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)
                ->where("r.create_time", ">=", strtotime($start . " 00:00:00"))
                ->where("r.create_time", "<=", strtotime($end . " 23:59:59"))
                ->where($where)
                ->field("r.status, sum(r.original_money) AS total")->group('r.status')
                ->column("SUM(r.original_money)", "r.status");
            //注册人数
            $reg_count = Db::name('home_user')
                ->where('is_xuni', 0)
                ->where("create_time", ">=", strtotime($start . " 00:00:00"))
                ->where("create_time", "<=", strtotime($end . " 23:59:59"))
                ->where($where2)
                ->count();
            //充值人数
            $recharge_count = Db::name('recharge')
                ->alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)
                ->where("r.create_time", ">=", strtotime($start . " 00:00:00"))
                ->where("r.create_time", "<=", strtotime($end . " 23:59:59"))
                ->where($where)
                ->where('r.status', 2)
                ->count('distinct user_id');
            //提现人数
            $withdraw_count = Db::name('withdrawals')
                ->alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)
                ->where("r.create_time", ">=", strtotime($start . " 00:00:00"))
                ->where("r.create_time", "<=", strtotime($end . " 23:59:59"))
                ->where($where)
                ->where('r.status', 2)
                ->count('distinct user_id');

            $zs_recharge = 0;
            $zs_withdrawals = 0;
            $zs_recharge_count = 0;
            $zs_withdraw_count = 0;
            //查询直属
            if (!empty($userid)) {
                //充值金额统计
                $zs_recharge = Db::name('recharge')
                    ->alias('r')->join('home_user u', 'u.id=r.user_id')
                    ->where('u.is_xuni', 0)
                    ->where("r.create_time", ">=", strtotime($start . " 00:00:00"))
                    ->where("r.create_time", "<=", strtotime($end . " 23:59:59"))
                    ->where($where3)
                    ->field("r.status, sum(r.money) AS total")->group('r.status')
                    ->column("SUM(r.money)", "r.status");
                //提现金额统计
                $zs_withdrawals = Db::name('withdrawals')
                    ->alias('r')->join('home_user u', 'u.id=r.user_id')
                    ->where('u.is_xuni', 0)
                    ->where("r.create_time", ">=", strtotime($start . " 00:00:00"))
                    ->where("r.create_time", "<=", strtotime($end . " 23:59:59"))
                    ->where($where3)
                    ->field("r.status, sum(r.original_money) AS total")->group('r.status')
                    ->column("SUM(r.original_money)", "r.status");
                //充值人数
                $zs_recharge_count = Db::name('recharge')
                    ->alias('r')->join('home_user u', 'u.id=r.user_id')
                    ->where('u.is_xuni', 0)
                    ->where("r.create_time", ">=", strtotime($start . " 00:00:00"))
                    ->where("r.create_time", "<=", strtotime($end . " 23:59:59"))
                    ->where($where3)
                    ->where('r.status', 2)
                    ->count('distinct user_id');
                //提现人数
                $zs_withdraw_count = Db::name('withdrawals')
                    ->alias('r')->join('home_user u', 'u.id=r.user_id')
                    ->where('u.is_xuni', 0)
                    ->where("r.create_time", ">=", strtotime($start . " 00:00:00"))
                    ->where("r.create_time", "<=", strtotime($end . " 23:59:59"))
                    ->where($where3)
                    ->where('r.status', 2)
                    ->count('distinct user_id');
            }
            return $this->success("ok", "", [
                'recharge' => $recharge,
                'withdraw' => $withdrawals,
                'withdraw_count' => intval($withdraw_count),
                'recharge_count' => intval($recharge_count),
                'reg_count' => intval($reg_count),
                'zs' => [
                    'recharge' => $zs_recharge,
                    'withdraw' => $zs_withdrawals,
                    'withdraw_count' => intval($zs_withdraw_count),
                    'recharge_count' => intval($zs_recharge_count),
                ]
            ]);
        }

        return View::fetch();
    }

    public function iprank()
    {
        if ($this->request->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $field = $param['ip_type'] == 0 ? 'ip' : 'last_login_ip';
            $where = [];
            if (isset($param['startTime']) && $param['startTime'] && isset($param['endTime']) && $param['endTime']) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time = strtotime($param['endTime'] . " 23:59:59");
                $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
            }

            $list = MemberModel::where($where)->field("{$field}, COUNT(id) AS total")
                ->whereNotIn($field, ['', '0.0.0.0'])
                ->group($field)->order(['total' => 'DESC'])->limit($offset, $limit)->select();
            $total = MemberModel::where($where)->whereNotIn($field, ['', '0.0.0.0'])->count($field);

            foreach ($list as &$value) {
                $value['ip_address'] = $value[$field];
                $value['ip_local'] = ip2location($value[$field]);
            }
            unset($value);
            $return = [];
            $return['total'] = $total;  //总数据
            $return['rows'] = $list;
            return $return;
        }
        return View::fetch();
    }

    public function recharge()
    {
        if ($this->request->isAjax()) {
            $param = $this->request->param();
            $where = [];
            if (!isset($param['startTime']) || !isset($param['endTime'])) {
                $param['startTime'] = getDateStr();
                $param['endTime'] = getDateStr();
            }
            $start_time = strtotime($param['startTime'] . " 00:00:00");
            $end_time = strtotime($param['endTime'] . " 23:59:59");
            $where[] = ['r.create_time', 'BETWEEN', [$start_time, $end_time]];

            $model = new \app\admin\model\PaymentModel();
            $list = $model->order('sort ASC, type ASC')->select();
            $list[] = [
                'id' => 0,
                'name' => '手动上分'
            ];
            $order_success_counts = Db::name('recharge')
                ->alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where("r.status", 2)->group("r.pay_id")->where($where)->column("count(r.id)", "r.pay_id");
            $order_total_counts = Db::name('recharge')
                ->alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->group("r.pay_id")->where($where)->column("count(r.id)", "r.pay_id");

            $PayAmounts = Db::name('recharge')
                ->alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->group("r.pay_id")->where("r.status", 2)->where($where)->column("sum(r.money)", "pay_id");
            $zPayAmount = $zOrderSuccess = $zOrderTotal = 0;
            foreach ($list as &$value) {
                $value['PayAmount'] = (isset($PayAmounts[$value['id']]) ? $PayAmounts[$value['id']] : 0);
                $zPayAmount += $value['PayAmount'];
                //付款成功订单笔数
                $value['OrderSuccess'] = $order_success_count = isset($order_success_counts[$value['id']]) ? $order_success_counts[$value['id']] : 0;
                $zOrderSuccess += $value['OrderSuccess'];
                //订单总笔数
                $value['OrderTotal'] = $order_total_count = isset($order_total_counts[$value['id']]) ? $order_total_counts[$value['id']] : 0;
                $zOrderTotal += $value['OrderTotal'];

                $value['PayRatio'] = $order_total_count > 0 ? (sprintf("%.2f", $order_success_count / $order_total_count) * 100) . "%" : "-";
                if ($param['startTime'] == $param['endTime']) {
                    $value['Date'] = "{$param['startTime']}";
                } else {
                    $value['Date'] = "{$param['startTime']} 至 {$param['endTime']}";
                }
            }
            unset($value);
            $zdata = [];
            $zdata['name'] = "总计";
            $zdata['PayAmount'] = $zPayAmount . "元";
            $zdata['OrderSuccess'] = $zOrderSuccess;
            $zdata['OrderTotal'] = $zOrderTotal;
            $zdata['PayRatio'] = $zOrderTotal > 0 ? (sprintf("%.2f", $zOrderSuccess / $zOrderTotal) * 100) . "%" : "-";
            $zdata['Date'] = "-";
            $list[] = $zdata;
            $return = [];
            $return['total'] = count($list);  //总数据
            $return['rows'] = $list;
            return $return;
        }
        return View::fetch();
    }

    public function rechargedays()
    {
        if ($this->request->isAjax()) {
            $param = $this->request->param();
            $where = [];
            if (!isset($param['startTime']) || !isset($param['endTime'])) {
                $param['startTime'] = getDateStr(strtotime("-7 days"));
                $param['endTime'] = getDateStr();
            }
            $start_time = strtotime($param['startTime'] . " 00:00:00");
            $end_time = strtotime($param['endTime'] . " 23:59:59");
            $map = [];
            if ($param['pay_id']) {
                $map['pay_id'] = $param['pay_id'];
            }
            $list = [];
            for ($i = $start_time; $i <= $end_time; $i += 86400) {
                $where = [
                    ['r.create_time', "between time", [getDateStr($i) . " 00:00:00", getDateStr($i) . " 23:59:59"]]
                ];
                $row['PayAmount'] = Db::name('recharge')
                    ->alias('r')->join('home_user u', 'u.id=r.user_id')
                    ->where('u.is_xuni', 0)->where("r.status", "2")->where($map)->where($where)->sum("r.money");
                //付款成功订单笔数
                $row['OrderSuccess'] = $order_success_count = Db::name('recharge')
                    ->alias('r')->join('home_user u', 'u.id=r.user_id')
                    ->where('u.is_xuni', 0)->where($map)->where("r.status", "2")->where($where)->count();
                //订单总笔数
                $row['OrderTotal'] = $order_total_count = Db::name('recharge')
                    ->alias('r')->join('home_user u', 'u.id=r.user_id')
                    ->where('u.is_xuni', 0)->where($map)->where($where)->count();
                $row['PayRatio'] = $order_total_count > 0 ? (sprintf("%.2f", $order_success_count / $order_total_count) * 100) . "%" : "-";
                $row['Date'] = getDateStr($i);
                $list[] = $row;
            }
            $return = [];
            $return['total'] = count($list);  //总数据
            $return['rows'] = $list;
            return $return;
        }
        $model = new \app\admin\model\PaymentModel();
        $pay_list = $model->order('sort ASC, type ASC')->select();
        View::assign("pay_list", $pay_list);
        return View::fetch();
    }

    public function reg()
    {
        return View::fetch();
    }

    public function reg_report()
    {
        $startTime = $this->request->param('startTime/s');
        $endTime = $this->request->param('endTime/s');
        if (empty($startTime)) {
            $startTime = getDateStr(strtotime("-7 days"));
        }
        if (empty($endTime) || strtotime($endTime) >= strtotime(getDateStr(strtotime("+1 days")))) {
            $endTime = getDateStr();
        }
        $st = strtotime($startTime . " 00:00:00");
        $et = strtotime($endTime . " 23:59:59");
        $list = [];
        $redis = Cache::store('redis');
        $yesterday_time = strtotime(timeToStr(strtotime('-1 days'), "Y-m-d 23:59:59"));
        for ($i = $st; $i <= $et; $i += 86400) {
            $date = getDateStr($i);
            $redis_key = "reg_report:{$date}";
            if ($redis->has($redis_key)) {
                $row = $redis->get($redis_key);
            } else {
                $where = [$date . " 00:00:00", $date . " 23:59:59"];
                $row = [];
                $row['date'] = $date;
                $row['reg'] = $this->getTotalReg($where);
                $row['recharge'] = $this->getTotalRechargeUsers($where);
                $row['no_recharge'] = floatval($row['reg']) - floatval($row['recharge']);
                if ($i <= $yesterday_time) { //缓存今天之前的数据
                    $redis->set($redis_key, $row, 7 * 86400);
                }
            }
            $list[] = $row;
        }
        View::assign('startTime', $startTime);
        View::assign('endTime', $endTime);
        View::assign('list', $list);
        return View::fetch();
    }

    public function reg_report_day()
    {
        if ($this->request->isAjax() || $this->request->isPost()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $date = $param['date'];
            if (empty($date)) {
                $date = getDateStr();
            }
            $where = [$date . " 00:00:00", $date . " 23:59:59"];

            $export = $this->request->param("export/d", 0);

            $recharge_where = [];
            if (isset($param['recharge_type']) && $param['recharge_type'] != '') {
                switch ($param['recharge_type']) {
                    case 1:
                        $recharge_where[] = ['remark', '=', '手动补单'];
                        break;
                    case 2:
                    case 3:
                        $recharge_where[] = ['type', '=', $param['recharge_type']];
                        $recharge_where[] = ['remark', '<>', '手动补单'];
                        break;
                }

            }

            $recharge = RechargeModel::whereTime('create_time', 'between', $where)
                ->where('status', RechargeModel::STATUS_SUCCESS)
                ->where($recharge_where)
                ->group('user_id')
                ->field('user_id, sum(money) as recharge')
                ->buildSql();
            $is_recharge = '';
            if (isset($param['is_recharge']) && $param['is_recharge'] != '') {
                $is_recharge = $param['is_recharge'];
            }
            if ($export) {
                $query = Db::name('home_user')
                    ->alias('hu')
                    ->whereTime('create_time', 'between', $where)
                    ->where('is_xuni', 0)
                    ->join([$recharge => 'r'], 'r.user_id=hu.id', 'left')
                    ->field("id, true_name,user_name,create_time,recharge,level_id,tree_id,leader1")
                    ->order(['id' => 'DESC']);
                if ($is_recharge !== '') {
                    if ($is_recharge == 1) {
                        $query = $query->having('recharge > 0');
                    } else {
                        $query = $query->having('recharge is null');
                    }
                }
                $result = $query->select();
            } else {
                $query = Db::name('home_user')
                    ->alias('hu')
                    ->whereTime('create_time', 'between', $where)
                    ->where('is_xuni', 0)
                    ->join([$recharge => 'r'], 'r.user_id=hu.id', 'left')
                    ->field("id, true_name,user_name,create_time,recharge,level_id,tree_id,leader1")
                    ->limit($offset, $limit)
                    ->order(['id' => 'DESC']);
                if ($is_recharge !== '') {
                    if ($is_recharge == 1) {
                        $query = $query->having('recharge > 0');
                    } else {
                        $query = $query->having('recharge is null');
                    }
                }
                $result = $query->select();

                $query = Db::name('home_user')
                    ->alias('hu')
                    ->whereTime('create_time', 'between', $where)
                    ->where('is_xuni', 0)
                    ->join([$recharge => 'r'], 'r.user_id=hu.id', 'left')
                    ->field("id, true_name,user_name,create_time,recharge,level_id,tree_id,leader1");
                if ($is_recharge !== '') {
                    if ($is_recharge == 1) {
                        $query = $query->having('recharge > 0');
                    } else {
                        $query = $query->having('recharge is null');
                    }
                }
                $total = count($query->select());
            }

            if ($result) {
                $result = $result->toArray();
                $level_name = LevelModel::column("level_name", 'level_id');
                foreach ($result as $key => $vo) {
                    $result[$key]['create_time'] = timeToStr($vo['create_time']);
                    $top_leader = UserModel::getTopLeader($vo['id']);
                    $result[$key]['top_leader_remark'] = '';
                    if ($top_leader) {
                        $result[$key]['top_leader_remark'] = $top_leader['remark'];
                    }
                    $tree = explode('/', $vo['tree_id']);
                    $result[$key]['second_leader_id'] = 0;
                    if (isset($tree[2])) {
                        $result[$key]['second_leader_id'] = $tree[2];
                    }

                    preg_match_all("/\d+/", $vo['tree_id'], $matches);
                    $count = count($matches[0]);
                    $result[$key]['tree_level'] = $count;
                }
            }
            if ($export) {
                $header = [
                    "id" => "用户ID",
                    "true_name" => "姓名",
                    "leader1" => "上级ID",
                    "second_leader_id" => "初级ID",
                    "user_name" => "账户",
                    "top_leader_remark" => "顶级代理",
                    "create_time" => "注册时间",
                    "recharge" => "充值金额",
                    "tree_level" => "等级",
                ];
                return genXlsx($header, $result, $date . "_注册用户报表");
            }
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }
        $date = $this->request->param('date/s');
        if (empty($date)) {
            $date = getDateStr();
        }
        View::assign('date', $date);
        return View::fetch();
    }

    //财务日报
    public function day_report()
    {
        try {
            (new Locker('day_report' . session('id')))->lock(1);
        } catch (LockException $e) {
            return $this->error('操作过于频繁', '', '', 3);
        }
//        $act = $this->request->param('act/s');
//        if ($act == 'query') {
//            $date = $this->request->param('date/s');
//            $key = $this->request->param('key/s');
//            $model = new DayReportModel;
//            $result = $model->stat($date, true, $key);
//            $this->success('ok', '', $result);
//        }
        $update = $this->request->param('update/s');
        $startTime = $this->request->param('startTime/s');
        $endTime = $this->request->param('endTime/s');
        $export = $this->request->param('export/s');
        if (empty($startTime)) {
            $startTime = getDateStr(strtotime("-2 days"));
        }
        if (empty($endTime) || strtotime($endTime) >= strtotime(getDateStr(strtotime("+1 days")))) {
            $endTime = getDateStr(strtotime("-1 days"));
        }
        $date_interval = Carbon::parse($startTime)->diff(Carbon::parse($endTime));
        if ($date_interval->days > 31) {
            $this->error("查询间隔天数请勿超过31天");
        }
        $st = strtotime($startTime . " 00:00:00");
        $et = strtotime($endTime . " 23:59:59");
        $model = new DayReportModel;
        $today = getDateStr();
        for ($i = $st; $i <= $et; $i += 86400) {
            $date = getDateStr($i);
            $model->stat($date, $date == $today || $date == $update);
        }
        $list = $model->where('stat_date', 'between', [$startTime, $endTime])->order('stat_date desc')->select();
        //`stat_date` date DEFAULT NULL COMMENT '日期',
        //  `recharge` decimal(20,4) DEFAULT NULL COMMENT '充值总额',
        //  `withdraw` decimal(20,4) DEFAULT NULL COMMENT '提现总额',
        //  `recharge_user` int(11) DEFAULT '0' COMMENT '充值人数',
        //  `withdraw_user` int(11) DEFAULT '0' COMMENT '提现人数',
        //  `first_recharge_user` int(11) DEFAULT '0' COMMENT '首充人数',
        $today_list = [
            'stat_date' => '合计',
            'user_name' => '--',
            'recharge' => 0,
            'three_recharge' => 0,
            'contract_recharge' => 0,
            'sys_recharge' => 0,
            'withdraw' => 0,
            'withdraw_27' => 0,
            'withdraw_28' => 0,
            'withdraw_29' => 0,
            'recharge_user' => 0,
            'withdraw_user' => 0,
            'first_recharge_user' => 0,
            'wr_diff' => 0
        ];
        $withdraw_list = PaymentPlatformModel::where(['withdraw_is_show' => 1])->field('id,name')->select();
        $withdraw_header = [];
        $withdraw_header_ids = [];
        $withdraw_json_temp = [];
        foreach ($withdraw_list as $key => $vo) {
            $withdraw_header['withdraw_' . $vo['id']] = ($vo['name'] == 'tarjeta bancaria') ? 'Qeawapay银行卡提现' : $vo['name'] . "提现";
            $withdraw_header_ids[] = 'withdraw_' . $vo['id'];
            $withdraw_json_temp['withdraw_' . $vo['id']] = 0;
            $today_list['withdraw_' . $vo['id']] = 0;
        }

        foreach ($list as $key => $vo) {
            if (!empty($vo['recharge_json'])) {
                $recharge_json = json_decode($vo['recharge_json'], true);
            } else {
                $recharge_json = [
                    'three' => 0,
                    'contract' => 0,
                    'sys' => 0,
                ];
            }
            $list[$key]['three_recharge'] = $recharge_json['three'];
            $list[$key]['contract_recharge'] = $recharge_json['contract'];
            $list[$key]['sys_recharge'] = $recharge_json['sys'];

            $today_list['three_recharge'] += $recharge_json['three'];
            $today_list['contract_recharge'] += $recharge_json['contract'];
            $today_list['sys_recharge'] += $recharge_json['sys'];

            $today_list['recharge'] += $vo['recharge'];
            $today_list['withdraw'] += $vo['withdraw'];
            if (!empty($vo['withdraw_json'])) {
                $withdraw_json = json_decode($vo['withdraw_json'], true);
            } else {
                $withdraw_json = $withdraw_json_temp;
            }
            foreach ($withdraw_list as $val) {
                if (isset($withdraw_json['withdraw_' . $val['id']])) {
                    $today_list['withdraw_' . $val['id']] += $withdraw_json['withdraw_' . $val['id']] ?? 0;
                    $list[$key]['withdraw_' . $val['id']] = $withdraw_json['withdraw_' . $val['id']];
                } else {
                    $today_list['withdraw_' . $val['id']] += 0;
                    $list[$key]['withdraw_' . $val['id']] = 0;
                }
            }
            $today_list['recharge_user'] += $vo['recharge_user'];
            $today_list['withdraw_user'] += $vo['withdraw_user'];
            $today_list['first_recharge_user'] += $vo['first_recharge_user'];
            $today_list['wr_diff'] += $vo['wr_diff'];
        }
//        print_r(json_encode($list[0]));
//        print_r(json_encode($today_list));
//        exit();
        if ($export) {
            $header = [
                    "stat_date" => "日期",
                    "user_name" => "代理线",
                    "recharge" => "充值总额",
                    "three_recharge" => "三方充值",
                    "contract_recharge" => "合约充值",
                    "sys_recharge" => "后台补单",
                    "withdraw" => "提现总额",
                ] + $withdraw_header +
                [
                    "recharge_user" => "充值人数",
                    "withdraw_user" => "提现人数",
                    "first_recharge_user" => "首次充值人数",
                    "wr_diff" => "存取差额",
                ];
            return genXlsx($header, $list, '财务日报表');
        }

        View::assign('startTime', $startTime);
        View::assign('endTime', $endTime);
        View::assign('list', array_merge($list->toArray(), [$today_list]));
        View::assign('header', $withdraw_header);
        View::assign('header_ids', $withdraw_header_ids);
        return View::fetch();
    }

    public function day_report_stat()
    {
//        $act = $this->request->param('act/s');
//        if ($act == 'query') {
//            $date = $this->request->param('date/s');
//            $key = $this->request->param('key/s');
//            $model = new DayReportModel;
//            $model->stat($date, true, $key);
//            $this->success('ok', '', '');
//        }
        $update = $this->request->param('update/s');
        $startTime = $this->request->param('startTime/s');
        $endTime = $this->request->param('endTime/s');
        $export = $this->request->param('export/s');
        if (empty($startTime)) {
            $startTime = getDateStr(strtotime("-2 days"));
        }
        if (empty($endTime) || strtotime($endTime) >= strtotime(getDateStr(strtotime("+1 days")))) {
            $endTime = getDateStr(strtotime("-1 days"));
        }
        $date_interval = Carbon::parse($startTime)->diff(Carbon::parse($endTime));
        if ($date_interval->days > 31) {
            $this->error("查询间隔天数请勿超过31天");
        }
        $st = strtotime($startTime . " 00:00:00");
        $et = strtotime($endTime . " 23:59:59");
        $withdraw_list = PaymentPlatformModel::where(['withdraw_is_show' => 1])->field('id,name')->select();
        $list_arr = [];
        for ($i = $et; $i >= $st; $i -= 86400) {
            $date = getDateStr($i);
            $stat_date = Carbon::parse(Carbon::parse($date)->format("Y-m-d 00:00:00"));
            $where_time = [$stat_date->timestamp, Carbon::parse($stat_date)->addDays()->timestamp];
            $where = [
                ['is_xuni', '=', 0]
            ];
            $temp_data = [
                'stat_date' => $stat_date->format("Y-m-d"),
                'recharge' => RechargeModel::where($where)->where('status', 2)->whereTime('success_time', 'between', $where_time)->sum('money'),
                //三方充值
                'three_recharge' => RechargeModel::where($where)->where('type', 3)->where('status', 2)->whereTime('success_time', 'between', $where_time)->sum('money'),
                //合约充值
                'contract_recharge' => RechargeModel::where($where)->where('type', 2)->where('status', 2)->whereTime('success_time', 'between', $where_time)->sum('money'),
                //手动补单
                'sys_recharge' => RechargeModel::where($where)->where('type', 1)->where('status', 2)->whereTime('success_time', 'between', $where_time)->sum('money'),
                //总提现
                'withdraw' => WithdrawModel::where($where)->where('status', 4)->whereTime('check_time', 'between', $where_time)->sum('original_money')
            ];
            foreach ($withdraw_list as $val) {
                $temp_data['withdraw_' . $val['id']] = WithdrawModel::where($where)->where('channel', $val['id'])->where('status', 4)->whereTime('check_time', 'between', $where_time)->sum('original_money');
            }
            $list_arr[] = $temp_data;
        }
//        $list = $model->where('stat_date', 'between', [$startTime, $endTime])->order('stat_date desc')->select();
        $today_list = [
            'stat_date' => '合计',
            'three_recharge' => 0,
            'contract_recharge' => 0,
            'sys_recharge' => 0,
            'recharge' => 0,
            'withdraw' => 0
        ];

        $withdraw_header = [];
        $withdraw_header_ids = [];
        $withdraw_json_temp = [];
        foreach ($withdraw_list as $key => $vo) {
            $withdraw_header['withdraw_' . $vo['id']] = ($vo['name'] == 'tarjeta bancaria') ? 'Qeawapay银行卡提现' : $vo['name'] . "提现";
            $withdraw_header_ids[] = 'withdraw_' . $vo['id'];
            $withdraw_json_temp['withdraw_' . $vo['id']] = 0;
            $today_list['withdraw_' . $vo['id']] = 0;
        }
        foreach ($list_arr as $vo) {
            $today_list['three_recharge'] += $vo['three_recharge'];
            $today_list['contract_recharge'] += $vo['contract_recharge'];
            $today_list['sys_recharge'] += $vo['sys_recharge'];
            $today_list['recharge'] += $vo['recharge'];
            $today_list['withdraw'] += $vo['withdraw'];
            foreach ($withdraw_header_ids as $val) {
                if (isset($today_list['withdraw_' . $val])) {
                    $today_list['withdraw_' . $val] += $vo['withdraw_' . $val];
                }
            }
        }

        if ($export) {
            $header = [
                    "stat_date" => "日期",
                    "three_recharge" => "三方充值",
                    "contract_recharge" => "合约充值",
                    "sys_recharge" => "手动补单",
                ] + $withdraw_header + [
                    "recharge" => "充值总额",
                    "withdraw" => "提现总额",
                ];
            return genXlsx($header, $list_arr, '日报表');
        }

        View::assign('startTime', $startTime);
        View::assign('endTime', $endTime);
        View::assign('list', array_merge($list_arr, [$today_list]));
        View::assign('header', $withdraw_header);
        View::assign('header_ids', $withdraw_header_ids);
        return View::fetch('day_report_stat');
    }

    //充值日报
    public function day_recharge()
    {
        $user_id = $this->request->param('user_id/d');//代理线id
        $startTime = $this->request->param('startTime/s');
        $endTime = $this->request->param('endTime/s');
        if ($this->request->isAjax() || $this->request->isPost()) {
            $return = $this->getRechargeData();
            return json($return);
        }
        View::assign('startTime', $startTime);
        View::assign('endTime', $endTime);
        View::assign('user_id', $user_id);
        return View::fetch();
    }

    /**
     * 提现列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getRechargeData()
    {
        $param = input('param.');
        $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
        $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
        $limit = $param['pageSize'];
        $offset = ($param['pageNumber'] - 1) * $limit;
        //总代理
        $user_id = $this->request->param("user_id/d", 0);
        if (!empty($user_id)) {
            $uids = UserModel::where('tree_id', 'like', "%/{$user_id}/%")->where('tree_id', 'not like', '%/' . $user_id . '/')->column('id');
        } else {
            $uids = [];
        }
        $where[] = ['r.is_xuni', '=', 0];
        if (count($uids) > 0) {
            $where[] = ['r.user_id', 'in', $uids];
            $where[] = ['r.status', '=', 2];
        } else {
            $where[] = ['r.status', '=', 2];
        }
        if (!empty($param['startTime']) && !empty($param['endTime'])) {
            $start_time = strtotime($param['startTime'] . " 00:00:00");
            $end_time = strtotime($param['endTime'] . " 23:59:59");
            $where[] = ['r.success_time', 'BETWEEN', [$start_time, $end_time]];
        }
        if (!empty($param['startTime']) && empty($param['endTime'])) {
            $start_time = strtotime($param['startTime'] . " 00:00:00");
            $where[] = ['r.success_time', '>=', $start_time];
        }
        if (!empty($param['endTime']) && empty($param['startTime'])) {
            $end_time = strtotime($param['endTime'] . " 23:59:59");
            $where[] = ['r.success_time', '<=', $end_time];
        }
        $orderby = ['status' => 'ASC', 'id' => 'DESC'];
        $model = new RechargeModel();
        $selectResult = $model->getLlistsByWhere($where, $offset, $limit, $orderby);
        $status_text = config('app.recharge_status');
        foreach ($selectResult as $key => $vo) {
            $buttons = '-';
            $selectResult[$key]['top_leader'] = \app\common\model\UserModel::getTopLeader($vo['user_id']);
            $selectResult[$key]['status_text'] = $status_text[$vo['status']];
            $selectResult[$key]['operate'] = $buttons;
            $selectResult[$key]['success_time'] = date('Y-m-d H:i:s', $vo['success_time']);
            $selectResult[$key]['channel'] = PaymentPlatformModel::find($vo['pay_id']);
        }
        $return['total'] = $model->getAllLists($where);  //总数据
        $return['rows'] = $selectResult;

        $stat = $model->alias('r')
            ->join('home_user hu', 'r.user_id = hu.id', 'left')
            ->where($where)->group('status')->field('r.status,sum(r.money) AS total')->select();
        if ($stat) {
            foreach ($stat as $k => $v) {
                $stat[$k]['text'] = $status_text[$v['status']];
                $stat[$k]['total'] = i18n_sprintf($v['total']);
            }
        }
        $return['stat'] = $stat;
        return $return;
    }

    /**
     * 提现日报
     * @return mixed|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function day_withdraw()
    {
        $user_id = $this->request->param('user_id/s');//代理线id
        $startTime = $this->request->param('startTime/s');
        $endTime = $this->request->param('endTime/s');
        if ($this->request->isAjax() || $this->request->isPost()) {
            $return = $this->getWithdrawData();
            return json($return);
        }
        View::assign('startTime', $startTime);
        View::assign('endTime', $endTime);
        View::assign('user_id', $user_id);
        return View::fetch();
    }

    /**
     * 提现列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getWithdrawData()
    {
        $param = input('param.');
        $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
        $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
        $limit = $param['pageSize'];
        $offset = ($param['pageNumber'] - 1) * $limit;
        //总代理
        $user_id = $this->request->param("user_id/d", 0);
        if (!empty($user_id)) {
            $uids = UserModel::getTeamAllIds($user_id, 7);
        } else {
            $uids = [];
        }
        $where = [];
        if (count($uids) > 0) {
            $where[] = ['w.user_id', 'in', $uids];
            $where[] = ['w.status', '=', 4];
        } else {
            $where[] = ['w.status', '=', 4];
        }
        if (!empty($param['startTime']) && !empty($param['endTime'])) {
            $start_time = strtotime($param['startTime'] . " 00:00:00");
            $end_time = strtotime($param['endTime'] . " 23:59:59");
            $where[] = ['w.check_time', 'BETWEEN', [$start_time, $end_time]];
        }
        if (!empty($param['startTime']) && empty($param['endTime'])) {
            $start_time = strtotime($param['startTime'] . " 00:00:00");
            $where[] = ['w.check_time', '>=', $start_time];
        }
        if (!empty($param['endTime']) && empty($param['startTime'])) {
            $end_time = strtotime($param['endTime'] . " 23:59:59");
            $where[] = ['w.check_time', '<=', $end_time];
        }
        $orderby = ['status' => 'ASC', 'id' => 'DESC'];
        if (!empty($param['sortName']) && in_array($param['sortName'],
                ['original_money', 'create_time', 'check_time'])) {
            $orderby = ["w." . $param['sortName'] => ($param['sortOrder'] == "asc" ? "ASC" : "DESC")];
        }
        $medol = new WithdrawModel();
        $selectResult = $medol->getLlistsByWhere($where, $offset, $limit, $orderby);
        $status_arr = config('app.withdrawals_status');
        // 拼装参数
        foreach ($selectResult as $key => $vo) {
            if ($vo['type'] == 4) {
                $selectResult[$key]['usdt_money'] = fiatToUsdt($vo['money'], $vo['exchange_rate']);
            }
            $selectResult[$key]['check_time'] = $vo['check_time'] > 0 ? date('Y-m-d H:i:s', $vo['check_time']) : '-';
            $selectResult[$key]['status_text'] = $status_arr[$vo['status']];
            $selectResult[$key]['operate'] = "-";
            $selectResult[$key]['top_leader'] = \app\common\model\UserModel::getTopLeader($vo['user_id']);
            $recharge = RechargeModel::where('user_id', $vo['user_id'])
                ->where("status", 2)
                ->order('create_time desc')
                ->find();

            $selectResult[$key]['last_recharge_time'] = $recharge ? $recharge->create_time : '--';
            $channel = PaymentPlatformModel::find($vo['channel']);
            $selectResult[$key]['channel'] = $channel;

            $admin = UserModel::find($vo['admin_id']);
            $selectResult[$key]['admin_name'] = $admin ? $admin->user_name : '系统';

            $result = $vo['result'];
            $tx_status_text = '待打款';
            if ($result) {
                $tx_status_text = '打款中';
                $result = json_decode($result, true);
                if ($channel->currency_type === PaymentPlatformModel::CURRENCY_TYPE_USDT) {
                    if (isset($result['status'])) {
                        switch ($result['status']) {
                            case 3:
                                $tx_status_text = getStatusText(1, '已出款');
                                break;
                            case 4:
                                $tx_status_text = getStatusText(2, '出款失败');
                                break;
                            default:

                        }
                    }
                } elseif ($channel->currency_type === PaymentPlatformModel::CURRENCY_TYPE_BANK) {
                    $config = json_decode($channel->config, true);
                    if ($config) {
                        switch ($config['class_name']) {
                            case 'Qeawapay':
                                switch ($result['tradeResult']) {
                                    //                tradeResult 0:申请成功 1:转账成功 2:转账失败 3:转账拒绝 4:处理中
                                    case 0:
                                        $tx_status_text = getStatusText(4, '申请转账成功');
                                        break;
                                    case 1:
                                        $tx_status_text = getStatusText(1, '转账成功');
                                        break;
                                    case 2:
                                        $tx_status_text = getStatusText(2, '转账失败');
                                        break;
                                    case 3:
                                        $tx_status_text = '转账拒绝';
                                        break;
                                    case 4:
                                        $tx_status_text = '处理中';
                                }
                                break;
                            case 'Gctpkpay':
                                // status=7表示订单成功,status=2或8表示订单失败
                                switch ($result['status']) {
                                    case 7:
                                        $tx_status_text = getStatusText(1, '转账成功');
                                        break;
                                    case 2:
                                    case 8:
                                        $tx_status_text = getStatusText(2, '转账失败');
                                        break;
                                }
                                break;
                            case 'Uupay':
                            case 'Offlinepay':
                                if ($result['success']) {
                                    $tx_status_text = getStatusText(1, '转账成功');
                                } else {
                                    $tx_status_text = getStatusText(2, '转账失败');
                                }
                                $tx_fail_reason = isset($result['message']) ? $result['message'] : '';
                                break;
                        }
                    }
                }
            }
            $selectResult[$key]['tx_status_text'] = $tx_status_text;
        }
        $return['total'] = $medol->getAllLists($where);  //总数据
        $return['rows'] = $selectResult;

        $model = new WithdrawModel();

        $stat = $model->alias('w')
            ->join('home_user hu', 'w.user_id = hu.id', 'left')
            ->where($where)->group('status')->field('w.channel,w.status,sum(w.original_money) AS total')->select();
        if ($stat) {
            foreach ($stat as $k => $v) {
                $stat[$k]['text'] = $status_arr[$v['status']];
                $stat[$k]['total'] = i18n_sprintf($v['total']);
            }
        }
        $return['stat'] = $stat;
        return $return;
    }

    public function total()
    {
        $clear = $this->request->param('clear/d', 0);
        $redis_key = "stat_total:" . date("Ymd");
        $redis = Cache::store('redis');
        if ($redis->has($redis_key) && $clear == 0) {
            $data = $redis->get($redis_key);
        } else {
            //用户统计
            $real_today_reg = MemberModel::whereTime('create_time', 'today')->where('is_xuni', 0)->count();
            $xuni_today_reg = MemberModel::whereTime('create_time', 'today')->where('is_xuni', 1)->count();
            $real_month_reg = MemberModel::whereTime('create_time', 'month')->where('is_xuni', 0)->count();
            $xuni_month_reg = MemberModel::whereTime('create_time', 'month')->where('is_xuni', 1)->count();
            $real_last_month_reg = MemberModel::whereTime('create_time', 'last month')->where('is_xuni', 0)->count();
            $xuni_last_month_reg = MemberModel::whereTime('create_time', 'last month')->where('is_xuni', 1)->count();
            $real_reg = MemberModel::where('is_xuni', 0)->count();
            $xuni_reg = MemberModel::where('is_xuni', 1)->count();
            $status_1_reg = MemberModel::where('status', 1)->count();
            $status_2_reg = MemberModel::where('status', 2)->count();
            $real_user_money = MemberModel::where('is_xuni', 0)->sum('user_money');
            $xuni_user_money = MemberModel::where('is_xuni', 1)->sum('user_money');

            //数据统计
            $today = [
                'recharge' => $this->getTotalRecharge('today'),
                'withdraw' => $this->getTotalWithdraw('today'),
                'difference' => 0,
                'order_price' => $this->getTotalOrder('today', 'retail_price'),
                'order_gray' => $this->getTotalOrder('today', 'return_bonus'),
                'rebate' => $this->getTotalMoneyLog('today'),
                'handsel' => $this->getTotalMoneyLog('today', 0),
            ];
            $today['difference'] = i18n_sprintf($today['recharge'] - $today['withdraw']);
            $yesterday = [
                'recharge' => $this->getTotalRecharge('yesterday'),
                'withdraw' => $this->getTotalWithdraw('yesterday'),
                'difference' => 0,
                'order_price' => $this->getTotalOrder('yesterday', 'retail_price'),
                'order_gray' => $this->getTotalOrder('yesterday', 'return_bonus'),
                'rebate' => $this->getTotalMoneyLog('yesterday'),
                'handsel' => $this->getTotalMoneyLog('yesterday', 0),
            ];
            $yesterday['difference'] = i18n_sprintf($yesterday['recharge'] - $yesterday['withdraw']);
            $month = [
                'recharge' => $this->getTotalRecharge('month'),
                'withdraw' => $this->getTotalWithdraw('month'),
                'difference' => 0,
                'order_price' => $this->getTotalOrder('month', 'retail_price'),
                'order_gray' => $this->getTotalOrder('month', 'return_bonus'),
                'rebate' => $this->getTotalMoneyLog('month'),
                'handsel' => $this->getTotalMoneyLog('month', 0),
            ];
            $month['difference'] = i18n_sprintf($month['recharge'] - $month['withdraw']);
            $last_month = [
                'recharge' => $this->getTotalRecharge('last month'),
                'withdraw' => $this->getTotalWithdraw('last month'),
                'difference' => 0,
                'order_price' => $this->getTotalOrder('last month', 'retail_price'),
                'order_gray' => $this->getTotalOrder('last month', 'return_bonus'),
                'rebate' => $this->getTotalMoneyLog('last month'),
                'handsel' => $this->getTotalMoneyLog('last month', 0),
            ];
            $last_month['difference'] = i18n_sprintf($last_month['recharge'] - $last_month['withdraw']);
            $data = [
                'real_today_reg' => $real_today_reg,
                'xuni_today_reg' => $xuni_today_reg,
                'real_month_reg' => $real_month_reg,
                'xuni_month_reg' => $xuni_month_reg,
                'real_last_month_reg' => $real_last_month_reg,
                'xuni_last_month_reg' => $xuni_last_month_reg,
                'real_reg' => $real_reg,
                'xuni_reg' => $xuni_reg,
                'status_1_reg' => $status_1_reg,
                'status_2_reg' => $status_2_reg,
                'real_user_money' => i18n_sprintf($real_user_money),
                'xuni_user_money' => i18n_sprintf($xuni_user_money),
                'today' => $today,
                'yesterday' => $yesterday,
                'month' => $month,
                'last_month' => $last_month,
            ];
            $redis->set($redis_key, $data, 600);
        }

        View::assign($data);
        return View::fetch();
    }

    //综合报表
    public function synthesis()
    {
        if ($this->request->isAjax()) {
            $type = $this->request->param('type/s');
            $result = [];
            switch ($type) {
                case "recharge";
                    $TRC20_money = PaymentPlatformModel::getWalletBalanceData(ConfigModel::getConfigValue('collection_wallet'), 20, 'TRC20');
                    $BEP20_money = PaymentPlatformModel::getWalletBalanceData(ConfigModel::getConfigValue('bep20_collection_wallet'), 23, 'BEP20');

                    $result = [
                        'recharge_num' => $this->getTotalRechargeNum(),
                        'recharge_rep' => $this->getTotalRechargeNum() - $this->getTotalRechargeFirst(),
                        'TRC20_addr' => ConfigModel::getConfigValue('collection_wallet'),
                        'TRC20_u_balance' => $TRC20_money['USDT'],
                        'TRC20_t_balance' => $TRC20_money['TRX'],
                        'BEP20_addr' => ConfigModel::getConfigValue('bep20_collection_wallet'),
                        'BEP20_u_balance' => $BEP20_money['USDT'],
                        'BEP20_bnb_balance' => $BEP20_money['TRX'],
                    ];
                    break;
                case "brokerage";
                    $task = $this->getTotalTaskAll();
                    $TRC20_money = PaymentPlatformModel::getWalletBalanceData(ConfigModel::getConfigValue('payment_wallet'), 20, 'TRC20');
                    $BEP20_money = PaymentPlatformModel::getWalletBalanceData(ConfigModel::getConfigValue('bsc_payment_wallet'), 23, 'BEP20');
                    $result = [
                        'brokerage_all' => $task['money'],
                        'TRC20_brok_addr' => ConfigModel::getConfigValue('payment_wallet'),
                        'TRC20_brok_u_balance' => $TRC20_money['USDT'],
                        'TRC20_brok_t_balance' => $TRC20_money['TRX'],
                        'TRC20_brok_energy' => '-/-',
                        'BEP20_brok_addr' => ConfigModel::getConfigValue('bsc_payment_wallet'),
                        'BEP20_brok_u_balance' => $BEP20_money['USDT'],
                        'BEP20_brok_bnb_balance' => $BEP20_money['TRX'],
                    ];
                    break;
                case "energy";
                    $TRC20_TRX_money = PaymentPlatformModel::getWalletBalanceData(ConfigModel::getConfigValue('trx_payment_wallet'), 20, 'TRC20');
                    $result = [
                        'TRC20_trx_addr' => ConfigModel::getConfigValue('trx_payment_wallet'),
                        'TRC20_trx_u_balance' => $TRC20_TRX_money['USDT'],
                        'TRC20_trx_t_balance' => $TRC20_TRX_money['TRX'],
                        'energy_all' => $this->getTotalBrokerage(),
                        'energy_addr' => "",
                        'energy_balance' => 0,
                    ];
                    break;
                case "contribute";
                    $result = [
                        'contribute_all' => 0,
                        'contribute_addr' => "",
                        'contribute_balance' => 0,
                    ];
                    break;
                case "user_money";
                    $user_money_all = UserModel::where('is_xuni', 0)->sum('user_money');
                    $result = [
                        'user_money_all' => sprintf4f($user_money_all), // 保留两位小数，不四舍五入
                    ];
                    break;
                case "finance_product";
                    $finance_product_order_repository = new FinanceProductOrderRepository();
                    // 今日购买广告位的人数
                    $today_finance_num = $finance_product_order_repository->getTodayFinanceNum();
                    $today_finance_money = $finance_product_order_repository->getTodayFinanceMoney();
                    $result = [
                        'finance_product_user_num' => $today_finance_num,
                        'finance_product_money' => $today_finance_money,
                    ];
                    break;
                case "new_user";
                    $new_user_num = UserModel::where('is_xuni', 0)
                        ->whereTime('create_time', 'today')
                        ->where('recharge_money', '>', 0)
                        ->count();
                    $new_user_money = UserModel::where('is_xuni', 0)
                        ->whereTime('create_time', 'today')
                        ->where('recharge_money', '>', 0)
                        ->sum('user_money');
                    $result = [
                        'new_user_num' => $new_user_num,
                        'new_user_money' => sprintf4f($new_user_money),
                    ];
                    break;
                case "active_user";
                    $finance_product_order_repository = new FinanceProductOrderRepository();
                    // 今日购买广告位的人数
                    $today_finance_num = $finance_product_order_repository->getTodayFinanceNum();
                    $active_user_num_money = $finance_product_order_repository->getTodayFinanceUserMoney();
                    $result = [
                        'active_user_num' => $today_finance_num,
                        'active_user_num_money' => sprintf4f($active_user_num_money),
                    ];
                    break;
                case "balance";
                    $money_log = new MoneyLogModel();
                    $user_balance = UserModel::where('is_xuni', 0)->sum('balance_money');
                    $result = [
                        'today_add_balance_user_num' => $money_log->whereTime('create_time', 'today')
                            ->where('detail_type', 13)
                            ->where('change', 1)
                            ->where('type', MoneyLogModel::TYPE_BALANCE)
                            ->group('user_id')
                            ->count(),
                        'add_user_balance' => sprintf4f($user_balance),
                        'add_balance_user_num' => $money_log
                            ->group('user_id')
                            ->where('detail_type', 13)
                            ->where('type', MoneyLogModel::TYPE_BALANCE)
                            ->where('change', 1)
                            ->count(),
                        'today_sub_balance_user_num' => $money_log->whereTime('create_time', 'today')
                            ->where('detail_type', 13)
                            ->where('type', MoneyLogModel::TYPE_BALANCE)
                            ->where('change', 2)
                            ->group('user_id')
                            ->count(),
                        'sub_user_balance' => sprintf4f($user_balance),
                        'sub_balance_user_num' => $money_log
                            ->group('user_id')
                            ->where('detail_type', 13)
                            ->where('type', MoneyLogModel::TYPE_BALANCE)
                            ->where('change', 2)
                            ->count(),
                    ];
                    break;
            }
            $this->success(lang('刷新成功'), '', $result);
        }
        $startTime = $this->request->param('start_time/s');
        $endTime = $this->request->param('end_time/s');
        //获取时区
        $sys_data_time = ConfigModel::getConfigValue('sys_data_time');
        date_default_timezone_set($sys_data_time);
        $sys_time = lang('系统时间') . ConfigModel::$SYS_DATA_TIME[$sys_data_time] . "(" . date("m-d H:i", time()) . ")";
        //会员总人数
        $member_total = UserModel::where('status', 1)->count();
        //总充值金额
        $recharge_total = $this->getTotalRechargeAll();
        // 手动补单金额
        $manual_recharge = $this->getManualRecharge();
        //总提现金额
        $withdraw_total = $this->getTotalWithdrawAll();
        //充提差额
        $rec_wit_tolal = $recharge_total - $withdraw_total - $manual_recharge;
        $recharge_total = $recharge_total - $manual_recharge;

        if (empty($startTime)) {
            $start_time = strtotime(date("Y-m-d"));
            $startTime = date("Y-m-d " . "00:00:00");
        } else {
            $start_time = strtotime($startTime);
        }
        if (empty($endTime)) {
            $end_time = time();
            $endTime = date("Y-m-d H:i:s", time());
        } else {
            $end_time = strtotime($endTime);
        }
        $time = [$start_time, $end_time];

        //时间区间内 线下补单人数和基恩
        $background_recharge_all = $this->getTotalBackgroundRechargeAll($time);
        $background_recharge_num = $this->getTotalBackgroundRechargeNum($time);

        //指定时间内容注册人数
        $register = $this->getTotalRegisterAll($time);
        //指定时间内容注册并做任务人数
        $register_task = $this->getTotalRegisterTask($time);
        //时间区间内容充值
        $recharge_all = $this->getTotalRechargeAll($time);
        $recharge_num = $this->getTotalRechargeNum($time);
        //时间区间内容提现
        $withdraw_all = $this->getTotalWithdrawAll($time);
        $withdraw_num = $this->getTotalWithdrawNum($time);
        $all_collection = $this->getTotalCollection([]);
        $collection = $this->getTotalCollection($time);
        //获取首次充值的人数
        $recharge_first = $this->getTotalRechargeFirst($time);
        //复从充人数
        $recharge_rep = $this->getTotalRechargeRep($time);
        //签到人数
        $sign_num = 0;

        //任务统计
        $task_all = $this->getTotalTaskAll($time);
        //任务数量
        $task_num = $task_all['num'];
        //任务佣金
        $task_money = $task_all['money'];

        View::assign([
            'startTime' => $startTime,
            'endTime' => $endTime,
            'member_total' => intval($member_total),
            'recharge_total' => i18n_sprintf($recharge_total),
            'manual_recharge' => i18n_sprintf($manual_recharge),
            'withdraw_total' => i18n_sprintf($withdraw_total),
            'rec_wit_tolal' => i18n_sprintf($rec_wit_tolal),
            'sys_time' => $sys_time,
            'register' => $register,
            'register_task' => $register_task,
            'recharge_all' => $recharge_all,
            'recharge_num' => $recharge_num,
            'background_recharge_all' => $background_recharge_all,
            'background_recharge_num' => $background_recharge_num,
            'withdraw_all' => $withdraw_all,
            'withdraw_num' => $withdraw_num,
            'recharge_first' => $recharge_first,
            'recharge_rep' => $recharge_rep,
            'sign_num' => $sign_num,
            'task_num' => $task_num,
            'task_money' => $task_money,
            'all_collection' => $all_collection,
            'collection' => $collection,
        ]);
        return View::fetch();
    }

    private function getTotalReg($time = 'today')
    {
        $model = new MemberModel;
        if (is_array($time)) {
            $model = $model->whereTime('create_time', 'between', $time);
        } else {
            $model = $model->whereTime('create_time', $time);
        }
        $result = $model->where('is_xuni', 0)->count();
        return intval($result);
    }

    /**
     * 获取当天充值用户数
     * @param type $time
     * @return type
     */
    private function getTotalRechargeUsers($time = 'today')
    {
        $model = new RechargeModel;
        if (is_array($time)) {
            $model = $model->whereTime('create_time', 'between', $time);
        } else {
            $model = $model->whereTime('create_time', $time);
        }
        $result = $model->where('status', 2)->whereIn('user_id', function ($query) use ($time) {
            if (is_array($time)) {
                $query->name('home_user')->whereTime('create_time', 'between', $time)->where('is_xuni', 0)->field('id');
            } else {
                $query->name('home_user')->whereTime('create_time', $time)->where('is_xuni', 0)->field('id');
            }
        })->count('DISTINCT user_id');
        return intval($result);
    }

    private function getTotalRecharge($time = 'today')
    {
        if (in_array($time, ['today', 'yesterday'])) {
            $result = RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 2)->whereTime('r.create_time', $time)->sum('r.money');
        } else {
            $result = DayReportModel::where('user_id', 0)->whereTime('stat_date', $time)->sum('recharge');
        }
        return i18n_sprintf($result);
    }

    private function getTotalWithdraw($time = 'today')
    {
        if (in_array($time, ['today', 'yesterday'])) {
            $result = WithdrawModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 4)->whereTime('r.check_time', $time)->sum('r.original_money');
        } else {
            $result = DayReportModel::where('user_id', 0)->whereTime('stat_date', $time)->sum('withdraw');
        }
        return i18n_sprintf($result);
    }

    private function getTotalOrder($time = 'today', $field = 'retail_price')
    {
        if (in_array($time, ['today', 'yesterday'])) {
            $result = Db::name('order')->where('is_xuni', 0)->where('status', 2)->whereTime('create_time', $time)->sum($field);
        } else {
            $result = DayReportModel::where('user_id', 0)->whereTime('stat_date', $time)->sum($field == 'retail_price' ? 'order_price' : 'order_gray');
        }
        return i18n_sprintf($result);
    }

    private function getTotalMoneyLog($time = 'today', $type = 2)
    {
        if (in_array($time, ['today', 'yesterday'])) {
            $full_table_name = getMoneyLogTableByDay($time);
            $result = Db::table($full_table_name)->where('type', $type)->where('is_xuni', 0)->whereTime('create_time', $time)->sum('money');
        } else {
            $result = DayReportModel::where('user_id', 0)->whereTime('stat_date', $time)->sum($type == 2 ? 'rebate' : 'manual_money');
        }
        return i18n_sprintf($result);
    }

    public function get_reg_option()
    {
        $colors = getColors(100);
        $param = $this->request->param();

        if (!isset($param['startTime']) || !isset($param['endTime'])) {
            $param['startTime'] = getDateStr();
            $param['endTime'] = getDateStr();
        }
        $start_date = $param['startTime'];
        $end_date = $param['endTime'];
        $start_time = strtotime($param['startTime'] . " 00:00:00");
        $end_time = strtotime($param['endTime'] . " 23:59:59");

        $type = '';
        if ($end_time - $start_time <= 86400) {
            $type = 'hour';
            $xAxisName = "时";
            $text = getChineseDate($end_time) . " 24小时内注册统计图";
        } elseif (floor(($end_time - $start_time) / 86400) <= 31 && timeToStr($start_time, "Y") === timeToStr($end_time, "Y")) {
            $type = 'day';
            $xAxisName = "天";
            $text = getChineseDate($start_time) . (($end_time - $start_time) > 86400 ? " 至 " . timeToStr($end_time, "m月d日") : "") . " 注册统计图";
        } elseif (timeToStr($start_time, "Y") === timeToStr($end_time, "Y")) {
            $type = 'months';
            $xAxisName = "月";
            $start_time = getMkTime(timeToStr($start_time, "Y-m-01 00:00:00"));
            $end_time = getMkTime(timeToStr($end_time, "Y-m-" . timeToStr($end_time, "d") . " 23:59:59"));
            $text = timeToStr($start_time, "Y年m月") . " 至 " . timeToStr($end_time, "Y年m月") . " 注册统计图";
        } else {
            $type = 'year';
            $xAxisName = "年";
            $start_time = getMkTime(timeToStr($start_time, "Y-01-01 00:00:00"));
            $end_time = getMkTime(timeToStr($end_time, "Y-12-31 23:59:59"));
            $text = timeToStr($start_time, "Y年") . " 至 " . timeToStr($end_time, "Y年") . " 注册统计图";
        }
        $option = [
            "title" => ["text" => $text, 'textStyle' => ['fontSize' => 16], 'subtext' => '', "subtextStyle" => ['fontWeight' => 'bold', 'color' => '#666']],
            "color" => ["#2e6da4"],
            "tooltip" => ["trigger" => "axis", "axisPointer" => ["type" => 'shadow']],
            "grid" => ["left" => '3%', "right" => '6%', "bottom" => '8%', "top" => '14%', "containLabel" => true],
            "xAxis" => ["type" => 'category', "data" => [], "axisTick" => ["show" => false], "axisLine" => ["show" => false], "boundaryGap" => ['50%', '20%']],
            "yAxis" => ["type" => "value", 'nameLocation' => 'start', 'nameGap' => 25],
            "series" => [],
            "toolbox" => [
                "show" => true,
                "right" => '25px',
                "top" => '125px',
                "orient" => 'vertical',
                "feature" => [
                    "magicType" => ["type" => ['line', 'bar']],
                    "saveAsImage" => ["type" => "png",],
                    "dataView" => ["show" => true]
                ],
            ]
        ];

        $k = 0;
        $labels = $series = $datasets = [];
        if ($type === 'day') {
            for ($i = $start_time; $i <= $end_time; $i += 86400) {
                $day = timeToStr($i, 'm-d');
                $labels[] = $day;
                $datasets[$day] = 0;
            }
        } elseif ($type === 'months') {
            for ($i = intval(timeToStr($start_time, "m")); $i <= intval(timeToStr($end_time, "m")); $i++) {
                $lab = str_pad($i, 2, 0, STR_PAD_LEFT);
                $labels[] = $lab;
                $datasets[$lab] = 0;
            }
        } elseif ($type === 'year') {
            for ($i = timeToStr($start_time, "Y"); $i <= timeToStr($end_time, "Y"); $i++) {
                $year = "{$i}";
                $labels[] = $year;
                $datasets[$year] = 0;
            }
        } elseif ($type === 'hour') {
            for ($i = 0; $i <= 23; $i++) {
                $lab = str_pad($i, 2, 0, STR_PAD_LEFT);
                $hour = "{$lab}:00";
                $labels[] = $hour;
                $datasets[$hour] = 0;
            }
        }

        $start_date = getDateStr($start_time) . " 00:00:00";
        $end_date = getDateStr($end_time) . " 23:59:59";

        $stat = Db::name('home_user')->where('is_xuni', 0)->whereTime('create_time', 'BETWEEN', [$start_date, $end_date])
            ->select();

        for ($i = 0; $i < count($stat); $i++) {
            if ($type === 'day') {
                $key = timeToStr($stat[$i]['create_time'], 'm-d');
            } elseif ($type === 'months') {
                $key = timeToStr($stat[$i]['create_time'], 'm');
            } elseif ($type === 'year') {
                $key = timeToStr($stat[$i]['create_time'], 'Y');
            } elseif ($type === 'hour') {
                $key = timeToStr($stat[$i]['create_time'], 'H:00');
            }

            if (isset($datasets[$key])) {
                $datasets[$key] = intval($datasets[$key]) + 1;
            } else {
                $datasets[$key] = 1;
            }
        }

        if (empty($datasets)) {
            $this->error("无数据");
        }

        $option["xAxis"]['data'] = array_values(array_unique($labels));
//        $option["legend"]['data'] = array_values(array_unique($legend));
        $option["series"][] = array(
            "type" => "line",
            "data" => array_values($datasets),
        );
        $this->success("ok", "", $option);
    }

    //获取总充值金额
    private function getTotalRechargeAll($time = [])
    {
        if (empty($time)) {
            return RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id', 'left')
                ->where('r.from', 1)
                ->where('u.is_xuni', 0)
                ->where('r.status', 2)->sum('r.money');
        } else {
            return RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 2)
                ->where('r.from', 1)
                ->whereBetween('r.create_time', $time)->sum('r.money');
        }
    }

    private function getTotalBackgroundRechargeAll($time = [])
    {
        if (empty($time)) {
            return RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id', 'left')
                ->where('r.from', 2)
                ->where('r.status', 2)->sum('r.money');
        } else {
            return RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('r.status', 2)
                ->where('r.from', 2)
                ->whereBetween('r.create_time', $time)->sum('r.money');
        }
    }

    // 获取手动补单金额
    private function getManualRecharge($time = [])
    {
        if (empty($time)) {
            return RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)
                ->where('r.status', 2)
                ->where('r.remark', '手动补单')
                ->sum('r.money');
        } else {
            return RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)
                ->where('r.status', 2)
                ->where('r.remark', '手动补单')
                ->whereBetween('r.create_time', $time)
                ->sum('r.money');
        }
    }

    //获取总提现金额
    private function getTotalWithdrawAll($time = [])
    {
        if (empty($time)) {
            return WithdrawModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 4)->sum('r.original_money');
        } else {
            return WithdrawModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 4)->whereBetween('r.create_time', $time)->sum('r.original_money');
        }
    }

    //获取时间区间内注册人数
    private function getTotalRegisterAll($time = [])
    {
        if (empty($time)) {
            return UserModel::where('is_xuni', 0)->where('status', 1)->count();
        }
        return UserModel::where('is_xuni', 0)->where('status', 1)->whereBetween('create_time', $time)->count();
    }

    //获取时间区间内做任务的注册人数
    private function getTotalRegisterTask($time = [])
    {
        if (empty($time)) {
            return TaskModel::alias('t')->join('home_user u', 'u.id=t.user_id')->where('u.is_xuni', 0)->where('u.status', 1)->where('t.status', 2)->group('t.user_id')->count();
        }
        return TaskModel::alias('t')->join('home_user u', 'u.id=t.user_id')->where('u.is_xuni', 0)->where('u.status', 1)->where('t.status', 2)->group('t.user_id')->whereBetween('u.create_time', $time)->count();
    }

    //获取时间区间内充值人数
    private function getTotalRechargeNum($time = [])
    {
        if (empty($time)) {
            return RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('r.from', 1)
                ->where('u.is_xuni', 0)->where('r.status', 2)->group('r.user_id')->count();
        } else {
            return RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('r.from', 1)
                ->where('u.is_xuni', 0)->where('r.status', 2)->whereBetween('r.create_time', $time)->group('r.user_id')->count();
        }
    }

    private function getTotalBackgroundRechargeNum($time = [])
    {
        if (empty($time)) {
            return RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('r.from', 2)
                ->where('r.status', 2)->group('r.user_id')->count();
        } else {
            return RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('r.from', 2)
                ->where('r.status', 2)->whereBetween('r.create_time', $time)->group('r.user_id')->count();
        }
    }


    //获取时间区间内提现人数
    private function getTotalWithdrawNum($time = [])
    {
        if (empty($time)) {
            return WithdrawModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 4)->group('r.user_id')->count();
        } else {
            return WithdrawModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 4)->whereBetween('r.create_time', $time)->group('r.user_id')->count();
        }
    }

    //获取时间区间内充值人数
    private function getTotalRechargeFirst($time = [])
    {
        if (empty($time)) {
            $count = RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 2)->where('r.is_first', 1)->group('r.user_id')->count();
        } else {
            $count = RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 2)->where('r.is_first', 1)->whereBetween('r.create_time', $time)->group('r.user_id')->count();
        }
        return $count;
    }

    //获取时间区间内充值人数
    private function getTotalRechargeRep($time = [])
    {
        if (empty($time)) {
            $count = RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 2)->where('r.is_first', 0)->group('r.user_id')->count();
        } else {
            $count = RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 2)->where('r.is_first', 0)->whereBetween('r.create_time', $time)->group('r.user_id')->count();
        }
        return $count;
    }

    /**
     * 获取任务统计情况
     * @param $time
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getTotalTaskAll($time = [])
    {
        if (empty($time)) {
            $data = TaskModel::where('status', 2)->field('SUM(money) as all_money,count(id) as all_num')->find();
        } else {
            $data = TaskModel::where('status', 2)->field('SUM(money) as all_money,count(id) as all_num')->whereBetween('create_time', $time)->find();
        }
        return [
            'num' => $data['all_num'] ?? 0,
            'money' => $data['all_money'] ?? 0
        ];
    }

    /**
     * 总佣金余额
     * @return float|int|string
     */
    private function getTotalBrokerage()
    {
        return UserModel::where('status', 1)->sum('brokerage_money');
    }

    /**
     * 获取归集次数
     * @param array $time
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getTotalCollection($time = [])
    {
        if (empty($time)) {
            $data = CollectionModel::field('success,chain,coin,money')->select();
        } else {
            $data = CollectionModel::field('success,chain,coin,money')->whereBetween('create_time', $time)->select();
        }
        $temp = [];
        $temp['TRC20-USDT'] = [
            'name' => 'TRC20-USDT',
            'num' => 0,
            'money' => 0
        ];
        $temp['TRC20-TRX'] = [
            'name' => 'TRC20-TRX',
            'num' => 0,
            'money' => 0
        ];
        $temp['BEP20-USDT'] = [
            'name' => 'BEP20-USDT',
            'num' => 0,
            'money' => 0
        ];
        $temp['BEP20-BNB'] = [
            'name' => 'BEP20-BNB',
            'num' => 0,
            'money' => 0
        ];
        foreach ($data as $v) {
            if (!isset($temp[$v['chain'] . "-" . $v['coin']])) {
                $temp[$v['chain'] . "-" . $v['coin']] = [
                    'name' => $v['chain'] . "-" . $v['coin'],
                    'num' => 0,
                    'money' => 0
                ];
            }
            $temp[$v['chain'] . "-" . $v['coin']]['name'] = $v['chain'] . "-" . $v['coin'];
            $temp[$v['chain'] . "-" . $v['coin']]['num'] += $v['success'];
            $temp[$v['chain'] . "-" . $v['coin']]['money'] += $v['money'];
        }
        return $temp;
    }

    public function new_day_report()
    {
        $startDate = $this->request->param('startDate/s', date('Y-m-d', strtotime('-1 day')));
        $endDate = $this->request->param('endDate/s', date('Y-m-d', strtotime('-1 day')));
        $param = input('param.');
        if ($this->request->isAjax()) {
            $data = [];
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
            $where = [];
            if (!empty($param['leader1'])) {
                $where[] = ['member_model.tree_id', 'like', '%/' . $param['leader1'] . '/%'];
                $where[] = ['member_model.tree_id', 'not like', '%/' . $param['leader1'] . '/'];
            }
            $where[] = ['member_model.is_xuni', '=', 0];
            while ($startDate <= $endDate) {
                $date = $startDate->format('Y-m-d');
                $where_time = [$startDate->startOfDay()->timestamp, $startDate->endOfDay()->timestamp];
                // 只展示当日平台内所有真实账户的总余额（虚拟号不计在内）
                $platformAmount = 0; // 平台账户余额、AI托管余额、广告位冻结余额总和
                // 平台账户余额
                $user_money = MemberModel::alias('member_model')->where($where)->sum('user_money');
                // AI托管冻结金额
                $ai_money = InvestmentProductOrderModel::hasWhere('user')
                    ->where($where)
                    ->where('investment_product_order_model.status', 'in', [
                        InvestmentProductOrderRepository::STATUS_PURCHASED
                    ])
                    ->sum('money');

                // 广告库存
                $ad_money = FinanceProductOrderModel::hasWhere('user')
                    ->where($where)
                    ->where('finance_product_order_model.status', 'in', [
                        FinanceProductOrderRepository::STATUS_PURCHASED
                    ])
                    ->sum('money');
                // 只展示当日平台内所有真实账户的总余额（虚拟号不计在内）
                $platformAmount = round($user_money + $ai_money + $ad_money, 2);
                $data[] = [
                    'date' => $date,
                    // 今日三方充值总额 计算平台三方的充值总额
                    'totalAmount' => RechargeModel::hasWhere('user')
                        ->where($where)
                        ->where('from', RechargeRepository::FROM_FRONT) // 前台线下充值
                        ->where('type', 'in', [RechargeModel::TYPE_THIRD, RechargeModel::TYPE_BANK]) // 三方充值
                        ->where('recharge_model.status', RechargeRepository::STATUS_SUCCESS) // 充值成功
                        ->whereTime('success_time', 'between', $where_time)
                        ->sum('money'),
                    // 今日三方充值总人数 计算三方充值总人数
                    'totalPeople' => RechargeModel::hasWhere('user')
                        ->where($where)
                        ->where('from', RechargeRepository::FROM_FRONT) // 前台线下充值
                        ->where('type', 'in', [RechargeModel::TYPE_THIRD, RechargeModel::TYPE_BANK]) // 三方充值
                        ->where('recharge_model.status', RechargeRepository::STATUS_SUCCESS)
                        ->whereTime('success_time', 'between', $where_time)
                        ->count('DISTINCT user_id'),
                    // 今日合约充值总额
                    'contractAmount' => RechargeModel::hasWhere('user')
                        ->where($where)
                        ->where('type', RechargeModel::TYPE_CONTRACT) // 合约充值
                        ->where('recharge_model.status', RechargeRepository::STATUS_SUCCESS) // 充值成功
                        ->whereTime('success_time', 'between', $where_time)
                        ->sum('money'),
                    // 今日合约充值总人数
                    'contractPeople' => RechargeModel::hasWhere('user')
                        ->where($where)
                        ->where('type', RechargeModel::TYPE_CONTRACT)
                        ->where('recharge_model.status', RechargeRepository::STATUS_SUCCESS)
                        ->whereTime('success_time', 'between', $where_time)
                        ->count('DISTINCT user_id'),
                    // 今日手动补单总额
                    'manualAmount' => RechargeModel::hasWhere('user')
                        ->where($where)
                        ->where('type', RechargeModel::TYPE_BANK) // 手动补单
                        ->where('from', RechargeRepository::FROM_ADMIN)
                        ->where('recharge_model.status', RechargeRepository::STATUS_SUCCESS) // 充值成功
                        ->whereTime('success_time', 'between', $where_time)
                        ->sum('money'),
                    // 今日手动补单总人数
                    'manualPeople' => RechargeModel::hasWhere('user')
                        ->where($where)
                        ->where('type', RechargeModel::TYPE_BANK)
                        ->where('from', RechargeRepository::FROM_ADMIN)
                        ->where('recharge_model.status', RechargeRepository::STATUS_SUCCESS)
                        ->whereTime('success_time', 'between', $where_time)
                        ->count('DISTINCT user_id'),
//                    // 今日提现总额 除线下转账和虚拟号提现的所有订单总额
                    'thirdWithdrawAmount' => WithdrawModel::hasWhere('user')
                        ->where($where)
                        ->where('withdraw_model.status', 4) // 4-提现成功
                        ->where('withdraw_model.channel', "<>", 30) // 30 线下转账
                        ->whereTime('check_time', 'between', $where_time)
                        ->sum('original_money'),
//                    // 今日提现人数 除线下转账和虚拟号提现的人数总和
//                    'withdrawPeople' => WithdrawModel::hasWhere('user')
//                        ->where($where)
//                        ->where('withdraw_model.status', 4)
//                        ->where('withdraw_model.channel', "<>", 30)
//                        ->whereTime('check_time', 'between', $where_time)
//                        ->count('DISTINCT user_id'),
                    // 今日申请提现总额 除线下转账和虚拟号提现的所有订单总额
                    'withdrawApplicationAmount' => WithdrawModel::hasWhere('user')
                        ->where($where)
                        ->where('withdraw_model.channel', "<>", 30) // 30 线下转账
                        ->whereTime('create_time', 'between', $where_time)
                        ->sum('original_money'),
                    // 今日申请提现人数 除线下转账和虚拟号提现的人数总和
                    'withdrawApplicationPeople' => WithdrawModel::hasWhere('user')
                        ->where($where)
                        ->where('withdraw_model.channel', "<>", 30)
                        ->whereTime('create_time', 'between', $where_time)
                        ->count('DISTINCT user_id'),
                    // 今日广告位总额 这边只展示真实账号购买的广告库存总额
                    'adAmount' => FinanceProductOrderModel::hasWhere('user')
                        ->where($where)
                        ->where('finance_product_order_model.status', 'in', [
                            FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED,
                        ])
                        ->whereTime('finance_product_order_model.create_time', 'between', $where_time)
                        ->sum('price'),
                    // 今日广告位收益 这边只展示真实账号购买的广告库存总收益
                    'adIncome' => FinanceProductOrderModel::hasWhere('user')
                        ->where($where)
                        ->where('finance_product_order_model.status', 'in', [
                            FinanceProductOrderRepository::STATUS_SETTLED
                        ])
                        ->whereTime('finance_product_order_model.settlement_time', 'between', $where_time)
                        ->sum('profit_money'),
                    // 今日活跃总人数 这边只展示真实账号有购买广告库存总人数
                    'activePeople' => FinanceProductOrderModel::hasWhere('user')
                        ->where($where)
                        ->where('finance_product_order_model.status', 'in', [
                            FinanceProductOrderRepository::STATUS_PURCHASED,
                            FinanceProductOrderRepository::STATUS_SETTLED
                        ])
                        ->whereTime('finance_product_order_model.create_time', 'between', $where_time)
                        ->count('DISTINCT user_id'),
                    // Ai托管总额 只展示真实账号的托管总额
                    'aiAmount' => InvestmentProductOrderModel::hasWhere('user')
                        ->where($where)
                        ->where('investment_product_order_model.status', 'in', [
                            InvestmentProductOrderRepository::STATUS_PURCHASED, InvestmentProductOrderRepository::STATUS_SETTLED,
                        ])
                        ->whereTime('investment_product_order_model.create_time', 'between', $where_time)
                        ->sum('money'),
                    // Ai托管总收益 只展示真实账号的托管总收益
                    'aiIncome' => InvestmentProductOrderModel::hasWhere('user')
                        ->where($where)
                        ->where('investment_product_order_model.status', 'in', [
                            InvestmentProductOrderRepository::STATUS_SETTLED
                        ])
                        ->whereTime('investment_product_order_model.settlement_time', 'between', $where_time)
                        ->sum('profit_money'),
                    // Ai托管总人数 只展示真实账号的托管总人数
                    'aiIncomePeople' => InvestmentProductOrderModel::hasWhere('user')
                        ->where($where)
                        ->where('investment_product_order_model.status', 'in', [
                            InvestmentProductOrderRepository::STATUS_PURCHASED,
                            InvestmentProductOrderRepository::STATUS_SETTLED
                        ])
                        ->whereTime('investment_product_order_model.create_time', 'between', $where_time)
                        ->count('DISTINCT user_id'),
                    // 轮盘奖励总金额 只展示真实账号的轮盘奖励总额
                    'wheelAmount' => PddTurntableRecordModel::hasWhere('user')
                        ->where($where)
                        ->where('is_receive', 1)
                        ->whereTime('pdd_turntable_record_model.update_time', 'between', $where_time)
                        ->sum('amount'),
                    // 轮盘领取总人数 只展示真实账号的轮盘领取总人数
                    'wheelPeople' => PddTurntableRecordModel::hasWhere('user')
                        ->where($where)
                        ->where('is_receive', 1)
                        ->whereTime('pdd_turntable_record_model.update_time', 'between', $where_time)
                        ->count('DISTINCT user_id'),
                    // 今日签到总人数 只展示真实账号的签到总人数
                    'signPeople' => SignRecordModel::hasWhere('user')
                        ->where($where)
                        ->whereTime('sign_record_model.create_time', 'between', $where_time)
                        ->count('DISTINCT user_id'),
                    // 今日签到总余额 只展示真实账号的签到总奖励金额
                    'signAmount' => SignRecordModel::hasWhere('user')
                        ->where($where)
                        ->whereTime('sign_record_model.create_time', 'between', $where_time)
                        ->sum('award'),
                    // 直推达标总返利 只展示真实账号今日直推达标的总返利金额
                    'directPushAmount' => MoneyLogModel::hasWhere('user')
                        ->where($where)
                        ->where('detail_type', 19)
                        ->whereTime('money_log_model.create_time', 'between', $where_time)
                        ->sum('money'),
                    // 直推达标总人数 只展示真实账号今日直推达标的总人数
                    'directPushPeople' => MoneyLogModel::hasWhere('user')
                        ->where($where)
                        ->where('detail_type', 19)
                        ->whereTime('money_log_model.create_time', 'between', $where_time)
                        ->count('DISTINCT user_id'),
                    // 活动直推奖励领取总金额 只展示真实账号今日活动直推奖励领取总金额
                    'ActivityDirectPushAmount' => ActivitynewRecordModel::hasWhere('user')
                        ->where($where)
                        ->where('status', 1)
                        ->whereTime('activitynew_record_model.create_time', 'between', $where_time)
                        ->sum('reward'),
                    // 活动直推奖励领取人数 只展示真实账号今日活动直推奖励领取人数
                    'ActivityDirectPushPeople' => ActivitynewRecordModel::hasWhere('user')
                        ->where($where)
                        ->where('status', 1)
                        ->whereTime('activitynew_record_model.create_time', 'between', $where_time)
                        ->count('DISTINCT user_id'),
                    // 日周月领取总金额 只展示当日真实账号今日领取该奖励总金额
//                    'weeklyAmount' => MoneyLogModel::hasWhere('user')
//                        ->where($where)
//                        ->where('detail_type', 15)
//                        ->whereTime('money_log_model.create_time', 'between', $where_time)
//                        ->sum('money'),
//                    // 日周月领取总人数 只展示当提真实账号今日领取该奖励总人数
//                    'weeklyPeople' => MoneyLogModel::hasWhere('user')
//                        ->where($where)
//                        ->where('detail_type', 15)
//                        ->whereTime('money_log_model.create_time', 'between', $where_time)
//                        ->count('DISTINCT user_id'),
                    // 团队活跃返佣总额 真实账号的团队今日直推三级内的返佣总额// TODO 这个地方很慢
                    'teamActivePeople' => MoneyLogModel::hasWhere('user')
                        ->where($where)
                        ->where('detail_type', 'in', [9, 10, 11])
                        ->whereTime('money_log_model.create_time', 'between', $where_time)
                        ->sum('money'),
                    'newRegisterPeople' => MemberModel::where("create_time", 'between', $where_time)
                        ->where("is_xuni", 0)
                        ->count('id'),
                    // 新增注册总人数  只展示当日新增注册的三方充值总人数 (当日注册并三方充值人数）
                    'newRegisterPeopleRecharge' => RechargeModel::hasWhere('user', [['create_time', 'between', $where_time]])
                        ->where($where)
                        ->where('type', "<>", 1) // 1 手动补单
                        ->where('type', "<>", 2) // 2 合约充值
                        ->where('recharge_model.status', 2) // 充值成功
                        ->count('DISTINCT member_model.id'),
                    // 新增注册总充值 只展示当日新增注册的三方、合约充值总额
                    'newRegisterAmount' => RechargeModel::hasWhere('user', [['create_time', 'between', $where_time]])
                        ->where($where)
                        ->where('type', "<>", 1) // 1 手动补单
                        ->where('recharge_model.status', 2) // 充值成功
                        ->sum('money'),
                    // 新增注册账户总余额 只展示当日新增有真实充值的账户总余额
                    'newRegisterAccount' => DB::table(RechargeModel::hasWhere('user', [['create_time', 'between', $where_time]])
                            ->where($where)
                            ->where('type', "<>", 1) // 1 手动补单
                            ->where('recharge_model.create_time', 'between', $where_time)
                            ->group('user_id')
                            ->field('member_model.user_money')
                            ->buildSql() . ' a')->sum('user_money'),
                    // 今日平台总余额 (平台账户余额、AI托管余额、广告位冻结余额总和) 只展示当日平台内所有真实账户的总余额（虚拟号不计在内）
                    'platformAmount' => $platformAmount,

                ];
                $startDate->addDay();
            }


            return json(msg(1, $data, $data ? lang('success') : lang('暂无数据')));
        }
        $agent = MemberModel::where('is_proxy', 1)->select();
        View::assign('agent', $agent);
        View::assign('startDate', $startDate);
        View::assign('endDate', $endDate);
        return View::fetch();
    }

    public function new_day_report2()
    {
        $startDate = $this->request->param('startDate/s', date('Y-m-d', strtotime('-1 day')));
        $endDate = $this->request->param('endDate/s', date('Y-m-d', strtotime('-1 day')));
        $param = input('param.');
        if ($this->request->isAjax()) {
            $data = [];
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();

            if (!empty($param['leader1'])) {
                // 获取代理列表.
                $users = UserModel::where('is_proxy', 1)->where('id', $param['leader1'])->select();
            } else {
                // 获取代理列表.
                $users = UserModel::where('is_proxy', 1)->select();
            }
            $where[] = ['member_model.is_xuni', '=', 0];
            while ($startDate <= $endDate) {
                $date = $startDate->format('Y-m-d');
                $where_time = [$startDate->startOfDay()->timestamp, $startDate->endOfDay()->timestamp];

                foreach ($users as $user) {
                    $user_id = $user->id;
                    // 获取下级用户
                    $team_ids = MemberModel::alias('member_model')
                        ->where($where)
                        ->where('tree_id', 'like', "%/{$user_id}/%")
                        ->where('tree_id', 'not like', '%/' . $user_id . '/')
                        ->column('id');
                    $user_ids = '0';
                    if ($team_ids) {
                        $user_ids = implode(',', $team_ids);
                    }


                    // 只展示当日平台内所有真实账户的总余额（虚拟号不计在内）
                    $platformAmount = 0; // 平台账户余额、AI托管余额、广告位冻结余额总和
                    // 平台账户余额
                    $user_money = MemberModel::alias('member_model')->where($where)->where('id', 'in', $user_ids)->sum('user_money');
                    // AI托管冻结金额
                    $ai_money = InvestmentProductOrderModel::hasWhere('user')
                        ->where($where)
                        ->where('user_id', 'in', $user_ids)
                        ->where('investment_product_order_model.status', 'in', [
                            InvestmentProductOrderRepository::STATUS_PURCHASED,
                        ])
                        ->sum('money');
                    // 广告库存
                    $ad_money = FinanceProductOrderModel::hasWhere('user')
                        ->where($where)
                        ->where('user_id', 'in', $user_ids)
                        ->where('finance_product_order_model.status', 'in', [
                            FinanceProductOrderRepository::STATUS_PURCHASED,
                        ])
                        ->sum('money');
                    // 只展示当日平台内所有真实账户的总余额（虚拟号不计在内）
                    $platformAmount = round($user_money + $ai_money + $ad_money, 2);

                    $data[] = [
                        'date' => $date,
                        'agentName' => $user->remark,
                        // 充值金额额
                        'totalAmount' => RechargeModel::hasWhere('user')
                            ->where($where)
                            ->where('from', RechargeRepository::FROM_FRONT) // 前台线下充值
                            ->where('type', 'in', [RechargeModel::TYPE_THIRD, RechargeModel::TYPE_BANK]) // 三方充值
                            ->where('recharge_model.status', 2)
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('success_time', 'between', $where_time)
                            ->sum('money'),
                        // 充值人数
                        'totalPeople' => RechargeModel::hasWhere('user')
                            ->where($where)
                            ->where('from', RechargeRepository::FROM_FRONT) // 前台线下充值
                            ->where('type', 'in', [RechargeModel::TYPE_THIRD, RechargeModel::TYPE_BANK]) // 三方充值
                            ->where('recharge_model.status', 2)
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('success_time', 'between', $where_time)
                            ->count('DISTINCT user_id'),
                        // 合约充值
                        'contractAmount' => RechargeModel::hasWhere('user')
                            ->where($where)
                            ->where('type', 2)
                            ->where('recharge_model.status', 2)
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('success_time', 'between', $where_time)
                            ->sum('money'),
                        // 合约充值人数
                        'contractPeople' => RechargeModel::hasWhere('user')
                            ->where($where)
                            ->where('type', 2)
                            ->where('recharge_model.status', 2)
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('success_time', 'between', $where_time)
                            ->count('DISTINCT user_id'),
                        // 补单金额
                        'manualAmount' => RechargeModel::hasWhere('user')
                            ->where($where)
                            ->where('type', RechargeModel::TYPE_BANK)
                            ->where('from', RechargeRepository::FROM_ADMIN)
                            ->where('recharge_model.status', RechargeRepository::STATUS_SUCCESS)
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('success_time', 'between', $where_time)
                            ->sum('money'),
                        // 补单人数
                        'manualPeople' => RechargeModel::hasWhere('user')
                            ->where($where)
                            ->where('type', RechargeModel::TYPE_BANK)
                            ->where('from', RechargeRepository::FROM_ADMIN)
                            ->where('recharge_model.status', RechargeRepository::STATUS_SUCCESS)
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('success_time', 'between', $where_time)
                            ->count('DISTINCT user_id'),
//                        // 提现总额
                        'thirdWithdrawAmount' => WithdrawModel::hasWhere('user')
                            ->where($where)
                            ->where('withdraw_model.status', 4)
                            ->where('withdraw_model.channel', "<>", 30)
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('check_time', 'between', $where_time)
                            ->sum('original_money'),
//                        // 提现人数
//                        'withdrawPeople' => WithdrawModel::hasWhere('user')
//                            ->where($where)
//                            ->where('withdraw_model.status', 4)
//                            ->where('withdraw_model.channel', "<>", 30)
//                            ->where('user_id', 'in', $user_ids)
//                            ->whereTime('check_time', 'between', $where_time)
//                            ->count('DISTINCT user_id'),
                        // 申请提现总额
                        'withdrawApplicationAmount' => WithdrawModel::hasWhere('user')
                            ->where($where)
                            ->where('withdraw_model.channel', "<>", 30)
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('create_time', 'between', $where_time)
                            ->sum('original_money'),
                        // 申请提现人数
                        'withdrawApplicationPeople' => WithdrawModel::hasWhere('user')
                            ->where($where)
                            ->where('withdraw_model.channel', "<>", 30)
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('create_time', 'between', $where_time)
                            ->count('DISTINCT user_id'),
                        // 广告位总额
                        'adAmount' => FinanceProductOrderModel::hasWhere('user')
                            ->where($where)
                            ->where('finance_product_order_model.status', 'in', [
                                FinanceProductOrderRepository::STATUS_PURCHASED,
                                FinanceProductOrderRepository::STATUS_SETTLED
                            ])
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('finance_product_order_model.create_time', 'between', $where_time)
                            ->sum('price'),
                        // 广告位收益
                        'adIncome' => FinanceProductOrderModel::hasWhere('user')
                            ->where($where)
                            ->where('finance_product_order_model.status', 'in', [
                                FinanceProductOrderRepository::STATUS_SETTLED
                            ])
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('finance_product_order_model.create_time', 'between', $where_time)
                            ->sum('profit_money'),
                        // 活跃人数
                        'activePeople' => FinanceProductOrderModel::hasWhere('user')
                            ->where($where)
                            ->where('finance_product_order_model.status', 'in', [
                                FinanceProductOrderRepository::STATUS_PURCHASED,
                                FinanceProductOrderRepository::STATUS_SETTLED
                            ])
                            ->where('finance_product_order_model.level_id', '>', 0)
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('finance_product_order_model.create_time', 'between', $where_time)
                            ->count('DISTINCT user_id'),
                        // AI托管金额
                        'aiAmount' => InvestmentProductOrderModel::hasWhere('user')
                            ->where($where)
                            ->where('investment_product_order_model.status', 'in', [
                                InvestmentProductOrderRepository::STATUS_PURCHASED,
                                InvestmentProductOrderRepository::STATUS_SETTLED
                            ])
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('investment_product_order_model.create_time', 'between', $where_time)
                            ->sum('money'),
                        // AI托管收益金额
                        'aiIncome' => InvestmentProductOrderModel::hasWhere('user')
                            ->where($where)
                            ->where('investment_product_order_model.status', 'in', [
                                InvestmentProductOrderRepository::STATUS_SETTLED
                            ])
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('investment_product_order_model.settlement_time', 'between', $where_time)
                            ->sum('profit_money'),
                        // AI托管人数
                        'aiIncomePeople' => InvestmentProductOrderModel::hasWhere('user')
                            ->where($where)
                            ->where('investment_product_order_model.status', 'in', [
                                InvestmentProductOrderRepository::STATUS_PURCHASED,
                                InvestmentProductOrderRepository::STATUS_SETTLED
                            ])
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('investment_product_order_model.create_time', 'between', $where_time)
                            ->count('DISTINCT user_id'),
                        // 轮盘奖励金额
                        'wheelAmount' => PddTurntableRecordModel::hasWhere('user')
                            ->where($where)
                            ->where('is_receive', 1)
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('pdd_turntable_record_model.update_time', 'between', $where_time)
                            ->sum('amount'),
                        // 轮盘领取人数
                        'wheelPeople' => PddTurntableRecordModel::hasWhere('user')
                            ->where($where)
                            ->where('is_receive', 1)
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('pdd_turntable_record_model.update_time', 'between', $where_time)
                            ->count('DISTINCT user_id'),
                        // 签到人数
//                        'signPeople' => SignRecordModel::hasWhere('user')
//                            ->where($where)
//                            ->whereTime('sign_record_model.create_time', 'between', $where_time)
//                            ->where('user_id', 'in', $user_ids)
//                            ->count('DISTINCT user_id'),
                        // 签到金额
//                        'signAmount' => SignRecordModel::hasWhere('user')
//                            ->where($where)
//                            ->whereTime('sign_record_model.create_time', 'between', $where_time)
//                            ->where('user_id', 'in', $user_ids)
//                            ->sum('award'),
                        // 直推达标返利
//                        'directPushAmount' => MoneyLogModel::hasWhere('user')
//                            ->where($where)
//                            ->where('detail_type', 19)
//                            ->whereTime('money_log_model.create_time', 'between', $where_time)
//                            ->where('user_id', 'in', $user_ids)
//                            ->sum('money'),
                        // 直推达标人数
//                        'directPushPeople' => MoneyLogModel::hasWhere('user')
//                            ->where($where)
//                            ->where('detail_type', 19)
//                            ->whereTime('money_log_model.create_time', 'between', $where_time)
//                            ->where('user_id', 'in', $user_ids)
//                            ->count('DISTINCT user_id'),
                        // 活动直推奖励领取总金额 只展示真实账号今日活动直推奖励领取总金额
                        'ActivityDirectPushAmount' => ActivitynewRecordModel::hasWhere('user')
                            ->where($where)
                            ->where('status', 1)
                            ->whereTime('activitynew_record_model.create_time', 'between', $where_time)
                            ->where('user_id', 'in', $user_ids)
                            ->sum('reward'),
                        // 活动直推奖励领取人数 只展示真实账号今日活动直推奖励领取人数
                        'ActivityDirectPushPeople' => ActivitynewRecordModel::hasWhere('user')
                            ->where($where)
                            ->where('status', 1)
                            ->whereTime('activitynew_record_model.create_time', 'between', $where_time)
                            ->where('user_id', 'in', $user_ids)
                            ->count('DISTINCT user_id'),
                        // 日周月领取金额
//                        'weeklyAmount' => MoneyLogModel::hasWhere('user')
//                            ->where($where)
//                            ->where('detail_type', 15)
//                            ->whereTime('money_log_model.create_time', 'between', $where_time)
//                            ->where('user_id', 'in', $user_ids)
//                            ->sum('money'),
//                        // 日周月领取人数
//                        'weeklyPeople' => MoneyLogModel::hasWhere('user')
//                            ->where($where)
//                            ->where('detail_type', 15)
//                            ->whereTime('money_log_model.create_time', 'between', $where_time)
//                            ->where('user_id', 'in', $user_ids)
//                            ->count('DISTINCT user_id'),
                        // 活跃返佣
                        'teamActivePeople' => MoneyLogModel::hasWhere('user')
                            ->where($where)
                            ->where('detail_type', 'in', [9, 10, 11])
                            ->where('user_id', 'in', $user_ids)
                            ->whereTime('money_log_model.create_time', 'between', $where_time)
                            ->sum('money'),
                        'newRegisterPeople' => MemberModel::where("create_time", 'between', $where_time)
                            ->where("is_xuni", 0)
                            ->where('id', 'in', $user_ids)
                            ->count('id'),
                        // 新增注册人数 只展示当日新增注册的三方充值总人数 (当日注册并三方充值人数）
                        'newRegisterPeopleRecharge' => RechargeModel::hasWhere('user', [['create_time', 'between', $where_time]])
                            ->where($where)
                            ->where('type', 3) // 3 三方充值
                            ->where('recharge_model.status', 2) // 充值成功
                            ->where('user_id', 'in', $user_ids)
                            ->count('DISTINCT member_model.id'),
                        // 新增注册充值  只展示当日新增注册的三方、合约充值总额
                        'newRegisterAmount' => RechargeModel::hasWhere('user', [['create_time', 'between', $where_time]])
                            ->where($where)
                            ->where('type', "<>", 1)
                            ->where('recharge_model.status', 2)
                            ->where('user_id', 'in', $user_ids)
                            ->sum('money'),
                        // 新增注册账户总余额 只展示当日新增有真实充值的账户总余额
                        'newRegisterAccount' => DB::table(RechargeModel::hasWhere('user', [['create_time', 'between', $where_time]])
                                ->where($where)
                                ->where('type', "<>", 1) // 1 手动补单
                                ->where('user_id', 'in', $user_ids)
                                ->where('recharge_model.create_time', 'between', $where_time)
                                ->group('user_id')
                                ->field('member_model.user_money')
                                ->buildSql() . ' a')->sum('user_money'),
                        // 团队余额 只展示当日平台内所有真实账户的总余额（虚拟号不计在内）
                        'teamBalance' => $platformAmount,
                    ];
                }
                $startDate->addDay();
            }


            return json(msg(1, $data, $data ? lang('success') : lang('暂无数据')));
        }
    }
}
