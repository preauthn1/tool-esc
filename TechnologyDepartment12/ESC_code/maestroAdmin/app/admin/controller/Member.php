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
use app\admin\model\RechargeModel;
use app\admin\model\UserModel as AdminModel;
use app\admin\model\WithdrawModel;
use app\admin\transform\EconnoisseurTransform;
use app\admin\transform\UserIncomeTransform;
use app\common\model\AdPlanModel;
use app\common\model\AdPlanUserModel;
use app\common\model\BankModel;
use app\common\model\ConfigModel;
use app\common\model\FinancePlanModel;
use app\common\model\FinanceProductOrderModel;
use app\common\model\HomeUserAdBufferCountdownConfigModel;
use app\common\model\HomeUserVirtualLogModel;
use app\common\model\HomeUserVirtualModel;
use app\common\model\InvestmentProductOrderModel;
use app\common\model\MemberLevelModel;
use app\common\model\MemberLogModel;
use app\common\model\MoneyLogModel;
use app\common\model\PaymentPlatformModel;
use app\common\model\UserModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\HomeUserRepository;
use app\common\repository\InvestmentProductOrderRepository;
use app\common\repository\LevelRepository;
use app\common\repository\MoneyLogRepository;
use app\common\repository\TaskSessionRepository;
use app\common\repository\WithdrawalRepository;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Exception;
use Firebase\JWT\JWT;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use think\facade\View;
use think\Model;
use think\response\Json;

class Member extends Base
{

    public function beforeMatchValidation($user_id, $level_id): bool
    {
        $level_data = (new LevelRepository())->getLevelByLevelID($level_id);

        $now = date('H:i:s');
        $task_session_list = (new TaskSessionRepository())->getTaskSessionList($level_data['type']);
        $task_session = null;
        foreach ($task_session_list as $task_session_) {
            if ($task_session_['start_time'] <= $now && $task_session_['end_time'] >= $now) {
                $task_session = $task_session_;
                break;
            }
        }
        if ($task_session == null) {
            return false;
        }
        $orders = (new FinanceProductOrderRepository())->selectWhere([
            ['user_id', '=', $user_id],
            [
                'create_time', 'between', [
                Carbon::parse($task_session->start_time)->timestamp,
                Carbon::parse($task_session->end_time)->timestamp
            ]
            ]
        ]);
        if ($orders->isEmpty()) {
            return true;
        }
        $result = true;
        foreach ($orders as $order) {
            if ($level_data['type'] != (new LevelRepository())->getLevelByLevelID($order['level_id'])['type']) {
                $result = false;
                break;
            }
        }
        return $result;
    }

    public function test(){
        $user_id = $this->request->param("user_id/d", 0);
        $level_id = $this->request->param('level_id/d', 0);
        if (!$this->beforeMatchValidation($user_id, $level_id)) {
            return json(msg(0, ['code' => -10020, 'msg' => lang('同时段不能购买不通类型的广告位')], lang('error')));
        } else{
            return json(msg(0, ['code' => 10020, 'msg' => lang('可以购买广告位')], lang('success')));
        }
    }

    // 用户列表
    public function index()
    {
        $id = $this->request->param("id/d", 0);
        $level = $this->request->param('level/d', 99);
        $level_id = $this->request->param('level_id', '');
        if (request()->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $is_xuni = $param['is_xuni'];
            $time_type = intval($param['time_type']);
            $where[] = ['is_demo', '=', 0];
            if (!empty($param['uid'])) {
                $where[] = ['id', '=', $param['uid']];
            }
            if (!empty($param['user_name'])) {
                $where[] = ['user_name', 'like', trim($param['user_name']) . '%'];
            }
            if (!empty($param['invite_code'])) {
                $where[] = ['invite_code', 'like', $param['invite_code'] . '%'];
            }
            if (!empty($param['document_id'])) {
                $where[] = ['document_id', 'like', $param['document_id'] . '%'];
            }
            if (!empty($param['true_name'])) {
                $where[] = ['true_name', 'like', $param['true_name'] . '%'];
            }
            if (array_key_exists('level_id', $param) && $param['level_id'] != '') {
                $where[] = ['level_id', '=', $param['level_id']];
            }
            if (!empty($param['ip'])) {
                $where[] = ['last_login_ip|ip', '=', $param['ip']];
            }
            if (!empty($param['leader1_invite_code'])) {
                // 通过邀请码查询上级id
                $where[] = ['leader1', '=', UserModel::where('invite_code', $param['leader1_invite_code'])->value('id')];
            }
            if (!empty($param['leader1'])) {
                $where[] = ['tree_id', 'like', "%/{$param['leader1']}/%"];
                $where[] = ['tree_id', 'not like', "%/{$param['leader1']}/"];
//                $where[] = ['tree_id'] = [
//                    ['like', "%/{$param['leader1']}/%"],
//                    ['not like', '%/' . $param['leader1'] . '/'],
//                ];
            }
            if ($id > 0 && ($level >= 1 && $level <= 7)) {
                $where[] = ["leader{$level}", '=', $id];
            } elseif ($id > 0 && $level == 99) {
                $where[] = ['tree_id', 'like', "%/{$id}/%"];
                $where[] = ['tree_id', 'not like', "%/{$id}/"];
//                $where[] = ['tree_id', 'like', "%/{$id}/%"];
//                $where[] = ['tree_id', 'not like', '%/' . $id . '/'];
            }
            if (in_array($is_xuni, [0, 1])) {
                $where[] = ['is_xuni', '=', $is_xuni];
            } elseif ($is_xuni == 2) { //未登录过的用户
                $where[] = ['last_login_time', '=', 0];
            } elseif ($is_xuni == 3) { //未绑定银行卡的用户
                $where[] = ['bank_number', '=', ''];
            } elseif ($is_xuni == 4) { //已绑定银行卡的用户
                $where[] = ['bank_number', "<>", ""];
            } elseif ($is_xuni == 5) { //禁广告
                $where[] = ['do_task_status', '=', 0];
            }
            $orderby = ['id' => 'DESC'];
            if (!empty($param['sortName']) && in_array($param['sortName'],
                    ['last_login_time', 'user_money', 'level_id'])) {
                $orderby = [$param['sortName'] => ($param['sortOrder'] == "asc" ? "ASC" : "DESC")];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time = strtotime($param['endTime'] . " 23:59:59");
                if ($time_type == 1) {
                    $where[] = ['last_login_time', 'BETWEEN', [$start_time, $end_time]];
                } else {
                    $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
                }
            }

            $user = new MemberModel();
            $selectResult = $user->getUsersByWhere($where, $offset, $limit, $orderby)->toArray();
            // 获取所有的id
            $ids = array_column($selectResult, 'id');
            $withdraws = Db::name('withdrawals')
                ->where('user_id', 'in', implode(',', $ids))
                ->where('status', 4)
                ->group('user_id')
                ->column('sum(original_money) sum_original_money', 'user_id');
            $level_name = LevelModel::column("level_name", 'level_id');
            $money_repository = new MoneyLogRepository();
            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                // 在线状态
                $online_status = time() - $vo['last_login_time'] < 300;
                $selectResult[$key]['online_status'] = $online_status;
                $selectResult[$key]['is_xuni_text'] = $vo['is_xuni'] == 1 ? '<span style="color:red">是</span>' : '否';
                $selectResult[$key]['last_login_time'] = $vo['last_login_time'] > 0 ? i18n_datetime2str($vo['last_login_time']) : '-';
                $selectResult[$key]['level_name'] = $level_name[$vo['level_id']] ?? '非会员';

                $selectResult[$key]['team_count'] = UserModel::where('tree_id', 'like', "%/{$vo['id']}/%")->where('tree_id', 'not like', '%/' . $vo['id'] . '/')->count();

                $vo['leader1_user_name'] = '-';
                $vo['leader2_user_name'] = '-';
                $vo['leader3_user_name'] = '-';

                if (isset($vo['leader1_info']['user_name'])) {
                    $vo['leader1_user_name'] = $vo['leader1_info']['user_name'];
                }
                if (isset($vo['leader2_info']['user_name'])) {
                    $vo['leader2_user_name'] = $vo['leader2_info']['user_name'];
                }
                if (isset($vo['leader3_info']['user_name'])) {
                    $vo['leader3_user_name'] = $vo['leader3_info']['user_name'];
                }
                $selectResult[$key]['leader1'] = $vo['leader1'] ?: "-";
                $selectResult[$key]['leader2'] = $vo['leader2'] ?: "-";
                $selectResult[$key]['leader3'] = $vo['leader3'] ?: "-";

                $selectResult[$key]['leader1_user_name'] = $vo['leader1_user_name'] ?: "-";
                $selectResult[$key]['leader2_user_name'] = $vo['leader2_user_name'] ?: "-";
                $selectResult[$key]['leader3_user_name'] = $vo['leader3_user_name'] ?: "-";

                $selectResult[$key]['top_leader'] = UserModel::getLeaderByTree($vo['tree_id'], 1);
                $selectResult[$key]['second_leader_info'] = UserModel::getLeaderByTree($vo['tree_id'], 2);
                $ip_count = isset($vo['user_ip']) ? count($vo['user_ip']) : 0;
                if ($ip_count > 0) {
                    $ip_count -= 1;
                }
                $selectResult[$key]['ip_count'] = $ip_count;

                $reg_ip = "";
                if ($vo['ip'] && $vo['ip'] != '0.0.0.0') {
                    $reg_ip .= $vo['ip'];
                    $reg_ip .= '(' . ip2location($vo['ip']) . ')';
                }
                $login_ip = '';
                if ($vo['last_login_ip']) {
                    $login_ip .= $vo['last_login_ip'];
                    $login_ip .= '(' . ip2location($vo['last_login_ip']) . ')';
                }
                $selectResult[$key]['withdraw'] = 0.00;
                if (isset($withdraws[$vo['id']])) {
                    $selectResult[$key]['withdraw'] = i18n_sprintf($withdraws[$vo['id']]);
                }
                $selectResult[$key]['recharge_money'] = i18n_sprintf($vo['recharge_money']);
                $selectResult[$key]['withdrawal_amount'] = i18n_sprintf($vo['withdrawal_amount']);
//                $selectResult[$key]['brokerage_money'] = i18n_sprintf($vo['brokerage_money']);
//                $selectResult[$key]['tyj_money'] = i18n_sprintf($vo['tyj_money']);
                $selectResult[$key]['user_money'] = i18n_sprintf($vo['user_money']);
                $selectResult[$key]['reg_ip'] = $reg_ip;
                $selectResult[$key]['login_ip'] = $login_ip;
                $selectResult[$key]['plan_name'] = '';
                $ad_plan_name = AdPlanModel::getPlanName($vo['ad_plan_id']);
                $selectResult[$key]['ad_plan_name'] = $ad_plan_name ? $ad_plan_name . '-第' . AdPlanModel::getDayCount($vo['id'], $vo['ad_plan_id']) . '天' : '';

                // 获取用户vip信息
//                $user_level = MemberLevelModel::where('user_id', $vo['id'])->where('level_end_time', '>', time())->find();
//                if ($user_level) {
//                    $selectResult[$key]['level_star_time'] = $user_level->create_time;
//                    $selectResult[$key]['level_end_time'] = date('Y-m-d H:i:s', $user_level->level_end_time);
//                } else {
//                    $selectResult[$key]['level_star_time'] = '';
//                    $selectResult[$key]['level_end_time'] = '';
//                }
                // 最后广告位购买时间
                $last_finance_product_buy_time = FinanceProductOrderModel::where('user_id', $vo['id'])->where('buy_time', '>', 0)->order('id DESC')->value('buy_time');
                $selectResult[$key]['last_finance_product_buy_time'] = $last_finance_product_buy_time ? i18n_datetime2str($last_finance_product_buy_time) : '';


                // 最后广告位结束时间
                $last_finance_product_end_time = FinanceProductOrderModel::where('user_id', $vo['id'])->where('end_time', '>', 0)->order('id DESC')->value('end_time');
                $selectResult[$key]['last_finance_product_end_time'] = $last_finance_product_end_time ? i18n_datetime2str($last_finance_product_end_time) : '';

                // 所在层级

                preg_match_all("/\d+/", $vo['tree_id'], $matches);
                $count = count($matches[0]);
                $selectResult[$key]['tree_level'] = $count;

                $selectResult[$key]['lang'] = $vo['lang'] ?? '- -';

                $finance_product_price = $investment_product_money = 0;
                // 获取广告位待结算金额和AI智能托管待结算金额
                $finance_product_price = FinanceProductOrderModel::hasWhere('user')
                    ->where('finance_product_order_model.status', 'in', [
                        FinanceProductOrderRepository::STATUS_PURCHASED,
                    ])
                    ->where('user_id', $vo['id'])
                    ->sum('price');

                $investment_product_money = InvestmentProductOrderModel::hasWhere('user')
                    ->where('investment_product_order_model.status', 'in', [
                        InvestmentProductOrderRepository::STATUS_PURCHASED,
                    ])
                    ->where('user_id', $vo['id'])
                    ->sum('money');

                $selectResult[$key]['product_frozen_money'] = $finance_product_price + $investment_product_money;
                $selectResult[$key]['frozen_money'] = i18n_sprintf($vo['frozen_money']);
                $user_latest_log = MemberLogModel::getUserLatestLog($vo['id']);
                $selectResult[$key]['device_uuid'] = $user_latest_log['uuid'];
                $selectResult[$key]['today_finance_product_count'] = (new FinanceProductOrderRepository)->todayUserFinanceProductCount($vo['id']);
                $selectResult[$key]['receive_from_other_user'] = i18n_sprintf($money_repository->getTotalMoney([$vo['id']], [23])); // 23-收到其他用户转账

                // 获取注册时间
                $register_days = floor(Carbon::parse($vo['create_time'])->diffInDays());
                // 获取累计交易天数
                $selectResult[$key]['finance_product_order_day'] = FinanceProductOrderModel::where('user_id', $vo['id'])
                    ->where('status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
                    ->field('COUNT(DISTINCT DATE(FROM_UNIXTIME(create_time))) as order_count')->select()[0]['order_count'];
                if ($last_finance_product_buy_time) {
                    $days = intval(Carbon::now()->diffInDays(Carbon::parse($last_finance_product_buy_time)));
                    $selectResult[$key]['finance_product_no_order_day'] = abs($days);
                } else {
                    $selectResult[$key]['finance_product_no_order_day'] = $register_days;//没有交易则为注册天数
                }

//
                $selectResult[$key]['register_days'] = $register_days;

            }
            $return['total'] = $user->getAllUsers($where);  //总数据
            $return['rows'] = $selectResult;
            return json($return);
        }
        $user = new MemberModel();
        $info = $user->getOneUser($id);
//        $levelModel = new LevelModel();
//        $levelList = $levelModel->getLevel();
//        $levelListArr = [];
//        $levelListArr[] = [
//            'level_name' => '全部',
//            'level_id' => '',
//            'num' => $user->count()
//        ];
//        foreach ($levelList as $value) {
//            $value['num'] = $user->where(['level_id' => $value['level_id']])->count();
//            $levelListArr[] = $value;
//        }
        $agent = MemberModel::where('is_proxy', 1)->select();
        View::assign('level_id', $level_id);
        View::assign('id', $id);
        View::assign('info', $info);
        View::assign('agent', $agent);
//        View::assign('levelAll', $levelListArr);
        View::assign('export_url', url('export', ['id' => $id, 'level' => $level])->build());
        return View::fetch();
    }


    // 用户列表
    public function next_index()
    {
        $id = $this->request->param("id/d", 0);
        $level = $this->request->param('level/d', 99);
        if (request()->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $user_type = $param['user_type'];
            $where[] = ['is_demo', '=', 0];
            $where[] = ['id', '<>', $id];
            if (!empty($param['uid'])) {
                $where[] = ['id', '=', $param['uid']];
            }
            if (!empty($param['user_name'])) {
                $where[] = ['user_name', 'like', '%' . $param['user_name'] . '%'];
            }
            if (!empty($param['invite_code'])) {
                $where[] = ['invite_code', 'like', '%' . $param['invite_code'] . '%'];
            }
            $team_ids = [];
            if ($id > 0 && ($level >= 1 && $level <= 7)) {
                $where[] = ["leader{$level}", '=', $id];
            } elseif ($id > 0 && $level == 99) {
                $team_ids = UserModel::where('tree_id', 'like', "%/{$id}/%")->where('tree_id', 'not like', '%/' . $id . '/')->column('id');
                $where[] = ['id', 'in', $team_ids];
//                $where[] = ['tree_id', 'like', "%/{$id}/%"];
//                $where[] = ['tree_id'] = ['not like', '%/'.$id. '/'];
            }
            if ($user_type == 1) { //未登录过的用户
                $where[] = ['last_login_time', '=', 0];
            } elseif ($user_type == 2) { //未绑定银行卡的用户
                $where[] = ['bank_number', '=', ''];
            } elseif ($user_type == 3) { //已绑定银行卡的用户
                $where[] = ['bank_number', "<>", ""];
            }
            $orderby = ['id' => 'DESC'];
            if (!empty($param['sortName']) && in_array($param['sortName'],
                    ['last_login_time', 'user_money', 'level_id'])) {
                $orderby = $param['sortName'] . " " . ($param['sortOrder'] == "asc" ? "ASC" : "DESC");
            }
            $order_date = [];
            if (!empty($param['startDate']) && !empty($param['endDate'])) {
                $start_time = strtotime($param['startDate'] . " 00:00:00");
                $end_time = strtotime($param['endDate'] . " 23:59:59");
                $order_date['create_time'] = ['BETWEEN', [$start_time, $end_time]];
            }
            $user = new MemberModel();
            $selectResult = $user->getUsersByWhere($where, $offset, $limit, $orderby)->toArray();
            $level_name = LevelModel::column("level_name", 'level_id');

            //得到所有用户的体现总和
            $ids = array_column($selectResult, 'id');
            $withdraws = Db::name('withdrawals')
                ->where('user_id', 'in', implode(',', $ids))
                ->where('status', 4)
                ->group('user_id')
                ->column('sum(original_money) sum_original_money', 'user_id');

            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['is_xuni_text'] = $vo['is_xuni'] == 1 ? '<span style="color:red">是</span>' : '否';
                $selectResult[$key]['last_login_time'] = $vo['last_login_time'] > 0 ? i18n_datetime2str($vo['last_login_time']) : '-';
                $selectResult[$key]['level_id'] = isset($level_name[$vo['level_id']]) ? $level_name[$vo['level_id']] : '非会员';

                $leader1_user_name = $user->getOneUser($vo['leader1'], "user_name");
                $selectResult[$key]['leader1_user_name'] = empty($leader1_user_name) ? "-" : $leader1_user_name;
                $selectResult[$key]['leader1'] = empty($vo['leader1']) ? "-" : $vo['leader1'];

                $leader2_user_name = $user->getOneUser($vo['leader2'], "user_name");
                $selectResult[$key]['leader2_user_name'] = empty($leader2_user_name) ? "-" : $leader2_user_name;
                $selectResult[$key]['leader2'] = empty($vo['leader2']) ? "-" : $vo['leader2'];

                $leader3_user_name = $user->getOneUser($vo['leader3'], "user_name");
                $selectResult[$key]['leader3_user_name'] = empty($leader3_user_name) ? "-" : $leader3_user_name;
                $selectResult[$key]['leader3'] = empty($vo['leader3']) ? "-" : $vo['leader3'];

                $selectResult[$key]['withdraw'] = 0.00;
                if (isset($withdraws[$vo['id']])) {
                    $selectResult[$key]['withdraw'] = i18n_sprintf($withdraws[$vo['id']]);
                }
                $selectResult[$key]['recharge_money'] = i18n_sprintf($vo['recharge_money']);
                $selectResult[$key]['brokerage_money'] = i18n_sprintf($vo['brokerage_money']);
                $selectResult[$key]['tyj_money'] = i18n_sprintf($vo['tyj_money']);


                $reg_ip = "";
                if ($vo['ip'] && $vo['ip'] != '0.0.0.0') {
                    $reg_ip .= $vo['ip'];
                    $reg_ip .= '(' . ip2location($vo['ip']) . ')';
                }
                $login_ip = '';
                if ($vo['last_login_ip']) {
                    $login_ip .= $vo['last_login_ip'];
                    $login_ip .= '(' . ip2location($vo['last_login_ip']) . ')';
                }

                $selectResult[$key]['user_money'] = i18n_sprintf($vo['user_money']);
                $selectResult[$key]['reg_ip'] = $reg_ip;
                $selectResult[$key]['login_ip'] = $login_ip;
                $selectResult[$key]['plan_name'] = FinancePlanModel::getPlanName($vo['finance_plan_id']);
                // 在线状态
                $online_status = time() - strtotime($vo['last_login_time']) < 300;
                $selectResult[$key]['online_status'] = $online_status;

                // 获取用户vip信息
//                $user_level = MemberLevelModel::where('user_id', $vo['id'])->where('level_end_time', '>', time())->find();
//                if ($user_level) {
//                    $selectResult[$key]['level_star_time'] = $user_level->create_time;
//                    $selectResult[$key]['level_end_time'] = date('Y-m-d H:i:s', $user_level->level_end_time);
//                } else {
//                    $selectResult[$key]['level_star_time'] = '';
//                    $selectResult[$key]['level_end_time'] = '';
//                }
                // 最后广告位购买时间
                $last_finance_product_buy_time = FinanceProductOrderModel::where('user_id', $vo['id'])->where('buy_time', '>', 0)->order('id DESC')->value('buy_time');
                $selectResult[$key]['last_finance_product_buy_time'] = $last_finance_product_buy_time ? i18n_datetime2str($last_finance_product_buy_time) : '';
                // 最后广告位结束时间
                $last_finance_product_end_time = FinanceProductOrderModel::where('user_id', $vo['id'])->where('end_time', '>', 0)->order('id DESC')->value('end_time');
                $selectResult[$key]['last_finance_product_end_time'] = $last_finance_product_end_time ? i18n_datetime2str($last_finance_product_end_time) : '';


                // 所在层级

                preg_match_all("/\d+/", $vo['tree_id'], $matches);
                $count = count($matches[0]);
                $selectResult[$key]['tree_level'] = $count;

                $selectResult[$key]['lang'] = $vo['lang'] ?? '- -';

                $finance_product_price = $investment_product_money = 0;
                // 获取广告位待结算金额和AI智能托管待结算金额
                $finance_product_price = FinanceProductOrderModel::hasWhere('user')
                    ->where('finance_product_order_model.status', 'in', [
                        FinanceProductOrderRepository::STATUS_PURCHASED,
                    ])
                    ->where('user_id', $vo['id'])
                    ->sum('price');

                $investment_product_money = InvestmentProductOrderModel::hasWhere('user')
                    ->where('investment_product_order_model.status', 'in', [
                        InvestmentProductOrderRepository::STATUS_PURCHASED,
                    ])
                    ->where('user_id', $vo['id'])
                    ->sum('money');

                $selectResult[$key]['product_frozen_money'] = $finance_product_price + $investment_product_money;
            }
            $return['total'] = $user->getAllUsers($where);  //总数据
            $return['rows'] = $selectResult;

            $stat = [
                'finance_product_order_amount' => FinanceProductOrderModel::where('user_id', 'in', $team_ids)
                    ->where('status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
                    ->where($order_date)
                    ->sum('price'),
                'withdrawal_sum' => WithdrawModel::where('user_id', 'in', $team_ids)
                    ->where('status', WithdrawalRepository::STATUS_SUCCESS)
//                    ->where($order_date)
                    ->sum('original_money'),
                'recharge_sum' => RechargeModel::where('user_id', 'in', $team_ids)
                    ->where('status', RechargeModel::STATUS_SUCCESS)
//                    ->where($order_date)
                    ->sum('money'),
            ];
            $return['stat'] = $stat;
            return json($return);
        }
        $user = new MemberModel();
        $info = $user->getOneUser($id);

        if ($level == 99) {
            $team_ids = UserModel::where('tree_id', 'like', "%/{$id}/%")
                ->where('tree_id', 'not like', '%/' . $id . '/')
                ->column('id');
        } else {
            $team_ids = UserModel::getTeamIds($id, $level);
        }
        $team_count = count($team_ids);
        $level_team_count = [];
        // 循环7级，获取级别总人数
        for ($i = 0; $i <= 7; $i++) {
            if ($i == 0) {
                $team_child_ids = UserModel::where('tree_id', 'like', "%/{$id}/%")
                    ->where('tree_id', 'not like', '%/' . $id . '/')
                    ->column('id');
            } else {
                $team_child_ids = UserModel::getTeamIds($id, $i);
            }
            $level_team_count[$i] = count($team_child_ids);
        }
        // ==================================    汇总   =========================================

        $home_user_repository = new HomeUserRepository();
        { // 汇总
            // 下级总数
            $team_count = count($team_ids);
            // 三方充值总额 已经更改为 实际充值总额 （三方充值+合约+线下）
            $third_recharge_success_money = i18n_sprintf($home_user_repository->getTeamThirdRechargeSuccessAmountAll($team_ids));
            // 三方提现总额
            $third_withdrawal_success_money = i18n_sprintf($home_user_repository->getTeamThirdWithdrawalSuccessAmount($team_ids));

            // 领取的总佣金（已领取的佣金池+推广奖励）
            $total_task_rebate = UserModel::getTeamTaskRebate($team_ids); // 领取的总佣金
        }

        { // 充值
            //当日充值总额
            $today_recharge_amount = i18n_sprintf($home_user_repository->getTeamTodayRechargeAmount($team_ids));
            // 当日充值总人数
            $today_recharge_count = $home_user_repository->getTeamTodayRechargeCount($team_ids);
            // 当日复充人数
            $today_repeat_recharge_count = $home_user_repository->getTeamTodayRepeatRechargeRechargeCount($team_ids);
            //当日基础金额
            $today_team_money = i18n_sprintf($home_user_repository->getTeamUserMoney($team_ids));
            // 当日首充金额 当日第一次充值（包含之前注册的账号）
            $today_first_recharge_money = i18n_sprintf($home_user_repository->getTeamTodayFirstRechargeMoney($team_ids));
            // 当日首充人数 这边指的是当日注册当日充值的账号
            $today_register_and_recharge_count = $home_user_repository->getTeamTodayRegisterAndFirstRechargeCount($team_ids);

        }

        { // 提现
            // 当日提现总额
            $today_withdrawal_money = i18n_sprintf($home_user_repository->getTeamTodayWithdrawalMoney($team_ids));
            // 当日提现人数
            $today_withdrawal_count = $home_user_repository->getTeamTodayWithdrawalCount($team_ids);
            // 当日已出款金额 当日三方已经出款的金额
            $today_third_withdrawal_success_money = i18n_sprintf($home_user_repository->getTeamTodayThirdWithdrawalMoney($team_ids));
        }

        { // 团队数据
            // 今日新增注册量
            $today_register_count = $home_user_repository->getTeamTodayRegisterCount($team_ids);
            // 团队活跃人数（当天） 活跃人数指的是有冻结资金的账号，包含广告库存、存AI有冻结资金的账号
            $active_user_count = $home_user_repository->getTeamActiveCount($team_ids);
            // 做任务人数（当天） 做任务的人数指的是当天购买广告的人数
            $today_task_user_count = $home_user_repository->getTeamTodayTaskUserCount($team_ids);
            // 当日领取的佣金（已领取的佣金池+推广奖励）
            $today_task_rebate = UserModel::getTeamTodayTaskRebate($team_ids);

            // 当日团队总余额 基础资金加上冻结金额
            $freeze_money = $home_user_repository->getTeamFreezeMoney($team_ids);
            $team_amount = i18n_sprintf($freeze_money + $home_user_repository->getTeamUserMoney($team_ids));
        }


        View::assign('team_count', $team_count);
        View::assign('third_recharge_success_money', $third_recharge_success_money);
        View::assign('third_withdrawal_success_money', $third_withdrawal_success_money);
        View::assign('total_task_rebate', $total_task_rebate);


        View::assign('today_recharge_amount', $today_recharge_amount);
        View::assign('today_recharge_count', $today_recharge_count);
        View::assign('today_repeat_recharge_count', $today_repeat_recharge_count);
        View::assign('today_team_money', $today_team_money);
        View::assign('today_first_recharge_money', $today_first_recharge_money);
        View::assign('today_register_and_recharge_count', $today_register_and_recharge_count);


        View::assign('today_withdrawal_money', $today_withdrawal_money);
        View::assign('today_withdrawal_count', $today_withdrawal_count);
        View::assign('today_third_withdrawal_success_money', $today_third_withdrawal_success_money);

        View::assign('today_register_count', $today_register_count);
        View::assign('active_user_count', $active_user_count);
        View::assign('today_task_user_count', $today_task_user_count);
        View::assign('today_task_rebate', $today_task_rebate);
        View::assign('freeze_money', $freeze_money);
        View::assign('team_amount', $team_amount);
        // ==================================    汇总   =========================================


        $recharge_count = 0;
        $recharge_money = 0;
        $withdraw_count = 0;
        $withdraw_money = 0;
        if ($team_ids) {
            $recharge_count = UserModel::getTeamRechargeCount($team_ids);
            $recharge_money = UserModel::getTeamRecharge($team_ids, false);
            $withdraw_count = UserModel::getTeamWithdrawalsCount($team_ids);
            $withdraw_money = UserModel::getTeamWithdrawals($team_ids, false);
        }
        View::assign('team_count', $team_count);
        View::assign('level_team_count', $level_team_count);
        View::assign('recharge_count', $recharge_count);
        View::assign('recharge_money', $recharge_money);
        View::assign('withdraw_count', $withdraw_count);
        View::assign('withdraw_money', $withdraw_money);
        View::assign('level', $level);
        View::assign('id', $id);
        View::assign('info', $info);
        View::assign('url', url('next_index', ['id' => $id, 'level' => $level]));
        View::assign('export_url', url('export', ['id' => $id, 'level' => $level]));
        return View::fetch();
    }
    /**
     * 导出文件
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function export_file()
    {
        header('X-Accel-Buffering: no');
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        $limit = 200; // 每次处理200条记录
        $id = $this->request->param("id/d", 0);
        $level = $this->request->param('level/d', 1);
        $param = input('param.');
        $time_type = $this->request->param('time_type/d', 0);
        $is_xuni = $this->request->param('is_xuni/d', 0);

        $where[] = ['is_demo', '=', 0];
        if (!empty($param['uid'])) {
            $where[] = ['id', '=', $param['uid']];
        }
        if (!empty($param['user_name'])) {
            $where[] = ['user_name', 'like', '%' . $param['user_name'] . '%'];
        }
        if (!empty($param['invite_code'])) {
            $where[] = ['invite_code', 'like', '%' . $param['invite_code'] . '%'];
        }
        if (isset($param['level_id']) && $param['level_id'] != '') {
            $where[] = ['level_id', '=', $param['level_id']];
        }
        if (!empty($param['ip'])) {
            $where[] = ['last_login_ip|ip', '=', $param['ip']];
        }
        if (!empty($param['leader1'])) {
            $where[] = ['leader1|leader2|leader3|leader4|leader5|leader6|leader7', '=', $param['leader1']];
        }
        if ($id > 0 && ($level >= 1 && $level <= 7)) {
            $where[] = ["leader{$level}", '=', $id];
        } elseif ($id > 0 && $level == 99) {
            $where[] = ['tree_id', 'like', "%/{$id}/%"];
        }
        if (in_array($is_xuni, [0, 1])) {
            $where[] = ['is_xuni', '=', $is_xuni];
        } elseif ($is_xuni == 2) { //未登录过的用户
            $where[] = ['last_login_time', '=', 0];
        } elseif ($is_xuni == 3) { //未绑定银行卡的用户
            $where[] = ['bank_number', '=', ''];
        } elseif ($is_xuni == 4) { //已绑定银行卡的用户
            $where[] = ['bank_number', "<>", ""];
        }
        $orderby = ['id' => 'DESC'];
        if (!empty($param['sortName']) && in_array($param['sortName'], ['last_login_time', 'user_money', 'level_id'])) {
            $orderby = [$param['sortName'] => ($param['sortOrder'] == "asc" ? "ASC" : "DESC")];
        }
        if (!empty($param['startTime']) && !empty($param['endTime'])) {
            $start_time = strtotime($param['startTime'] . " 00:00:00");
            $end_time = strtotime($param['endTime'] . " 23:59:59");
            if ($time_type == 1) {
                $where[] = ['last_login_time', 'BETWEEN', [$start_time, $end_time]];
            } else {
                $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
            }
        }
//        $session_id = session('file_id');
        $session_id = session('id');
        if(empty($session_id)){
            $session_id = uniqid();
            session('file_id', $session_id);
        }
        $cache_key = md5(json_encode($where) . "member". $session_id);
        $cache_file = Cache::store('file');

        // 获取总记录数
        $user = new MemberModel();
        $total = $user->getAllUsers($where);

        $status = config('app.user_status');
        $level_name = LevelModel::column("level_name", 'level_id');
        $money_repository = new MoneyLogRepository();

        $header = [
            'top_leader_name' => '总代理名字',
            'id' => '用户ID',
            'status' => '用户状态',
            'tree_level' => '所在层级',
            'credit_score' => '信用分',
            'second_leader_id' => '初级ID',
            'leader1' => '上级ID',
            'team_count' => '下级人数',
            'true_name' => '姓名',
            'mobile' => '手机号',
            'email' => '邮箱',
            'document_id' => '账户账号',
            'document_type' => '账户类型',
            'user_money' => '余额',
            'recharge' => '充值',
            'withdraw' => '提现',
            'product_frozen_money' => '冻结资金',
            'receive_from_other_user' => '上级划转金额',
            'wcount' => '历史提现次数',
            'create_time' => '注册时间',
            'last_finance_product_buy_time' => '最后购买时间',
            'finance_product_order_day' => '累计交易天数',
            'finance_product_no_order_day' => '累计未交易天数',
        ];

        $save_path = public_path() . 'storage/export/user/' . date('Ymd') . '/';
        if (!is_dir(public_path() . 'storage/export/user/')) {
            mkdir(public_path() . 'storage/export/user/', 0777, true);
        }
        if (!is_dir($save_path)) {
            mkdir($save_path, 0777, true);
        }

        // 获取缓存信息
        $file_name = $cache_file->get($cache_key . "_name");
        $current_page = $cache_file->get($cache_key . "_page", 1);
        $processed_count = $cache_file->get($cache_key . "_offset", 0);

        // 如果没有缓存文件名，说明是新任务
        if (empty($file_name)) {
            $file_name = "用户列表_" . genOrderSN() . ".xlsx";
            $cache_file->set($cache_key . "_name", $file_name, 600);
            $current_page = 1;
            $processed_count = 0;
        }

        $full_file_path = $save_path . $file_name;

        // 创建或加载Excel
        if (file_exists($full_file_path) && $current_page > 1) {
            // 如果文件已存在且不是第一页，加载现有文件
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($full_file_path);
            $sheet = $spreadsheet->getActiveSheet();
        } else {
            // 创建新的Excel文件
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // 写入表头（仅在创建新文件时写入）
            $col = 1;
            foreach ($header as $key => $value) {
                $cellAddress = Coordinate::stringFromColumnIndex($col) . '1';
                $sheet->setCellValue($cellAddress, $value);
                $col++;
            }
        }

        do {
            // 分页获取数据
            $list = $user->getUsersByWhere($where, ($current_page - 1) * $limit, $limit, $orderby)->toArray();
            if (empty($list)) {
                break;
            }
            // 获取相关数据
            $ids = array_column($list, 'id');
            $withdrawals = Db::name('withdrawals')
                ->where("user_id", "in", $ids)
                ->where("status", 4)
                ->group("user_id")
                ->column("sum(original_money)", "user_id");

            // 处理数据并写入Excel
            foreach ($list as $key => $vo) {
                $list[$key]['is_xuni'] = lang($vo['is_xuni'] == 1 ? '是' : '否');
                $list[$key]['last_login_time'] = date('Y-m-d H:i:s', $vo['last_login_time']);
                $list[$key]['status'] = $status[$vo['status']] ?? '';
                if ($vo['delete_time']) {
                    $list[$key]['status'] = lang('已删除');
                }
                $list[$key]['recharge'] = i18n_sprintf($vo['recharge_money']);
                $list[$key]['withdraw'] = isset($withdrawals[$vo['id']]) ? intval($withdrawals[$vo['id']]) : 0;
                $list[$key]['user_money'] = i18n_sprintf($vo['user_money']);
                $list[$key]['level_id'] = isset($level_name[$vo['level_id']]) ? $level_name[$vo['level_id']] : '非会员';
                $top_leader = UserModel::getLeaderByTree($vo['tree_id'], 1);
                $list[$key]['top_leader_name'] = $top_leader ? $top_leader['remark'] : '无';
                $second_leader = UserModel::getLeaderByTree($vo['tree_id'], 2);
                $list[$key]['second_leader_id'] = $second_leader ? $second_leader['id'] : '无';
                $list[$key]['email'] = $vo['mobile'] ? '' : $vo['user_name'];
                preg_match_all("/\d+/", $vo['tree_id'], $matches);
                $count = count($matches[0]);
                $list[$key]['tree_level'] = $count;

                // 获取广告位待结算金额和AI智能托管待结算金额
                $finance_product_price = FinanceProductOrderModel::hasWhere('user')
                    ->where('finance_product_order_model.status', 'in', [
                        FinanceProductOrderRepository::STATUS_PURCHASED,
                    ])
                    ->where('user_id', $vo['id'])
                    ->sum('price');

                $investment_product_money = InvestmentProductOrderModel::hasWhere('user')
                    ->where('investment_product_order_model.status', 'in', [
                        InvestmentProductOrderRepository::STATUS_PURCHASED,
                    ])
                    ->where('user_id', $vo['id'])
                    ->sum('money');

                $list[$key]['product_frozen_money'] = $finance_product_price + $investment_product_money;
                $list[$key]['wcount'] = WithdrawModel::where('user_id', $vo['id'])->count();
                $list[$key]['receive_from_other_user'] = i18n_sprintf($money_repository->getTotalMoney([$vo['id']], [23])); // 23-收到其他用户转账

                //最后购买时间
                $last_finance_product_buy_time = FinanceProductOrderModel::where('user_id', $vo['id'])->where('buy_time', '>', 0)->order('id DESC')->value('buy_time');
                $list[$key]['last_finance_product_buy_time'] = $last_finance_product_buy_time ? i18n_datetime2str($last_finance_product_buy_time) : '';
                // 获取注册时间
                $register_days = floor(Carbon::parse($vo['create_time'])->diffInDays());
                // 获取累计交易天数
                $finance_product_order_day = FinanceProductOrderModel::where('user_id', $vo['id'])
                    ->where('status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
                    ->field('COUNT(DISTINCT DATE(FROM_UNIXTIME(create_time))) as order_count')
                    ->find();
                // 获取累计交易天数
                if ($last_finance_product_buy_time) {
                    $days = intval(Carbon::now()->diffInDays(Carbon::parse($last_finance_product_buy_time)));
                    $list[$key]['finance_product_no_order_day'] = abs($days);
                } else {
                    $list[$key]['finance_product_no_order_day'] = $register_days;//没有交易则为注册天数
                }
                $list[$key]['finance_product_order_day'] = $finance_product_order_day ? $finance_product_order_day['order_count'] : 0;
                $list[$key]['register_days'] = $register_days;

                // 写入数据到Excel
                $col = 1;
                $excelRow = $processed_count + $key + 2; // +2是因为第一行是表头
                foreach ($header as $field => $title) {
                    $cellAddress = Coordinate::stringFromColumnIndex($col) . $excelRow;
                    $sheet->setCellValue($cellAddress, $list[$key][$field] ?? '');
                    $col++;
                }
            }

            // 更新处理计数
            $processed_count += count($list);
            $current_page++;

            // 保存当前进度到缓存
            $cache_file->set($cache_key . "_page", $current_page, 600);
            $cache_file->set($cache_key . "_offset", $processed_count, 600);

            // 保存Excel文件
            $writer = new Xlsx($spreadsheet);
            $writer->save($full_file_path);

            // 清理内存
            unset($list, $withdrawals);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // 发送进度信息
            $item_data = [
                'item' => $processed_count,
                'total' => $total,
                'session_id' => session('id'),
                'cache_key'=>$cache_key
            ];
            echo "data: " . json_encode($item_data) . "\n\n";
            ob_flush();
            flush();

            // 短暂休眠以防止连接超时
            usleep(100000); // 0.1秒

        } while ($processed_count < $total);

        // 导出完成，清理缓存
        $cache_file->delete($cache_key . "_name");
        $cache_file->delete($cache_key . "_page");
        $cache_file->delete($cache_key . "_offset");

        $file_path = str_replace(public_path() . 'storage', "", $save_path) . $file_name;
        $file_data = [
            'url' => "/storage".$file_path,
            'file_name' => $file_name,
        ];

        echo "event: stream-end\n";
        ob_flush();
        flush();
        echo "data: " . json_encode($file_data) . "\n\n";
        ob_flush();
        flush();
    }


    // 导出用户列表
    public function export()
    {
        $id = $this->request->param("id/d", 0);
        $level = $this->request->param('level/d', 1);
        $param = input('param.');
        $userid = $this->request->param('userid/d', 0);
        $time_type = $this->request->param('time_type/d', 0);
        $is_xuni = $this->request->param('is_xuni/d', 0);
        $invite_code = $this->request->param('invite_code/s');
        $where[] = ['is_demo', '=', 0];
        if (!empty($param['uid'])) {
            $where[] = ['id', '=', $param['uid']];
        }
        if (!empty($param['user_name'])) {
            $where[] = ['user_name', 'like', '%' . $param['user_name'] . '%'];
        }
        if (!empty($param['invite_code'])) {
            $where[] = ['invite_code', 'like', '%' . $param['invite_code'] . '%'];
        }
        if (isset($param['level_id']) && $param['level_id'] != '') {
            $where[] = ['level_id', '=', $param['level_id']];
        }
        if (!empty($param['ip'])) {
            $where[] = ['last_login_ip|ip', '=', $param['ip']];
        }
        if (!empty($param['leader1'])) {
            $where[] = ['leader1|leader2|leader3|leader4|leader5|leader6|leader7', '=', $param['leader1']];
        }
        if ($id > 0 && ($level >= 1 && $level <= 7)) {
            $where[] = ["leader{$level}", '=', $id];
        } elseif ($id > 0 && $level == 99) {
            $where[] = ['tree_id', 'like', "%/{$id}/%"];
        }
        if (in_array($is_xuni, [0, 1])) {
            $where[] = ['is_xuni', '=', $is_xuni];
        } elseif ($is_xuni == 2) { //未登录过的用户
            $where[] = ['last_login_time', '=', 0];
        } elseif ($is_xuni == 3) { //未绑定银行卡的用户
            $where[] = ['bank_number', '=', ''];
        } elseif ($is_xuni == 4) { //已绑定银行卡的用户
            $where[] = ['bank_number', "<>", ""];
        }
        $orderby = ['id' => 'DESC'];
        if (!empty($param['sortName']) && in_array($param['sortName'], ['last_login_time', 'user_money', 'level_id'])) {
            $orderby = [$param['sortName'] => ($param['sortOrder'] == "asc" ? "ASC" : "DESC")];
        }
        if (!empty($param['startTime']) && !empty($param['endTime'])) {
            $start_time = strtotime($param['startTime'] . " 00:00:00");
            $end_time = strtotime($param['endTime'] . " 23:59:59");
            if ($time_type == 1) {
                $where[] = ['last_login_time', 'BETWEEN', [$start_time, $end_time]];
            } else {
                $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
            }
        }
        $user = new MemberModel();
        $list = $user->getAllUsersByWhere($where, $orderby);
        $status = config('app.user_status');
        $withdrawals = Db::name('withdrawals')->where("status", 4)->group("user_id")->column("sum(original_money)", "user_id");
        $level_name = LevelModel::column("level_name", 'level_id');
        // 拼装参数
        foreach ($list as $key => $vo) {
            $list[$key]['is_xuni'] = lang($vo['is_xuni'] == 1 ? '是' : '否');
            $list[$key]['last_login_time'] = date('Y-m-d H:i:s', $vo['last_login_time']);
            $list[$key]['status'] = $status[$vo['status']];
            if ($vo['delete_time']) {
                $list[$key]['status'] = lang('已删除');
            }
            $list[$key]['recharge'] = i18n_sprintf($vo['recharge_money']);
            $list[$key]['withdraw'] = isset($withdrawals[$vo['id']]) ? intval($withdrawals[$vo['id']]) : 0;;
            $list[$key]['user_money'] = i18n_sprintf($vo['user_money']);
            $list[$key]['level_id'] = isset($level_name[$vo['level_id']]) ? $level_name[$vo['level_id']] : '非会员';
            $top_leader = UserModel::getLeaderByTree($vo['tree_id'], 1);
            $list[$key]['top_leader_name'] = $top_leader ? $top_leader['remark'] : '无';
            $second_leader = UserModel::getLeaderByTree($vo['tree_id'], 2);
            $list[$key]['second_leader_id'] = $second_leader ? $second_leader['id'] : '无';
            $list[$key]['email'] = $vo['mobile'] ? '' : $vo['user_name'];
            preg_match_all("/\d+/", $vo['tree_id'], $matches);
            $count = count($matches[0]);
            $list[$key]['tree_level'] = $count;

            // 获取广告位待结算金额和AI智能托管待结算金额
            $finance_product_price = FinanceProductOrderModel::hasWhere('user')
                ->where('finance_product_order_model.status', 'in', [
                    FinanceProductOrderRepository::STATUS_PURCHASED,
                ])
                ->where('user_id', 'in', $vo['id'])
                ->sum('price');

            $investment_product_money = InvestmentProductOrderModel::hasWhere('user')
                ->where('investment_product_order_model.status', 'in', [
                    InvestmentProductOrderRepository::STATUS_PURCHASED,
                ])
                ->where('user_id', 'in', $vo['id'])
                ->sum('money');

            $list[$key]['product_frozen_money'] = $finance_product_price + $investment_product_money;
            $list[$key]['wcount'] = WithdrawModel::where('user_id', $vo['id'])->count();
            $user_latest_log = MemberLogModel::getUserLatestLog($vo['id']);
            $list[$key]['device_uuid'] = $user_latest_log['uuid'];

            // 获取注册时间
            $register_days = floor(Carbon::parse($vo['create_time'])->diffInDays());
            // 获取累计交易天数
            $list[$key]['finance_product_order_day'] = FinanceProductOrderModel::where('user_id', $vo['id'])
                ->where('status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
                ->field('COUNT(DISTINCT DATE(FROM_UNIXTIME(create_time))) as order_count')->select()[0]['order_count'];
            $list[$key]['finance_product_no_order_day'] = ($register_days > $list[$key]['finance_product_order_day']) ? $register_days - $list[$key]['finance_product_order_day'] : 0;
            $list[$key]['register_days'] = $register_days;
        }
        $header = [
            'top_leader_name' => '总代理名字',
            'id' => '用户ID',
            'tree_level' => '所在层级',
            'second_leader_id' => '初级ID',
            'leader1' => '上级ID',
            'team_count' => '下级人数',
            'true_name' => '姓名',
            'mobile' => '手机号',
            'email' => '邮箱',
            'document_id' => '账户账号',
            'document_type' => '账户类型',
//            'bank_number'                  => '银行卡号',
//            'bank_name'                    => '银行名称',
            'user_money' => '余额',
            'recharge' => '充值',
            'withdraw' => '提现',
            'product_frozen_money' => '冻结资金',
            'wcount' => '历史提现次数',
            'level_id' => 'VIP等级',
            'create_time' => '注册时间',
            'ip' => '注册ip',
            'last_login_ip' => '最后ip',
            'device_uuid' => '设备id',
            'finance_product_order_day' => '累计交易天数',
            'finance_product_no_order_day' => '累计未交易天数',
        ];
        return genXlsx($header, $list, "用户列表" . date('Ymd'));
    }

    public function batch()
    {
        $id = $this->request->param("id/d", 0);
        $type = $this->request->param('type/d', 1);
        if (!in_array($type, [1, 2]) || empty($id)) {
            $this->error(lang("参数有误"));
        }
        $user = new MemberModel();
        $row = $user->withTrashed()->whereLike('tree_id', "%/{$id}/%")->update(['status' => $type == 1 ? 2 : 1, 'login_key' => random(32)]);
        if ($row > 0) {
            insert_operation_log('批量解封/封停', "用户id:{$id}," . "成功" . ($type == 1 ? "封停" : '解封') . "{$row}个会员");
        }
        $this->success("成功" . ($type == 1 ? "封停" : '解封') . "{$row}个会员");
    }

    // 添加用户
    public function userAdd()
    {
        if (request()->isPost()) {
            $param = input('post.');
            // 分割 textarea 传过来的 user_name
            $user_names = explode("\n", $param['user_name']);
            $success_count = $fail_count = 0;
            foreach ($user_names as $key => $user_name) {
                // 判断账号类型
                if ($param['user_type'] == 1) { // 邮箱
                    // 检查提交的是不是邮箱
                    if (!filter_var($user_name, FILTER_VALIDATE_EMAIL)) {
                        $this->error(lang('请输入正确的邮箱格式'));
                    }
                    $param['mobile'] = '';
                }
                if ($param['user_type'] == 2) { // 手机
                    $param['phone_code'] = ConfigModel::getConfigValue('system_default_phone_code');
                    $param['mobile'] = $user_name;
                    if (!i18n_valid_mobile($user_name)) {
                        $this->error(lang('手机号格式有误'));
                    }
//                    $exist = MemberModel::where('mobile', $param['mobile'])->find();
//                    if ($exist) {
//                        unset($user_names[$key]);
//                        $fail_count ++;
////                        $this->error(lang('账号 %s 已存在', [$user_name]));
//                    }
                    // TODO 判断手机号格式
//                $this->error(lang('后台不支持新增手机账号'));
                }

                // 判断用户名是否已存在
                $exist = MemberModel::where('user_name', $user_name)->find();
                if ($exist) {
                    unset($user_names[$key]);
                    $fail_count++;
//                    $this->error(lang('账号已存在'));
                }

            }

            if (empty($param['password'])) {
                $this->error(lang('请填写密码'));
            }


            if (array_key_exists('paypwd', $param) && $param['paypwd']) {
                $param['paypwd'] = md5($param['paypwd'] . config('app.salt'));
            } else {
                $param['paypwd'] = md5("123456" . config('app.salt'));
            }

            $user = new MemberModel();
            if (isset($param['invite_code']) && !empty($param['invite_code'])) {
                // 判断提交的邀请码是否正确
                $leader = MemberModel::where('invite_code', $param['invite_code'])->find();
                if (!$leader) {
                    $this->error(lang('邀请码不存在'));
                }

                $parent = $user->getOneUser($leader->id);
                if (empty($parent)) {
                    return json(msg(-1, '', lang("上级用户信息不存在")));
                }
                $param['leader1'] = $parent['id'];
                $param['leader2'] = $parent['leader1'];
                $param['leader3'] = $parent['leader2'];
                $param['leader4'] = $parent['leader3'];
                $param['leader5'] = $parent['leader4'];
                $param['leader6'] = $parent['leader5'];
                $param['leader7'] = $parent['leader6'];
            } else {
                $param['leader1'] = 0;
            }

            $param['password'] = md5($param['password'] . config('app.salt'));
            $param['avatar'] = mt_rand(1, 100) . '.png';
            $param['invite_code'] = generateInviteCode();
            $param['create_time'] = time();
            $param['ip'] = getip();

            $result = $this->validate($param, 'MemberValidate');
            if (true !== $result) {
                return json(msg(-1, '', lang($result)));
            }

            foreach ($user_names as $user_name) {
                if ($param['user_type'] == 2) {
                    $param['mobile'] = $user_name;
                }
                $param['user_name'] = $user_name;
                $flag = $user->insertUser($param);
                if ($flag['code'] == 1) {
                    $success_count++;
                    $id = MemberModel::where('user_name', $user_name)->value('id');
                    updateTreeById2($id);
                    // 检查上级是否可以升级
//                if ($user->leader1) {
//                    checkLevel($user['leader1']); // 会员升级
//                }
                    insert_operation_log('新增会员', "用户id:{$id}", $id);
                } else {
                    $fail_count++;
                }
            }
            return json(msg(1, url('member/index'), lang('添加用户 成功 %d, 失败 %d', [$success_count, $fail_count])));
        }
        // 获取语言
        $langs = getLangs(1);
        View::assign('langs', $langs);
        return View::fetch();
    }

    // 编辑用户
    public function userEdit()
    {
        $user = new MemberModel();
        $id = input('param.id');
        $info = $user->getOneUser($id);

        if (request()->isPost()) {
            $param = input('post.');
            $result = $this->validate($param, 'MemberValidate');
            if (true !== $result) {
                return json(msg(-1, '', lang($result)));
            }
            $paypwd = $param['paypwd'];
            if ($param['paypwd']) {
                $param['paypwd'] = md5($param['paypwd'] . config('app.salt'));
            } else {
                unset($param['paypwd']);
            }

            if (!array_key_exists('status', $param)) {
                $param['status'] = 2; // 禁用
                // 禁用同时设置 login_key
                $param['login_key'] = random(32);
            }
            if (!array_key_exists('withdraw_status', $param)) {
                $param['withdraw_status'] = 0; // 禁用
            }
            if (!array_key_exists('kyc_status', $param)) {
                $param['kyc_status'] = 0;
            }
            if (!array_key_exists('is_proxy', $param)) {
                $param['is_proxy'] = 0;
            }
            if (!array_key_exists('is_total_proxy', $param)) {
                $param['is_total_proxy'] = 0;
            }
            if (!array_key_exists('is_upgrade', $param)) {
                $param['is_upgrade'] = 0;
            }
            if (!array_key_exists('do_task_status', $param)) {
                $param['do_task_status'] = 0;
            }
            if (!array_key_exists('invite_code_status', $param)) {
                $param['invite_code_status'] = 0; // 邀请码无效
            }
            if (!array_key_exists('show_virtual_data', $param)) {
                $param['show_virtual_data'] = 0; // 是否显示假数据
            }
            if (!array_key_exists('is_finance_product_count_down', $param)) {
                $param['is_finance_product_count_down'] = 0; // 是否显示假数据
            }
            if (!array_key_exists('team_info_month_reset', $param)) {
                $param['team_info_month_reset'] = 0; // 是否显示假数据
            }
            if (!array_key_exists('transfer_money_status', $param)) {
                $param['transfer_money_status'] = 0; // 是否可以转账给其他用户
            }
            // 密码
            $password = $param['password'];
            if (!empty($param['password'])) {
                $param['password'] = md5($param['password'] . config('app.salt'));

            } else {
                unset($param['password']);
            }
            // 变更vip
            if (!empty($param['level_id'])) {
                // 获取用户信息
                $user_id = $param['id'];

                // 获取等级信息
                $level = LevelModel::where('level_id', $param['level_id'])->find();
                if (!$level) {
                    $this->error(lang('广告位库存不存在'));
                }
                $level = LevelModel::where('level_id', $param['level_id'])->find();
                $param['level_id'] = $level->level_id;
                if ($param['level_id'] > $info['level_id']) {
                    UserModel::incTaskCount($user_id);
                    //后台修改等级添加升级记录
                    $member_level_model = new MemberLevelModel();
                    $member_level_model->addUserLevelLog($user_id, $info['level_id'], $level['level_id'], $level['price'], $level['level_time'], MemberLevelModel::LEVEL_TYPE_NATURAL_DAYS, $level['type']);
                }
            }
            if (!empty($param['ad_plan_id'])) {
                $user_id = $param['id'];
                $ad_plan = AdPlanModel::where('id', $param['ad_plan_id'])->find();
                // 增加用户计划记录
                if ($info->ad_plan_id && $info->ad_plan_id != $param['ad_plan_id']) {
                    // 调整计划
                    AdPlanUserModel::create([
                        'user_id' => $user_id,
                        'ad_plan_id' => $ad_plan->id,
                        'previous_plan_id' => $info->ad_plan_id
                    ]);
                } else {
                    // 新增计划
                    if (!$ad_plan) {
                        $this->error(lang('VIP等级不存在'));
                    }
                    $exists = AdPlanUserModel::where('user_id', $user_id)->find();
                    if (!$exists) {
                        AdPlanUserModel::create([
                            'user_id' => $user_id,
                            'ad_plan_id' => $ad_plan->id,
                            'previous_plan_id' => 0
                        ]);
                    }
                }
            }
            if ($param['bank_code']) {
                $param['bank_name'] = BankModel::where('bank_code', $param['bank_code'])->value('name');
            } else {
                $param['bank_name'] = '';
            }
            if ($param['withdraw_max_usdt'] == '') {
                $param['withdraw_max_usdt'] = 0;
            }
            unset($param['leader1']);
            $flag = $user->editUser($param);
            if (isset($param['user_name'])) {
                $account = $param['user_name'];
                $redis = Cache::store("redis");
                $key = "login_password_err_" . md5($account);
                $redis->set($key, 0, 0);
            }
            if (isset($param['mobile'])) {
                $account = $param['mobile'];
                $redis = Cache::store("redis");
                $key = "login_password_err_" . md5($account);
                $redis->set($key, 0, 0);
            }
            if ($flag['code'] == 1) {
                if ($info->bank_number != $param['bank_number'] ||
                    $info->bank_name != $param['bank_name'] ||
                    $info->bank_code != $param['bank_code'] ||
                    $info->true_name != $param['true_name'] ||
                    $info->document_id != $param['document_id'] ||
                    $info->document_type != $param['document_type']
                ) {
                    $bank = [
                        'true_name' => $param['true_name'],
                        'bank_number' => $param['bank_number'],
                        'bank_name' => $param['bank_name'],
                        'bank_code' => $param['bank_code'],
                        'document_id' => $param['document_id'],
                        'document_type' => $param['document_type'],
                    ];
                    insert_operation_log('修改会员银行卡号', $bank, $param['id']);
                }
                if ($password && $info->password != $password) {
                    insert_operation_log('修改会员登录密码', [], $param['id']);
                }
                if ($paypwd && $info->paypwd != $paypwd) {
                    insert_operation_log('修改会员安全密码', [], $param['id']);
                }
                if ($info->user_water_status != $param['user_water_status']) {
//                    0-不限制 1-默认 2-限制
                    $user_water_status_arr = [
                        '不限制',
                        '默认',
                        '限制',
                    ];
                    insert_operation_log('修改会员流水限制', ['user_water_status' => $param['user_water_status'], 'user_water_status_str' => $user_water_status_arr[$param['user_water_status']]], $param['id']);
                }
                if ($info->withdraw_status != $param['withdraw_status']) {
                    $withdraw_status_arr = [
                        '禁止提现',
                        '允许提现',
                    ];
                    insert_operation_log('修改会员提现状态', ['withdraw_status' => $param['withdraw_status'], 'withdraw_status_str' => $withdraw_status_arr[$param['withdraw_status']]], $param['id']);
                }
                insert_operation_log('修改会员', $param, $param['id']);
            }
            return json(msg($flag['code'], $flag['data'], lang($flag['msg'])));
        }

        if (empty($info)) {
            $this->error(lang("用户信息不存在"));
        }
        $info['old_leader1'] = isset($info['leader1']) ? $info['leader1'] : '-';
//        $info['update_time'] = $info['update_time'] ? date("Y-m-d H:i:s", $info['update_time']) : "-";
        $level = new LevelModel();
        if ($info['leader1']) {
            $info['parent'] = $user->getOneUser($info['leader1']);
        }
        $banks = i18n_banks2();
        View::assign([
            'level' => $level->getLevel(),
//            'plan_list' => PlanModel::getPlanList(),
            'ad_plan_list' => AdPlanModel::getPlanList(),
            'user' => $info,
            'banks' => $banks,
            'status' => config('app.user_status')
        ]);
        return View::fetch();
    }

    // 删除用户
    public function userDel()
    {
        $id = input('param.id');
        $ids = explode(",", $id);
        if (empty($ids)) {
            return msg(-1, '', '参数有误');
        }
        foreach ($ids as $id) {
            MemberModel::destroy($id);
            insert_operation_log('删除会员', "用户id:{$id}");
        }
        return json(msg(1, '', lang('删除用户成功')));
    }

    public function userBatch()
    {
        $param = input('param.');
        $result = false;
        $msg = '操作完成';
        if (request()->isPost()) {
            if (isset($param['ids']) && !empty($param['ids'])) {
                $ids = $param['ids'];
                //批量禁用
                if (isset($param['disable_member'])) {
                    $status = $param['disable_member'];
                    if ($status > 0) {
                        $data = [
                            'status' => $status,
                        ];
                        if ($status == 2) {
                            $data['login_key'] = random(32);
                        }
                        MemberModel::where(['id' => ['in', $ids]])->update($data);
                        $result = true;
                    }
                    $msg = ($status == 2 ? "禁用" : "启用") . "用户" . ($result ? "成功" : "失败");
                }
            }
            return json(msg($result, '', lang($msg)));
        }
    }

    // 账户调整
    public function money()
    {
        $money_type = MoneyLogModel::$TYPE_NAMES;
        $detail_type = MoneyLogModel::$DETAIL_TYPE_NAMES;
        $uid = input('uid/d');
        View::assign('uid', $uid);
        View::assign('money_type', $money_type);
        View::assign('detail_type', $detail_type);
        return View::fetch();
    }

    // 账户调整
    public function water()
    {
        $money_type = MoneyLogModel::$TYPE_NAMES;
        $detail_type = MoneyLogModel::$DETAIL_TYPE_NAMES;
        $uid = input('uid/d');
        View::assign('uid', $uid);
        View::assign('money_type', $money_type);
        View::assign('detail_type', $detail_type);
        return View::fetch();
    }

    // 调整余额
    public function moneyAdd()
    {
        if (request()->isPost()) {
            $param = $this->request->param();
            unset($param['code']);
            if ($param['money'] == 0) {
                $this->error(lang('金额有误'));
            }
            if ($param['money'] > 0 && $param['usertype'] == 1) {
                $this->error(lang('「基础」只允许减钱'));
            }
            if (!in_array($param['usertype'], [1, 2, 3, 4])) {
                $this->error(lang('请选择操作类型'));
            }
            $redis = Cache::store('redis');
            $redis_key = "money_add:{$param['user_id']}";
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
            $change = "add";
            $ch_str = "增加";
            if ($param['money'] < 0) {
                $ch_str = "扣除";
                $change = "sub";
            }
            switch ($param['usertype']) {
                case 1:
                    $title = "后台手动{$ch_str}";
                    $remarks = "后台手动{$ch_str}:{$param['money']} " . $param['remarks'];
                    $money_log = new MoneyLogModel();
                    $data = $money_log->changeMoney($userid, $param['money'], MoneyLogModel::TYPE_USER, $change, $title,
                        $remarks, 0,
                        session('id', ''));
                    if ($data['code'] == 1) {
                        // 恢复已匹配未支付的广告位订单
                        (new FinanceProductOrderRepository())->recoverOrder($userid);
                    }
                    break;
                case 2:
                    $title = "后台{$ch_str}佣金";
                    $remarks = "后台{$ch_str}佣金:{$param['money']}" . $param['remarks'];
                    $money_log = new MoneyLogModel();
                    $data = $money_log->changeMoney($userid, $param['money'], MoneyLogModel::TYPE_BROKERAGE, $change,
                        $title, $remarks, 0,
                        session('id', ''));
                    break;
                case 3:
                    $title = "后台{$ch_str}充值";
                    $remarks = "后台{$ch_str}充值:{$param['money']}" . $param['remarks'];
                    $money_log = new MoneyLogModel();
                    $data = $money_log->changeMoney($userid, $param['money'], MoneyLogModel::TYPE_RECHARGE, $change,
                        $title, $remarks, 0,
                        session('id', ''));
                    break;
                case 4:
                    $title = "后台{$ch_str}彩金";
                    $remarks = "后台{$ch_str}彩金:{$param['money']}" . $param['remarks'];
                    $money_log = new MoneyLogModel();
                    $data = $money_log->changeMoney($userid, $param['money'], MoneyLogModel::TYPE_TYJ, $change, $title,
                        $remarks, 0,
                        session('id', ''));
                    break;
            }
            if ($data['code'] == 1) {
                $this->success(lang('操作成功'), url('member/money', ['uid' => $param['user_id']]));
            } else {
                $this->error(lang($data['msg']));
            }
        }
    }

    // 删除余额变动日志
    public function money_log_del($id)
    {
        $ids = implode(',', $id);
        $money_model = new MoneyLogModel();
        $money_model->where('id', 'in', $ids)->delete();
        $this->success(lang('删除成功'), url('index'));
    }

    public function edit_parent()
    {
        $id = $this->request->param('userid/d');
        $user = MemberModel::where('id', $id)->find();
        if (empty($user)) {
            $this->error(lang("用户信息不存在"));
        }
        if ($this->request->isAjax()) {
            $redis = Cache::store('redis');
            $redis_key = "change_leader1_{$id}";
            if ($redis->has($redis_key)) {
                $this->error(lang("请勿重复提交,正在修改中..."));
            }
            $leader1 = $this->request->param('leader1/s', 0);
            if ($leader1 == $user->leader1) {
                $this->error(lang("修改的上级ID和原有用户上级ID一致,无须修改"));
            }
            if ($user->id == $leader1) {
                $this->error(lang("不能将自己设置为自己的上级"));
            }
            $leader1_info = MemberModel::where('id', $leader1)->find();
            if (!$leader1_info) {
                $this->error(lang("修改的上级ID有误"));
            }
            insert_operation_log('修改会员上级id', "用户ID:{$id},原上级ID:{$user->leader1},修改的上级ID:{$leader1}");
            $old_leader1 = $user->leader1;
//            $user->tree_id = $leader1_info->tree_id."{$id}/";
            // 更新leader1-leader7
//            $user->save();

//            $this->setUserTreeAndLeader($id, $user->tree_id);

            Queue::push("ChangeLeaderV2", ['user_id'=>$id, 'tree_id'=>$leader1_info->tree_id."{$id}/"]);
            // 更新用户下级数量
            Queue::push('CalculateTeamCount', $old_leader1);
            Queue::push('CalculateTeamCount', $leader1);
            $redis->set($redis_key, 1);
            $redis->set("calculate_team_count_{$old_leader1}", 1);
            $redis->set("calculate_team_count_{$leader1}", 1);
            $this->success(lang("修改成功"));
        }

        $child_count = MemberModel::whereLike('tree_id', "%/{$id}/%")->count();
        View::assign('user', $user);
        View::assign('child_count', $child_count);
        return View::fetch();
    }

    public function setUserTreeAndLeader($user_id, $tree_id = ''){
        $tree_arr = explode('/', $tree_id);
        // 数组去空 $tree_arr
        $tree_arr = array_filter($tree_arr);
        // 倒序
        $tree_arr = array_reverse($tree_arr);
        $userModel = MemberModel::where("id", $user_id)->find();
        for ($i = 1; $i < count($tree_arr); $i++){
            $userModel["leader{$i}"] = $tree_arr[$i];
        }
        $userModel->save();
    }

    //批量修改
    public function edit_batch()
    {

        $batch_type = $this->request->param('batch_type/s');
        $user_names = $this->request->param('user_names/s');
        $user_name = $this->request->param('user_name/s');
        $level_id = $this->request->param('level_id/s');
        $finance_plan_id = $this->request->param('finance_plan_id/s');
        $startTime = $this->request->param('startTime/s');
        $endTime = $this->request->param('endTime/s');
        $witrec = $this->request->param('witrec/s');
        if ($this->request->isAjax()) {
            $batch_type = intval($batch_type);
            switch ($batch_type) {
                case 1:
                    $user_names = urlencode($user_names);
                    $user_names = str_replace(' ', '', $user_names);
                    $user_names = explode('%0D%0A', $user_names);
                    $user_name_arr = [];
                    foreach ($user_names as $k => $v) {
                        $user_name_arr[] = urldecode($v);
                    }
                    UserModel::where('user_name', 'in', $user_name_arr)->update(['finance_plan_id' => $finance_plan_id]);
                    break;
                case 2:
                    if (empty($startTime) || empty($endTime)) {
                        $this->error(lang("请选择时间"));
                    }
                    $start_time = strtotime($startTime);
                    $end_time = strtotime($endTime);
                    UserModel::where('level_id', $level_id)
                        ->where('create_time', '>=', $start_time)
                        ->where('create_time', '<=', $end_time)
                        ->update(['finance_plan_id' => $finance_plan_id]);
                    break;
                case 3:
                    if ($witrec < 0) {
                        $this->error(lang("充提差有误"));
                    }
                    $withdrawals = new WithdrawModel();
                    $list = $withdrawals->where('status', WithdrawalRepository::STATUS_SUCCESS)->field('SUM(original_money) as all_original_money,user_id')->group('user_id')->select();
                    $recharge = new RechargeModel();
                    $reclist = $recharge->where('status', RechargeModel::STATUS_SUCCESS)->field('SUM(money) as all_money,user_id')->group('user_id')->select();
                    $reclist_ids = [];
                    foreach ($reclist as $k => $v) {
                        $reclist_ids[$v['user_id']] = $v['all_money'];
                    }
                    $user_ids = [];
                    foreach ($list as $k => $v) {
                        if ($v['all_original_money'] - $reclist_ids[$v['user_id']] >= $witrec) {
                            $user_ids[] = $v['user_id'];
                        }
                    }
                    UserModel::where('id', 'in', $user_ids)->update(['finance_plan_id' => $finance_plan_id]);
                    break;
                case 4:
                    $user_name = trim($user_name);
                    $temp = UserModel::where('user_name', $user_name)->find();
                    if (empty($temp)) {
                        $this->error(lang("用户名不存在"));
                    }
                    $user_ids = MemberModel::whereLike('tree_id', "%/{$temp->id}/%")->column('id');

                    UserModel::where('id', 'in', $user_ids)->where('id', '<>', $temp->id)->update(['finance_plan_id' => $finance_plan_id]);
                    break;
            }
            $this->success(lang("修改成功"), '', 'close');
        }
        $level = new LevelModel();
        View::assign([
            'level' => $level->getLevel(),
            'ad_plan_list' => AdPlanModel::getPlanList(),
        ]);
        return View::fetch();
    }

    public function setcp()
    {
        $id = $this->request->param('user_id/d');
        $user = MemberModel::where('id', $id)->find();
        if (empty($user)) {
            $this->error(lang("用户信息不存在"));
        }
        if ($this->request->isAjax()) {
            $cp_id = $this->request->param('cp_id/s');
            if (empty($cp_id)) {
                $this->error(lang("请填写CP ID"));
            }
            if ($cp_id == $user->cp_id) {
                $this->error(lang("修改的CPID和原有用户CPID一致,无须修改"));
            }
            $cp_info = MemberModel::where('id|mobile|invite_code', $cp_id)->find();
            if (!$cp_info) {
                $this->error(lang("填写的修改CP信息有误"));
            }
            insert_operation_log('修改会员CPid', "用户ID:{$id},原CPID:{$user->cp_id},修改后的CPID:{$cp_info->id}");
            $user->cp_id = $cp_info->id;
            $user->save();
            $this->success(lang("修改成功"));
        }

        View::assign('user', $user);
        return View::fetch();
    }

    /**
     * 取消cp
     */
    public function cancelcp()
    {
        $id = $this->request->param('user_id/d');
        $user = MemberModel::where('id', $id)->find();
        if (empty($user)) {
            $this->error(lang("用户信息不存在"));
        }
        if (0 == $user->cp_id) {
            $this->error(lang("修改的CPID和原有用户CPID一致,无须修改"));
        }
        insert_operation_log('修改会员CPid', "用户ID:{$id},原CPID:{$user->cp_id},修改后的CPID:0");
        $user->cp_id = 0;
        $user->save();
        Db::name('order')->where('specify_user_id', $id)
            ->where('status', 1)
            ->update([
                'status' => 3, 'paid_status' => 3
            ]);
        $this->success(lang("修改成功"));
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
                'auth' => 'member/useredit',
                'href' => url('member/userEdit', ['id' => $id]),
                'btnStyle' => 'primary',
            ]
        ];
    }

    public function wallet_balance($id, $type = 'TRC20')
    {
        $user = MemberModel::find($id);

        // 获取 TRC20 配置
        $recharge_address = $user->recharge_address;
        $platform = PaymentPlatformModel::where('name', 'like', $type . '%')->find();
        if (!$platform) {
            throw new Exception(lang('参数错误'));
        }
        if (!$recharge_address) {
            // 没有钱包地址，先去生成
            $recharge_address = PaymentPlatformModel::getRechargeAddress($user->id, $platform->id);
        }
        // 获取虚拟币余额
        $res = PaymentPlatformModel::getWalletBalance($recharge_address, $platform->id, $type);
        $assets = $res['data']['assets'];
        foreach ($assets as $item) {
            switch ($item['coinName']) {
                case 'TRX';
                    $user->trx_address_money = $item['balance'];
                    break;
                case 'USDT';
                    $user->usdt_address_money = $item['balance'];
                    break;
            }
        }
        $user->save();

        $this->success(lang("查询成功") . ': USDT:' . $user->usdt_address_money . ' TRX:' . $user->trx_address_money);
    }

    /**
     * 获取指定用户的下级个数
     * @param $user_name
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function lower_level_count($user_name)
    {
        $user = MemberModel::where('user_name', $user_name)->find();
        $count = MemberModel::whereLike('tree_id', "%/{$user->id}/%")
            ->where('id', '<>', $user->id)
            ->count();
        $this->success('success', '', $count);
    }

    /**
     * 编辑虚拟用户数据
     * @param $id
     * @return mixed|\think\response\Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function edit_virtual($uid = '')
    {

        if (request()->isPost()) {
            $data = $this->request->post();
            $data['update_time'] = time();
            $id = $data['id'];
            unset($data['id']);
            $res = HomeUserVirtualModel::where('id', $id)->update($data);
            if ($res) {
                $this->success(lang("修改成功"), '', 'close');
            } else {
                $this->error(lang("修改失败"), '', '');
            }
        }
        if (empty($uid)) {
            $this->error(lang("参数有误"));
        }
        $data = HomeUserVirtualModel::where('user_id', $uid)->find();
        if (empty($data)) {
            HomeUserVirtualModel::getUserVirtual($uid);
            $data = HomeUserVirtualModel::where('user_id', $uid)->find();
        }
        View::assign('data', $data);
        return View::fetch();
    }

    //虚拟用户钱包日志
    public function virtual_log()
    {
        $money_type = HomeUserVirtualLogModel::$TYPE_NAMES;
        if (request()->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
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
            $field = "id,user_id,admin_id,change,title,remarks,money,user_money,last_money,type,status,create_time";
            $result = Db::table('qd_home_user_virtual_log')->field($field)->where($where)
                ->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = Db::table("qd_home_user_virtual_log")->where($where)->count();
            foreach ($result as $key => &$vo) {
                $vo['type_str'] = $money_type[$vo['type']];
                if ($vo['admin_id'] > 0) {
                    $vo['admin_name'] = AdminModel::where('id', $vo['admin_id'])->value('user_name');
                } else {
                    $vo['admin_name'] = '-';
                }
                $user_info = UserModel::getOneUser($vo['user_id'], 'id', 'user_name,nick_name,mobile');
                $vo['user_info'] = $user_info['user_name'];
                $vo['create_time'] = date('Y-m-d H:i:s', $vo['create_time']);
                $vo['change_str'] = $vo['change'] == 1 ? '收入' : '支出';
            }
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;

            $stat = Db::table('qd_home_user_virtual_log')->where($where)->group('type')->field('type, sum(money) AS total')->select();
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
        $user_id = $this->request->param('user_id', '');
        $user_name = '';
        $start_time = '';
        if ($user_id) {
            $user = MemberModel::where('id', $user_id)->find();
            $user_name = $user->user_name;
            $start_time = date('Y-m-d', strtotime($user->create_time));
        }
        $arr_type = [
            1 => '用户充值',
            2 => '提现申请',
            3 => '任务返佣',
            4 => '首充送款',
            5 => '抽奖活动',
            6 => '签到活动',
            7 => '升级返佣',
            8 => '分享奖励',
            9 => '任务返利-1级别',
            10 => '任务返利-2级别',
            11 => '任务返利-3级别',
            12 => '广告库存',
            13 => 'AI智能托管',
            14 => 'AI智能托管收益',
            15 => "当日直推奖励",
            16 => '收益结算',
            17 => '升级会员',
            19 => '团队活跃奖',
            20 => '系统自动修正',
            21 => '奖励发放错误',
            22 => '转账给其他用户',
            23 => '收到其他用户转账',
            24 => '连续推广奖励',
            25 => '领取佣金池佣金',
            26 => '充值送金',
            27 => '领取团队活跃奖励',
            28 => '体验券领取',
            29 => '体验券到期回收'
        ];
        View::assign('user_id', $user_id);
        View::assign('start_time', $start_time);
        View::assign('user_name', $user_name);
        View::assign('money_type', $arr_type);
        return View::fetch();
    }

    //虚拟用户钱包添加
    public function virtual_add()
    {
        $user_id = $this->request->param('uid/d', 0);
        $money = $this->request->param('money/f', 0);
        $detail_type = $this->request->param('detail_type/d', 0);
        $add_time = $this->request->param('add_time/s', 0);
        if (empty($user_id) || empty($detail_type)) {
            $this->error(lang("参数有误"));
        }
        if (empty($add_time)) {
            $add_time = date("Y-m-d");
        }
        if ($money <= 0) {
            $this->error(lang("操作余额不得为负数"));
        }
        $change = 'add';
        $title = '后台操作';
        $remarks = '后台操作_' . $money;
        switch ($detail_type) {
            case 1:
                $title = '用户充值';
                $remarks = '用户充值_' . $money;
                break;
            case 2:
                $title = '提现申请';
                $remarks = '提现申请_' . $money;
                $change = 'sub';
                break;
            case 3:
                $title = '任务返佣';
                $remarks = '完成任务返佣:' . $money;
                break;
            case 4:
                $title = '首充送款';
                $remarks = '首充送款' . $money;
                break;
            case 5:
                $title = '抽奖活动';
                $remarks = '抽奖活动_' . $money;
                break;
            case 6:
                $title = '签到活动';
                $remarks = '签到活动_' . $money;
                break;
            case 7:
                $title = '升级返佣';
                $remarks = '升级返佣_' . $money;
                break;
            case 8:
                $title = '分享奖励';
                $remarks = '分享奖励_' . $money;
                break;
            case 9:
                $title = '任务返利-1级别';
                $remarks = '任务返利-1级别_' . $money;
                break;
            case 10:
                $title = '任务返利-2级别';
                $remarks = '任务返利-2级别_' . $money;
                break;
            case 11:
                $title = '任务返利-3级别';
                $remarks = '任务返利-3级别_' . $money;
                break;
            case 12:
                $title = '库存买入';
                $remarks = '';
                $change = 'sub';
                break;
            case 13:
                $title = 'AI智能托管转入';
                $remarks = '';
                $change = 'sub';
                break;
            case 14:
                $title = 'AI智能托管收益';
                $remarks = '';
                break;
            case 15:
                $title = "当日直推1级奖励";
                $remarks = "当日直推1级获得奖励:" . $money;
                break;
            case 16:
                $title = '收益结算';
                $remarks = '';
                break;
            case 17:
                $title = '升级会员';
                $remarks = '升级会员扣除余额:-' . $money;
                $change = 'sub';
                break;
            case 18:
                $detail_type = 15;
                $title = "当日直推2级奖励";
                $remarks = "当日直推2级获得奖励:" . $money;
                break;
            case 19:
                $detail_type = 15;
                $title = "当日直推3级奖励";
                $remarks = "当日直推3级获得奖励:" . $money;
                break;
            case 20:
                $detail_type = 15;
                $title = "当日直推4级奖励";
                $remarks = "当日直推4级获得奖励:" . $money;
                break;
            case 21:
                $detail_type = 15;
                $title = "当日直推5级奖励";
                $remarks = "当日直推5级获得奖励:" . $money;
                break;
            case 22:
                $detail_type = 15;
                $title = "当日直推奖励";
                $remarks = "当日直推获得奖励:" . $money;
                break;
            case 23:
                $detail_type = 15;
                $title = "当周直推奖励";
                $remarks = "当周直推获得奖励:" . $money;
                break;
            case 24:
                $detail_type = 15;
                $title = "当月直推奖励";
                $remarks = "当月直推获得奖励:" . $money;
                break;
            case 25:
                $title = '广告库存返利-1级别';
                $remarks = '广告库存返利-1级别_' . $money;
                break;
            case 26:
                $title = '广告库存返利-2级别';
                $remarks = '广告库存返利-2级别_' . $money;
                break;
            case 27:
                $title = '广告库存返利-3级别';
                $remarks = '广告库存返利-3级别_' . $money;
                break;
            case 28:
                $title = '体验券领取';
                $remarks = '';
                break;
            case 29:
                $title = '体验券到期回收';
                $remarks = '';
                break;

        }
        $home_user_virtual_logmodel = new HomeUserVirtualLogModel();
        $is_xuni = UserModel::where('id', $user_id)->value('is_xuni');
        if ($is_xuni != 1) {
            $this->error(lang("该用户不是虚拟用户，不能操作"));
        }
        $data = $home_user_virtual_logmodel->changeMoney(
            $user_id,
            $money,
            HomeUserVirtualLogModel::TYPE_USER,
            $change,
            $title,
            $remarks,
            $detail_type,
            session('id', ''),
            $add_time
        );
        if ($data['code'] == 1) {
            $this->success(lang('操作成功'), url('member/virtual_log', ['user_id' => $user_id]));
        } else {
            $this->error(lang($data['msg']));
        }
    }

    // 虚拟用户钱包删除
    public function virtual_del($id = '')
    {
        $ids = implode(',', $id);
        $plan_model = new HomeUserVirtualLogModel();
        $plan_model->where('id', 'in', $ids)->delete();
        $this->success(lang('删除成功'), url('index'));
    }

    // 修改提现状态
    public function withdraw()
    {
        $id = $this->request->param("id/d", 0);
        $type = $this->request->param('type/d', 1);
        if (!in_array($type, [0, 1]) || empty($id)) {
            $this->error(lang("参数有误"));
        }
        $user = new MemberModel();
        $row = $user->withTrashed()
            ->whereLike('tree_id', "%/{$id}/%")
            ->update(['withdraw_status' => $type]);
        if ($row > 0) {
            insert_operation_log('批量禁提', "用户id:{$id}," . "成功" . ($type == 0 ? "禁用提现" : '启用提现') . "{$row}个会员");
        }
        $this->success("成功" . ($type == 1 ? "启用提现" : '禁用提现') . "{$row}个会员");

    }

    // 修改升级做任务
    public function upgrade()
    {
        $id = $this->request->param("id/d", 0);
        $type = $this->request->param('type/d', 1);
        if (!in_array($type, [0, 1]) || empty($id)) {
            $this->error(lang("参数有误"));
        }
        $user = new MemberModel();
        $row = $user->withTrashed()
            ->whereLike('tree_id', "%/{$id}/%")
            ->update(['is_upgrade' => $type]);
        if ($row > 0) {
            insert_operation_log('批量升级', "用户id:{$id}," . "成功" . ($type == 0 ? "不需要升级" : '需要升级') . "{$row}个会员");
        }
        $this->success("成功" . ($type == 1 ? "需要升级" : '不需要升级') . "{$row}个会员");
    }

    // 是否可购买广告位
    public function do_task_status()
    {
        $id = $this->request->param("id/d", 0);
        $type = $this->request->param('type/d', 1);
        if (!in_array($type, [0, 1]) || empty($id)) {
            $this->error(lang("参数有误"));
        }
        $user = new MemberModel();
        $row = $user->withTrashed()
            ->whereLike('tree_id', "%/{$id}/%")
            ->update(['do_task_status' => $type]);
        if ($row > 0) {
            insert_operation_log('批量购买广告位', "用户id:{$id}," . "成功" . ($type == 0 ? "不可购买广告位" : '可购买广告位') . "{$row}个会员");
        }
        $this->success("成功" . ($type == 1 ? "可购买广告位" : '不可购买广告位') . "{$row}个会员");

    }


    public function getUserLog()
    {
        $user_id = request()->param('user_id');
        $result = MemberLogModel::with('user')->withCount('user_ip')->where('user_id', $user_id)
            ->group('ip')
            ->select();

        $total = MemberLogModel::where('user_id', $user_id)
            ->group('ip')
            ->count();

        // 拼装参数
        foreach ($result as $key => $vo) {
            $result[$key]['user_name'] = $vo['user']['user_name'];
            $ip_count = $result[$key]['user_ip_count'] > 0 ? $result[$key]['user_ip_count'] - 1 : $result[$key]['user_ip_count'];
            $result[$key]['user_ip_count'] = '<span style="color: #0c80fe;cursor: pointer" class="ip-count" data-ip="' . $vo['ip'] . '" data-user_id="' . $vo['user_id'] . '">' . $ip_count . '</span>';
        }

        $data = [
            'code' => '',
            'msg' => '',
            'count' => $total,
            'data' => $result,
        ];

        return json($data);
    }

    /**
     * 使token失效
     */
    public function offline(): Json
    {
        $user_id = $this->request->param('user_id/d');
        if (empty($user_id)) {
            return json(msg(-1, '', lang('参数错误')));
        }

        $user = UserModel::find($user_id);
        if (!$user) {
            return json(msg(-1, '', lang('用户不存在')));
        }

        // 更新用户的login_key使所有token失效
        $user->login_key = random(32);
        if ($user->save()) {
            // 记录操作日志
            insert_operation_log('使token失效', ['user_id' => $user_id]);
            return json(msg(0, '', lang('操作成功')));
        } else {
            return json(msg(-1, '', lang('操作失败')));
        }
    }

    /**
     * 整条线邀请码禁用/启用
     * @return void
     */
    public function inviteCodeStatus()
    {
        $id = $this->request->param("id/d", 0);
        $type = $this->request->param('type/d', 1);
        if (!in_array($type, [0, 1]) || empty($id)) {
            $this->error(lang("参数有误"));
        }
        $user = new MemberModel();
        $row = $user->withTrashed()
            ->whereLike('tree_id', "%/{$id}/%")
            ->update(['invite_code_status' => $type]);
        if ($row > 0) {
            insert_operation_log('批量禁用邀请码', "用户id:{$id}," . "成功" . ($type == 0 ? "禁用邀请码" : '启用邀请码') . "{$row}个会员");
        }
        $this->success("成功" . ($type == 1 ? "启用邀请码" : '禁用邀请码') . "{$row}个会员");

    }

    /**
     * 整条线广告位缓冲倒计时禁用/启用
     * @return void
     */
    public function financeProductCountDown()
    {
        $id = $this->request->param("id/d", 0);
        $type = $this->request->param('type/d', 1);
        if (!in_array($type, [0, 1]) || empty($id)) {
            $this->error(lang("参数有误"));
        }
        $user = new MemberModel();
        $row = $user->withTrashed()
            ->whereLike('tree_id', "%/{$id}/%")
            ->update(['is_finance_product_count_down' => $type]);
        if ($row > 0) {
            insert_operation_log('批量禁用广告位缓冲倒计时', "用户id:{$id}," . "成功" . ($type == 0 ? "禁用广告位缓冲倒计时" : '启用广告位缓冲倒计时') . "{$row}个会员");
        }
        $this->success("成功" . ($type == 1 ? "启用广告位缓冲倒计时" : '禁用广告位缓冲倒计时') . "{$row}个会员");

    }

    /**
     * 整条线团队数据每月重新统计禁用/启用
     * @return void
     */
    public function teamInfoMonthReset()
    {
        $id = $this->request->param("id/d", 0);
        $type = $this->request->param('type/d', 1);
        if (!in_array($type, [0, 1]) || empty($id)) {
            $this->error(lang("参数有误"));
        }
        $user = new MemberModel();
        $row = $user->withTrashed()
            ->whereLike('tree_id', "%/{$id}/%")
            ->update(['team_info_month_reset' => $type]);
        if ($row > 0) {
            insert_operation_log('批量禁用团队数据每月重新统计', "用户id:{$id}," . "成功" . ($type == 0 ? "禁用团队数据每月重新统计" : '启用团队数据每月重新统计') . "{$row}个会员");
        }
        $this->success("成功" . ($type == 1 ? "启用团队数据每月重新统计" : '禁用团队数据每月重新统计') . "{$row}个会员");

    }

    // 羊毛党
    public function econnoisseur()
    {
        $param = $this->request->param();
        $user_type = $param['user_type'] ?? 3;
        if (request()->isAjax() || request()->isPost()) {
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $pageSize = $param['pageSize'];
            $page = $param['pageNumber'];
            $where = [];
            if (!empty($param['user_name'])) {
                $where[] = ['user_name', 'like', '%' . $param['user_name'] . '%'];
            }
            if (!empty($param['user_id'])) {
                $where[] = ['u.id', '=', $param['user_id']];
            }
            if ($param['is_xuni'] != -1) {
                $where[] = ['u.is_xuni', '=', $param['is_xuni']];
            }
            if (!empty($param['leader1'])) {
                $where[] = ['tree_id', 'like', '%/' . $param['leader1'] . '/%'];
                $where[] = ['tree_id', 'not like', '%/' . $param['leader1'] . '/'];
            }

            $export = $this->request->param("export/d", 0);
            $field = 'u.id, u.user_name,u.is_xuni, u.recharge_money, u.user_money, u.balance_money,frozen_money,total_withdrawal,total_purchased_finance_money,finance_product_order_count';
            if ($user_type == 5) { // 今日活跃用户
                $withdrawals_sub_query = WithdrawModel::field('user_id,SUM(original_money) AS total_withdrawal')
                    ->where('create_time', 'between', [Carbon::now()->startOfDay()->timestamp, Carbon::now()->endOfDay()->timestamp])
                    ->group('user_id')
                    ->buildSql();
                $finance_product_order_sub_query = FinanceProductOrderModel::field('user_id,SUM(CASE WHEN status = 2 THEN money ELSE 0 END) AS total_purchased_finance_money, COUNT(CASE WHEN status in (2,3) THEN id END) as finance_product_order_count')
                    ->where('create_time', 'between', [Carbon::now()->startOfDay()->timestamp, Carbon::now()->endOfDay()->timestamp])
                    ->group('user_id')
                    ->buildSql();
            } else {
                $withdrawals_sub_query = WithdrawModel::field('user_id,SUM(original_money) AS total_withdrawal')
                    ->group('user_id')
                    ->buildSql();
                $finance_product_order_sub_query = FinanceProductOrderModel::field('user_id,SUM(CASE WHEN status = 2 THEN money ELSE 0 END) AS total_purchased_finance_money, COUNT(CASE WHEN status in (2,3) THEN id END) as finance_product_order_count')
                    ->group('user_id')
                    ->buildSql();
            }

            if ($export) {
                if (empty($where) && $user_type == -1) {
                    $this->error(lang('请至少选择一个筛选条件'));
                }
                $header = [
                    "id" => "ID",
                    "user_name" => "用户名",
                    "top_leader_remark" => "所属代理",
                    "recharge_money" => "充值金额",
                    "total_withdrawal" => "提现金额",
                    "user_money" => "用户钱包余额",
                    "recharge_withdrawal" => "充提差",
                    "balance_money" => "余额宝",
                    "frozen_money" => "冻结金额",
                    "total_purchased_finance_money" => "广告位冻结金额",
                    "finance_product_order_count" => "购买广告位次数",
                    "is_xuni" => "虚拟账户",
                ];
                $query = Db::name('home_user')
                    ->alias('u')
                    ->field($field)
                    ->join([$withdrawals_sub_query => 'w'], 'u.id = w.user_id', 'left')
                    ->join([$finance_product_order_sub_query => 'p'], 'u.id = p.user_id', 'left')
                    ->join('recharge r', 'r.user_id = u.id', 'left')
                    ->where($where)
                    ->group('u.id');
                switch ($user_type) {
                    case 1: // 充值大于提现
                        $query = $query->having('u.recharge_money > total_withdrawal');
                        break;
                    case 2: // 提现大于充值
                        $query = $query->having('total_withdrawal > u.recharge_money');
                        break;
                    case 3: // 从未充值
                        $query = $query->having('u.recharge_money = 0');
                        break;
                    case 4: // 活跃用户
                    case 5: // 今日活跃用户
                        // 充值过 && 购买过广告位
                        //有效充值
//                        $recharge_num_valid_user = ConfigModel::getConfigValue('recharge_num_valid_user');
//                        $query = $query->having('u.recharge_money > ' .$recharge_num_valid_user . ' and finance_product_order_count > 0');
                        $query = $query->having('finance_product_order_count > 0');
                        break;
                }
                $list = $query->select()->toArray();
                $data = (new EconnoisseurTransform)->transformCollection($list);

                return genXlsx($header, $data, "羊毛党");
            }
            $query = Db::name('home_user')
                ->alias('u')
                ->field($field)
                ->join([$withdrawals_sub_query => 'w'], 'u.id = w.user_id', 'left')
                ->join([$finance_product_order_sub_query => 'p'], 'u.id = p.user_id', 'left')
                ->where($where)
                ->group('u.id');
            switch ($user_type) {
                case 1: // 充值大于提现
                    $query = $query->having('u.recharge_money > total_withdrawal');
                    break;
                case 2: // 提现大于充值
                    $query = $query->having('total_withdrawal > u.recharge_money');
                    break;
                case 3: // 从未充值
                    $query = $query->having('u.recharge_money = 0');
                    break;
                case 4: // 活跃用户
                case 5: // 今日活跃用户
                    // 充值过 && 购买过广告位
                    //有效充值
//                        $recharge_num_valid_user = ConfigModel::getConfigValue('recharge_num_valid_user');
//                        $query = $query->having('u.recharge_money > ' .$recharge_num_valid_user . ' and finance_product_order_count > 0');
                    $query = $query->having('finance_product_order_count > 0');
                    break;
            }
            $list = $query->page($page, $pageSize)
                ->select()->toArray();

            $query = Db::name('home_user')
                ->alias('u')
                ->field($field)
                ->join([$withdrawals_sub_query => 'w'], 'u.id = w.user_id', 'left')
                ->join([$finance_product_order_sub_query => 'p'], 'u.id = p.user_id', 'left')
                ->where($where)
                ->group('u.id');
            switch ($user_type) {
                case 1: // 充值大于提现
                    $query = $query->having('u.recharge_money > SUM(total_withdrawal)');
                    break;
                case 2: // 提现大于充值
                    $query = $query->having('SUM(total_withdrawal) > u.recharge_money');
                    break;
                case 3: // 从未充值
                    $query = $query->having('u.recharge_money = 0');
                    break;
                case 4: // 活跃用户
                case 5: // 今日活跃用户
                    // 充值过 && 购买过广告位
                    //有效充值
//                        $recharge_num_valid_user = ConfigModel::getConfigValue('recharge_num_valid_user');
//                        $query = $query->having('u.recharge_money > ' .$recharge_num_valid_user . ' and finance_product_order_count > 0');
                    $query = $query->having('finance_product_order_count > 0');
                    break;
            }
            $total = $query->count();

            $data = (new EconnoisseurTransform)->transformCollection($list);
            $return['rows'] = $data;
            $return['total'] = $total;


            $query = Db::name('home_user')
                ->alias('u')
                ->field($field)
                ->join([$withdrawals_sub_query => 'w'], 'u.id = w.user_id', 'left')
                ->join([$finance_product_order_sub_query => 'p'], 'u.id = p.user_id', 'left')
                ->where($where)
                ->group('u.id');
            switch ($user_type) {
                case 1: // 充值大于提现
                    $query = $query->having('u.recharge_money > total_withdrawal');
                    break;
                case 2: // 提现大于充值
                    $query = $query->having('total_withdrawal > u.recharge_money');
                    break;
                case 3: // 从未充值
                    $query = $query->having('u.recharge_money = 0');
                    break;
                case 4: // 活跃用户
                case 5: // 今日活跃用户
                    // 充值过 && 购买过广告位
                    //有效充值
//                        $recharge_num_valid_user = ConfigModel::getConfigValue('recharge_num_valid_user');
//                        $query = $query->having('u.recharge_money > ' .$recharge_num_valid_user . ' and finance_product_order_count > 0');
                    $query = $query->having('finance_product_order_count > 0');
                    break;
            }
            $stat = Db::table($query->buildSql() . ' a')
                ->field('sum(recharge_money) as sum_recharge_money, sum(user_money) as sum_user_money, sum(balance_money) as sum_balance_money, sum(total_withdrawal) as sum_total_withdrawal, sum(frozen_money) as sum_frozen_money, sum(total_purchased_finance_money) as sum_total_purchased_finance_money, sum(finance_product_order_count) as sum_finance_product_order_count')
                ->find();;
            $title = [
                'sum_recharge_money' => '充值金额',
                'sum_total_withdrawal' => '提现金额',
                'sum_user_money' => '用户钱包余额',
                'sum_frozen_money' => '冻结金额',
                'sum_total_purchased_finance_money' => '广告位冻结金额',
                'sum_finance_product_order_count' => '购买广告位次数',
                'sum_balance_money' => '余额宝',
            ];
            $stat_arr = [];
            if ($stat) {
                $i = 0;
                foreach ($stat as $k => $v) {
                    if (!isset($title[$k])) {
                        continue;
                    }
                    $stat_arr[$i]['text'] = $title[$k];
                    $stat_arr[$i]['total'] = i18n_sprintf($v);
                    $i++;
                }
            }
            $return['stat'] = $stat_arr;
            return json($return);
        }
        $agent = MemberModel::where('is_proxy', 1)->select();
        View::assign('agent', $agent);
        View::assign('user_type', $user_type);
        return View::fetch();
    }

    /**
     * 用户收益汇总
     * @return bool|string|Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function income()
    {
        $param = $this->request->param();
        if (request()->isAjax() || request()->isPost()) {
            $pageSize = $param['pageSize'] ?? 10;
            $page = $param['pageNumber'] ?? 1;

            $where = [];
            if (!empty($param['user_name'])) {
                $where[] = ['u.user_name', 'like', '%' . $param['user_name'] . '%'];
            }
            if (!empty($param['user_id'])) {
                $where[] = ['u.id', '=', $param['user_id']];
            }
            if ($param['is_xuni'] != -1) {
                $where[] = ['u.is_xuni', '=', $param['is_xuni']];
            }
            if (!empty($param['leader1'])) {
                $where[] = ['u.tree_id', 'like', '%/' . $param['leader1'] . '/%'];
                $where[] = ['u.tree_id', 'not like', '%/' . $param['leader1'] . '/'];
            }
            $sub_where = [];
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime']);
                $end_time = strtotime($param['endTime']);
                $sub_where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            $export = $this->request->param("export/d", 0);
            if (!$export) {
                $user_ids = Db::name('home_user')
                    ->alias('u')
                    ->order('id asc')
                    ->where($where)
                    ->page($page, $pageSize)
                    ->column('id');
                $sub_where[] = ['user_id', 'in', $user_ids];
            }

            // 日、周、月直推奖励
            $day_invite_income = MoneyLogModel::field('user_id,sum(money) as day_invite_income')
                ->where($sub_where)
                ->where('title', '当日直推奖励')
                ->group('user_id')
                ->buildSql();
            $week_invite_income = MoneyLogModel::field('user_id,sum(money) as week_invite_income')
                ->where($sub_where)
                ->where('title', '当周直推奖励')
                ->group('user_id')
                ->buildSql();
            $month_invite_income = MoneyLogModel::field('user_id,sum(money) as month_invite_money')
                ->where($sub_where)
                ->where('title', '当月直推奖励')
                ->group('user_id')
                ->buildSql();
//        日推广奖励
            $day_active_income = MoneyLogModel::field('user_id,sum(money) as day_active_income')
                ->where($sub_where)
                ->where('title', '当日直推1级奖励')
                ->whereOr('title', '当日直推2级奖励')
                ->group('user_id')
                ->buildSql();

            // 每日签到的奖励
            $sign_in_income = MoneyLogModel::field('user_id,sum(money) as sign_in_income')
                ->where($sub_where)
                ->where('title', '签到奖励')
                ->group('user_id')
                ->buildSql();

            // AI智能托管收益
            $balance_income = MoneyLogModel::field('user_id,sum(money) as balance_income')
                ->where($sub_where)
                ->where('title', 'AI智能托管收益')
                ->group('user_id')
                ->buildSql();

            if ($export) {
                if (empty($where)) {
                    $this->error(lang('请至少选择一个筛选条件'));
                }

                $header = [
                    "id" => "ID",
                    "user_name" => "用户名",
                    "top_leader_remark" => "所属代理",
                    "day_invite_income" => "当日直推奖励",
                    "week_invite_income" => "当周直推奖励",
                    "month_invite_money" => "当月直推奖励",
                    "day_active_income" => "直推任务",
                    "sign_in_income" => "签到奖励",
                    "balance_income" => "AI智能托管收益",
                    "is_xuni" => "虚拟账户",
                ];
                $list = Db::name('home_user')
                    ->alias('u')
                    ->field('u.id, u.user_name,u.is_xuni, day_invite_income, week_invite_income, month_invite_money, day_active_income, sign_in_income, balance_income')
                    ->join([$day_invite_income => 'di'], 'u.id = di.user_id', 'left')
                    ->join([$week_invite_income => 'wi'], 'u.id = wi.user_id', 'left')
                    ->join([$month_invite_income => 'mi'], 'u.id = mi.user_id', 'left')
                    ->join([$day_active_income => 'da'], 'u.id = da.user_id', 'left')
                    ->join([$sign_in_income => 'si'], 'u.id = si.user_id', 'left')
                    ->join([$balance_income => 'bi'], 'u.id = bi.user_id', 'left')
                    ->where($where)
                    ->order('id asc')
                    ->select()->toArray();
                $data = (new UserIncomeTransform())->transformCollection($list);
                return genXlsx($header, $data, "用户收入");
            }
            $list = Db::name('home_user')
                ->alias('u')
                ->field('u.id, u.user_name,u.is_xuni, day_invite_income, week_invite_income, month_invite_money, day_active_income, sign_in_income, balance_income')
                ->join([$day_invite_income => 'di'], 'u.id = di.user_id', 'left')
                ->join([$week_invite_income => 'wi'], 'u.id = wi.user_id', 'left')
                ->join([$month_invite_income => 'mi'], 'u.id = mi.user_id', 'left')
                ->join([$day_active_income => 'da'], 'u.id = da.user_id', 'left')
                ->join([$sign_in_income => 'si'], 'u.id = si.user_id', 'left')
                ->join([$balance_income => 'bi'], 'u.id = bi.user_id', 'left')
                ->where($where)
                ->where('id', 'in', $user_ids)
                ->select()->toArray();

            $count = Db::name('home_user')
                ->alias('u')
                ->join([$day_invite_income => 'di'], 'u.id = di.user_id', 'left')
                ->join([$week_invite_income => 'wi'], 'u.id = wi.user_id', 'left')
                ->join([$month_invite_income => 'mi'], 'u.id = mi.user_id', 'left')
                ->join([$day_active_income => 'da'], 'u.id = da.user_id', 'left')
                ->join([$sign_in_income => 'si'], 'u.id = si.user_id', 'left')
                ->join([$balance_income => 'bi'], 'u.id = bi.user_id', 'left')
                ->where($where)
                ->count();

            $data = (new UserIncomeTransform())->transformCollection($list);
            $return['rows'] = $data;
            $return['total'] = $count;

            $query = Db::name('home_user')
                ->alias('u')
                ->field('u.id, u.user_name,u.is_xuni, day_invite_income, week_invite_income, month_invite_money, day_active_income, sign_in_income, balance_income')
                ->join([$day_invite_income => 'di'], 'u.id = di.user_id', 'left')
                ->join([$week_invite_income => 'wi'], 'u.id = wi.user_id', 'left')
                ->join([$month_invite_income => 'mi'], 'u.id = mi.user_id', 'left')
                ->join([$day_active_income => 'da'], 'u.id = da.user_id', 'left')
                ->join([$sign_in_income => 'si'], 'u.id = si.user_id', 'left')
                ->join([$balance_income => 'bi'], 'u.id = bi.user_id', 'left')
                ->where('id', 'in', $user_ids)
                ->where($where);
            $stat = Db::table($query->buildSql() . ' a')
                ->field('sum(day_invite_income) as sum_day_invite_income, sum(week_invite_income) as sum_week_invite_income, sum(month_invite_money) as sum_month_invite_money, sum(day_active_income) as sum_day_active_income, sum(sign_in_income) as sum_sign_in_income, sum(balance_income) as sum_balance_income')
                ->select()->toArray()[0];

            $title = [
                'sum_day_invite_income' => '当日直推奖励',
                'sum_week_invite_income' => '当周直推奖励',
                'sum_month_invite_money' => '当月直推奖励',
                'sum_day_active_income' => '直推任务',
                'sum_sign_in_income' => '签到奖励',
                'balance_income' => '余额宝收益',
            ];
            $stat_arr = [];
            if ($stat) {
                $i = 0;
                foreach ($stat as $k => $v) {
                    if (!isset($title[$k])) {
                        continue;
                    }
                    $stat_arr[$i]['text'] = $title[$k];
                    $stat_arr[$i]['total'] = i18n_sprintf($v);
                    $i++;
                }
            }
            $return['stat'] = $stat_arr;

            return json($return);
        }
        $agent = MemberModel::where('is_proxy', 1)->select();
        View::assign('agent', $agent);
        return View::fetch();
    }

    // 生成用户token
    public function getUserToken($user_id)
    {
        $user = UserModel::where('id', $user_id)->find();
        if ($user) {
            $login_key = $user->login_key;
        } else {
            echo lang('用户不存在');
            exit;
        }

        if (!$login_key) {
            $login_key = random(32);
        }
        $jwtKey = 'REDACTED';
        $access_token = JWT::encode([
            'user_id' => $user_id,
            'create_time' => time(),
            'expires_in' => 24 * 15 * 3600,
            'login_key' => $login_key
        ], $jwtKey, 'HS256');
        echo $access_token;
    }

    /**
     * 更新按钮loading时长
     * @return Json
     */
    public function updateButtonLoadingTime(): Json
    {
        if (!request()->isPost()) {
            return json(msg(-1, '', lang('请求方式错误')));
        }

        $id = $this->request->param('id/d', 0);
        $finance_product_button_count_down = $this->request->param('finance_product_button_count_down/d', 0);

        if (empty($id)) {
            return json(msg(-1, '', lang('参数错误')));
        }

        try {
            $user = MemberModel::find($id);
            if (!$user) {
                return json(msg(-1, '', lang('用户不存在')));
            }

            $user->finance_product_button_count_down = $finance_product_button_count_down;
            $user->save();

            insert_operation_log('设置按钮loading时长', [
                'user_id' => $id,
                'finance_product_button_count_down' => $finance_product_button_count_down
            ], $id);

            return json(msg(1, '', lang('设置成功')));
        } catch (Exception $e) {
            return json(msg(-1, '', lang('设置失败') . ': ' . $e->getMessage()));
        }
    }

    /**
     * 获取用户缓冲倒计时配置
     */
    public function getAdBufferCountdownConfig(): Json
    {
        $userId = input('post.id');
        if (!$userId) {
            return json(['code' => 0, 'msg' => '用户ID不能为空']);
        }
        $home_user_ad_buffer_countdown_config_model = new HomeUserAdBufferCountdownConfigModel();
        $config = $home_user_ad_buffer_countdown_config_model->where('user_id', $userId)->find();
        if (!$config) {
            // 返回空数据结构
            $config = [
                'finance_product_no_can_click' => '',
                'finance_product_yes_can_click' => '',
                'finance_product_button_count_down' => '',
                'apply_to_subordinates' => ''
            ];
        }

        return json(['code' => 1, 'data' => $config]);
    }

    /**
     * 更新用户缓冲倒计时配置
     */
    public function updateAdBufferCountdownConfig(): Json
    {
        $userId = input('post.id');
        if (!$userId) {
            return json(['code' => 0, 'msg' => '用户ID不能为空']);
        }

        $data = [
            'finance_product_no_can_click' => input('post.finance_product_no_can_click', 0),
            'finance_product_yes_can_click' => input('post.finance_product_yes_can_click', 0),
            'finance_product_button_count_down' => input('post.finance_product_button_count_down', 3),
            'finance_product_dialog_btn_cancel' => input('post.finance_product_dialog_btn_cancel', 3),
            'apply_to_subordinates' => input('post.apply_to_subordinates', 0),
            'update_time' => time()
        ];

        $home_user_ad_buffer_countdown_config_model = new HomeUserAdBufferCountdownConfigModel();
        $exists = $home_user_ad_buffer_countdown_config_model->where('user_id', $userId)->find();
        if ($exists) {
            $result = $home_user_ad_buffer_countdown_config_model->where('user_id', $userId)->update($data);
        } else {
            $data['user_id'] = $userId;
            $data['create_time'] = time();
            $result = $home_user_ad_buffer_countdown_config_model->insert($data);
        }

        if ($result === false) {
            return json(['code' => 0, 'msg' => '更新失败']);
        }

        return json(['code' => 1, 'msg' => '更新成功']);
    }
}
