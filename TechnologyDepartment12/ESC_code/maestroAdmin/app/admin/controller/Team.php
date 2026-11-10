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
use app\admin\model\WithdrawModel;
use app\common\model\AdPlanModel;
use app\common\model\FinancePlanModel;
use app\common\model\FinanceProductOrderModel;
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
use app\common\repository\MoneyLogRepository;
use app\common\repository\RechargeRepository;
use app\common\repository\WithdrawalRepository;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\View;
use think\facade\Queue;

class Team extends Base
{

    // 用户列表
    public function index()
    {
        $id = $this->request->param("id/d", 0);
        $level = $this->request->param('level/d', 1);
        $level_id = $this->request->param('level_id/d', '');
        $home_user_repository = new HomeUserRepository();
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
                $where[] = ['user_name', 'like', '%'.$param['user_name'].'%'];
            }
            if (!empty($param['invite_code'])) {
                $where[] = ['invite_code', 'like', '%'.$param['invite_code'].'%'];
            }
            if ($param['level_id'] != '') {
                $where[] = ['level_id', '=', $param['level_id']];
            }
            if (!empty($param['ip'])) {
                $where[] = ['last_login_ip|ip', '=', $param['ip']];
            }
            if ($id > 0 && ($level >= 1 && $level <= 7)) {
                $where[] = ["leader{$level}", '=', $id];
            } elseif ($id > 0 && $level == 99) {
                $where[] = ['tree_id', 'like', "%/{$id}/%"];
                $where[] = ['tree_id', 'not like', '%/'.$id.'/'];
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
            $orderby = ['team_count' =>  'DESC', 'id' => 'DESC'];
            //            if (!empty($param['sortName']) && in_array($param['sortName'],
            //                    ['last_login_time', 'user_money', 'level_id'])) {
            //                $orderby = [$param['sortName'] => ($param['sortOrder'] == "asc" ? "ASC" : "DESC")];
            //            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime']." 00:00:00");
                $end_time = strtotime($param['endTime']." 23:59:59");
                if ($time_type == 1) {
                    $where[] = ['last_login_time', 'BETWEEN', [$start_time, $end_time]];
                } else {
                    $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
                }
            }

            //            $where[] = ['leader1', '=', 0];
            $where[] = ['is_proxy', '=', 1];
            $user = new MemberModel();
            $selectResult = $user->getUsersByWhere($where, $offset, $limit, $orderby);
            $level_name = LevelModel::column("level_name", 'level_id');

            //得到十天内的时间戳
            $last_ten_day_start_time = strtotime(date('Y-m-d', strtotime('-10 day')));
            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                // 在线状态
                $online_status = time() - strtotime($vo['last_login_time']) < 300;
                $selectResult[$key]['online_status'] = $online_status;
                $selectResult[$key]['is_xuni_text'] = $vo['is_xuni'] == 1 ? '<span style="color:red">是</span>' : '否';
//                                        $selectResult[$key]['create_time'] = $vo['create_time'] > 0 ? i18n_datetime2str($vo['create_time']) : '-';
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
                $ip_count = count($vo['user_ip']);
                if ($ip_count > 0) {
                    $ip_count -= 1;
                }
                $selectResult[$key]['ip_count'] = $ip_count;

                $reg_ip = "";
                if ($vo['ip'] && $vo['ip'] != '0.0.0.0') {
                    $reg_ip .= $vo['ip'];
                    $reg_ip .= '('.ip2location($vo['ip']).')';
                }
                $login_ip = '';
                if ($vo['last_login_ip']) {
                    $login_ip .= $vo['last_login_ip'];
                    $login_ip .= '('.ip2location($vo['last_login_ip']).')';
                }
                //                $withdraw = Db::name('withdrawals')
                //                    ->where('user_id', $vo['id'])
                //                    ->where('status', 4)
                //                    ->sum('original_money');
                //                $selectResult[$key]['withdraw'] = i18n_sprintf($withdraw);
                $selectResult[$key]['user_money'] = i18n_sprintf($vo['user_money']);
                $selectResult[$key]['reg_ip'] = $reg_ip;
                $selectResult[$key]['login_ip'] = $login_ip;
                $selectResult[$key]['plan_name'] = FinancePlanModel::getPlanName($vo['finance_plan_id']);

                // 获取用户vip信息
                $user_level = MemberLevelModel::where('user_id', $vo['id'])->where('level_end_time', '>',
                    time())->find();
                if ($user_level) {
                    $selectResult[$key]['level_star_time'] = $user_level->create_time;
                    $selectResult[$key]['level_end_time'] = date('Y-m-d H:i:s', $user_level->level_end_time);
                } else {
                    $selectResult[$key]['level_star_time'] = '';
                    $selectResult[$key]['level_end_time'] = '';
                }
                // 获取下级用户id
                $team_ids = UserModel::where('tree_id', 'like', "%/{$vo['id']}/%")
                    ->where('tree_id', 'not like', '%/'.$vo['id'].'/')
                    ->where("is_xuni", 0)
                    ->column('id');
                $team_ids_str = implode(',', $team_ids);
//                var_dump($team_ids_str);die();
                // 所在层级
                preg_match_all("/\d+/", $vo['tree_id'], $matches);
                $count = count($matches[0]) - 1;
                $selectResult[$key]['tree_level'] = $count;

                $selectResult[$key]['lang'] = $vo['lang'] ?? '- -';

                $recharge_sum = 0;
                $recharge_sum_bank = 0;
                $recharge_sum_contract = 0;
                $recharge_sum_third = 0;
                $withdrawal_sum = 0;
                $user_money = 0;
                $last_ten_days = 0;
                $sup_to_sub_money = 0;
                $withdrawal_no_pay = 0;
                if ($team_ids_str) {
                    // 获取下级充值总额
                    $recharge_sum = RechargeModel::where('user_id in ('.$team_ids_str.')')->where('status',
                        RechargeModel::STATUS_SUCCESS)->sum('money');
                    // 手动补单
                    $recharge_sum_bank = RechargeModel::where('user_id in ('.$team_ids_str.')')
                        ->where('status', RechargeModel::STATUS_SUCCESS)
                        ->where('type', RechargeModel::TYPE_BANK)
                        ->where('from', RechargeRepository::FROM_ADMIN)
                        ->sum('money');
                    // 合约
                    $recharge_sum_contract = RechargeModel::where('user_id in ('.$team_ids_str.')')
                        ->where('status', RechargeModel::STATUS_SUCCESS)
                        ->where('type', RechargeModel::TYPE_CONTRACT)
                        ->sum('money');
                    // 三方 = 三方充值总额+合约+线下
                    $recharge_sum_third = RechargeModel::where('user_id in ('.$team_ids_str.')')
                        ->where('status', RechargeModel::STATUS_SUCCESS)
                        ->where('from', RechargeRepository::FROM_FRONT)
                        ->where('type', 'in', [RechargeModel::TYPE_THIRD, RechargeModel::TYPE_BANK])
                        ->sum('money');

                    // 获取下级提现总额
                    $withdrawal_sum = Db::name('withdrawals')->where('user_id in ('.$team_ids_str.')')
                        ->where('status', 4)
                        ->sum('original_money');

                    $withdrawal_no_pay = Db::name('withdrawals')->where('user_id in ('.$team_ids_str.')')
                        ->where('status', 1)
                        ->sum('original_money');

                    $user_money = $user->where('id in ('.$team_ids_str.')')->sum('user_money');
                    $user_money += $home_user_repository->getTeamFreezeMoney($team_ids);

                    $last_ten_days = FinanceProductOrderModel::where('user_id', 'in', $team_ids)
                        ->where('create_time', '>', strtotime('-3 days'))
                        ->group('user_id')
                        ->count();
                    // FIXME
                    // 获取流量包待结算的金额
                    // 获取AI托管待结算的金额

                    $sup_to_sub_money = MoneyLogModel::where("user_id","in", $team_ids)
                        ->where("from_user_id",$vo['id'] )//上级ID
                        ->where("status", 1)
                        ->sum("money");
                }

                // 获取下级余额总额
                $selectResult[$key]['sup_to_sub_money'] = i18n_sprintf($sup_to_sub_money);// 上级转下级金额
                $selectResult[$key]['team_money'] = i18n_sprintf($user_money);// 下级余额 总的+冻结的
                $selectResult[$key]['recharge_sum'] = i18n_sprintf($recharge_sum);
                $selectResult[$key]['recharge_sum_bank'] = i18n_sprintf($recharge_sum_bank);
                $selectResult[$key]['recharge_sum_contract'] = i18n_sprintf($recharge_sum_contract);
                $selectResult[$key]['recharge_sum_third'] = i18n_sprintf($recharge_sum_third);

                $selectResult[$key]['withdrawal_sum'] = i18n_sprintf($withdrawal_sum);
                $selectResult[$key]['withdrawal_no_pay'] = i18n_sprintf($withdrawal_no_pay);
                $selectResult[$key]['last_ten_days'] = $last_ten_days;// 近10天的有效人数

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
//            'num' => $user->where('is_proxy', 1)->count()
//        ];
//        foreach ($levelList as $value) {
//            $value['num'] = $user->where(['level_id' => $value['level_id'], 'is_proxy' => 1])->count();
//            $levelListArr[] = $value;
//        }
        View::assign('level_id', $level_id);
        View::assign('id', $id);
        View::assign('info', $info);
//        View::assign('levelAll', $levelListArr);
        View::assign('export_url', url('export', ['id' => $id, 'level' => $level]));
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
            if ($id > 0 && ($level >= 1 && $level <= 7)) {
                $where[] = ["leader{$level}", '=', $id];
            } elseif ($id > 0 && $level == 99) {
                $where[] = ['tree_id', 'like', "%/{$id}/%"];
                $where[] = ['tree_id', 'not like', '%/'.$id. '/'];
            }
            $orderby = ['team_count' => 'DESC'];

            $user = new MemberModel();
            $selectResult = $user->getUsersByWhere($where, $offset, $limit, $orderby);
            $level_name = LevelModel::column("level_name", 'level_id');
            $money_repository = new MoneyLogRepository();
            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                // 在线状态
                $online_status = time() - strtotime($vo['last_login_time']) < 300;
                $selectResult[$key]['online_status'] = $online_status;
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
                $withdraw = Db::name('withdrawals')
                    ->where('user_id', $vo['id'])
                    ->where('status', 4)
                    ->sum('original_money');
                $selectResult[$key]['withdraw'] = i18n_sprintf($withdraw);
                $selectResult[$key]['user_money'] = i18n_sprintf($vo['user_money']);
                $selectResult[$key]['reg_ip'] = $reg_ip;
                $selectResult[$key]['login_ip'] = $login_ip;
                $selectResult[$key]['plan_name'] = FinancePlanModel::getPlanName($vo['finance_plan_id']);

                // 获取用户vip信息
                $user_level = MemberLevelModel::where('user_id', $vo['id'])->where('level_end_time', '>', time())->find();
                if ($user_level) {
                    $selectResult[$key]['level_star_time'] = $user_level->create_time;
                    $selectResult[$key]['level_end_time'] = date('Y-m-d H:i:s', $user_level->level_end_time);
                } else {
                    $selectResult[$key]['level_star_time'] = '';
                    $selectResult[$key]['level_end_time'] = '';
                }

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
                $product_frozen_money = $finance_product_price + $investment_product_money;
                $selectResult[$key]['product_frozen_money'] = i18n_sprintf($product_frozen_money);

                // 获取下级用户id
                $team_ids = UserModel::where('tree_id', 'like', "%/{$vo['id']}/%")->where('tree_id', 'not like', '%/'.$vo['id'].'/')->column('id');
                $team_ids_str = implode(',', $team_ids);


                // 所在层级
                preg_match_all("/\d+/", $vo['tree_id'], $matches);
                $count = count($matches[0])-1;
                $selectResult[$key]['tree_level'] = $count;

                $selectResult[$key]['lang'] = $vo['lang'] ?? '- -';

                // 最后广告位购买时间
                $last_finance_product_buy_time = FinanceProductOrderModel::where('user_id', $vo['id'])->where('buy_time', '>', 0)->order('id DESC')->value('buy_time');
                $selectResult[$key]['last_finance_product_buy_time'] = $last_finance_product_buy_time ? i18n_datetime2str($last_finance_product_buy_time) : '';


                $recharge_sum = 0;
                $recharge_sum_bank = 0;
                $recharge_sum_contract = 0;
                $recharge_sum_third = 0;
                $withdrawal_sum = 0;
                $user_money = 0;
                $recharge_sum_all = 0;

                if ($team_ids_str) {
                    // 获取下级充值总额(三方)
                    $recharge_sum = RechargeModel::where('user_id in ('. $team_ids_str . ')')
                        ->where('type','=', RechargeModel::TYPE_THIRD)
                        ->where('status', RechargeModel::STATUS_SUCCESS)->sum('money');

                    $recharge_sum_all = RechargeModel::where('user_id in ('. $team_ids_str . ')')->where('status', RechargeModel::STATUS_SUCCESS)->sum('money');

                    // 手动补单
                    $recharge_sum_bank = RechargeModel::where('user_id in ('. $team_ids_str . ')')
                        ->where('status', RechargeModel::STATUS_SUCCESS)
                        ->where('type', RechargeModel::TYPE_BANK)
                        ->where('from', RechargeRepository::FROM_ADMIN)
                        ->sum('money');
                    // 合约
                    $recharge_sum_contract = RechargeModel::where('user_id in ('. $team_ids_str . ')')
                        ->where('status', RechargeModel::STATUS_SUCCESS)
                        ->where('type', RechargeModel::TYPE_CONTRACT)
                        ->sum('money');
                    // 三方
                    $recharge_sum_third = RechargeModel::where('user_id in ('. $team_ids_str . ')')
                        ->where('status', RechargeModel::STATUS_SUCCESS)
                        ->where('type', RechargeModel::TYPE_THIRD)
                        ->sum('money');

                    // 获取下级提现总额
                    $withdrawal_sum = Db::name('withdrawals')->where('user_id in ('. $team_ids_str . ')')
                        ->where('status',4)
                        ->sum('original_money');

                    $user_money = $user->where('id in ('. $team_ids_str . ')')->sum('user_money') +
                        $user->where('id in ('. $team_ids_str . ')')->sum('frozen_money');//增加冻结金额
                    // 获取流量包待结算的金额
                    // 获取AI托管待结算的金额
                }

                // 获取下级余额总额
                $selectResult[$key]['team_money'] = i18n_sprintf($user_money);
                $selectResult[$key]['recharge_sum'] = i18n_sprintf($recharge_sum);
                $selectResult[$key]['recharge_sum_all'] = i18n_sprintf($recharge_sum_all);
                $selectResult[$key]['recharge_sum_bank'] = i18n_sprintf($recharge_sum_bank);
                $selectResult[$key]['recharge_sum_contract'] = i18n_sprintf($recharge_sum_contract);
                $selectResult[$key]['recharge_sum_third'] = i18n_sprintf($recharge_sum_third);

                $selectResult[$key]['withdrawal_sum'] = i18n_sprintf($withdrawal_sum);
                $selectResult[$key]['recharge_money'] = i18n_sprintf($vo['recharge_money']);
                $selectResult[$key]['frozen_money'] = i18n_sprintf($vo['frozen_money']);

                $selectResult[$key]['receive_from_other_user'] = i18n_sprintf($money_repository->getTotalMoney([ $vo['id']], [23])); // 23-收到其他用户转账
            }
            $return['total'] = $user->getAllUsers($where);  //总数据
            $return['rows'] = $selectResult;
            return json($return);
        }

//        代理下级顶部统计开始
        $user = new MemberModel();
        $info = $user->getOneUser($id);
        if ($level == 99) {
            $team_ids = UserModel::where('tree_id', 'like', "%/{$id}/%")->where('tree_id', 'not like', '%/'.$id.'/')->column('id');
        } else {
            $where["leader{$level}"] = $id;
            $team_ids = UserModel::where($where)->column('id');
        }

        $home_user_repository = new HomeUserRepository();
        // 循环7级，获取级别总人数
        $level_7_team_count = (new HomeUserRepository())->getLevel7TeamCount($id);

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


        View::assign('level_7_team_count', $level_7_team_count);


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


        View::assign('level', $level);
        View::assign('id', $id);
        View::assign('info', $info);
        View::assign('url', url('next_index', ['id' => $id, 'level' => $level])->build());
        View::assign('export_file', url('export_file', ['id' => $id, 'level' => $level]));
        return View::fetch();
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
        if ($id > 0 && ($level >= 1 && $level <= 7)) {
            $where[] = ["leader{$level}", '=', $id];
        } elseif ($id > 0 && $level == 99) {
            $where[] = ['tree_id', 'like', "%/{$id}/%"];
            $where[] = ['tree_id', 'not like', '%/'.$id. '/'];
        }
        if (isset($param['level_id']) && $param['level_id'] != '') {
            $where[] = ['level_id', '=', $param['level_id']];
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
        } else {
            $this->error(lang('请选择时间'));
        }
        $user = new MemberModel();
        $list = $user->getAllUsersByWhere($where, $orderby);
        $status = config('app.user_status');
        $recharge = Db::name('recharge')->where("status", 2)
            ->where("type", RechargeModel::TYPE_THIRD)
            ->group("user_id")->column("sum(money)", "user_id");
        $recharge_all = Db::name('recharge')->where("status", 2)->group("user_id")->column("sum(money)", "user_id");

        $withdrawals = Db::name('withdrawals')->where("status", 2)->group("user_id")->column("sum(money)", "user_id");
        // 拼装参数
        foreach ($list as $key => $vo) {
            $list[$key]['is_xuni'] = lang($vo['is_xuni'] == 1 ? '是' : '否');
            $list[$key]['last_login_time'] = date('Y-m-d H:i:s', $vo['last_login_time']);
            $list[$key]['status'] = $status[$vo['status']];
            if ($vo['delete_time']) {
                $list[$key]['status'] = lang('已删除');
            }
            $list[$key]['recharge'] = isset($recharge[$vo['id']]) ? intval($recharge[$vo['id']]) : 0;
            $list[$key]['recharge_all'] = isset($recharge_all[$vo['id']]) ? intval($recharge_all[$vo['id']]) : 0;
            $list[$key]['withdraw'] = isset($withdrawals[$vo['id']]) ? intval($withdrawals[$vo['id']]) : 0;
            $list[$key]['user_money'] = i18n_sprintf($vo['user_money']);
        }
        $header = [
            "id" => "ID",
            "invite_code" => "邀请码",
            "mobile" => "手机号",
            "user_money" => "余额",
            "recharge" => "充值总额",//三方充值总额
            "recharge_all" => "实际充值总额",//三方+后台充值总额+合约
            "withdraw" => "提现总额",
            "leader1" => "上级ID",
            "is_xuni" => "虚拟账户",
            "create_time" => "注册时间",
            "ip" => "注册IP",
            "last_login_time" => "最后登录时间",
            "last_login_ip" => "最后登录IP",
            "status" => "状态",
        ];
        return genCsv($header, $list, "用户列表");
    }

    public function batch()
    {
        $id = $this->request->param("id/d", 0);
        $type = $this->request->param('type/d', 1);
        if (!in_array($type, [1, 2]) || empty($id)) {
            $this->error(lang("参数有误"));
        }
        $user = new MemberModel();
        $row = $user->withTrashed()->whereLike('tree_id', "%/{$id}/%")->update(['status' => $type == 1 ? 2 : 1]);
        if ($row > 0) {
            // 同时更新 login_key
            $user->withTrashed()->whereLike('tree_id', "%/{$id}/%")->update(['login_key', random(32)]);
            insert_operation_log('批量解封/封停', "用户id:{$id}," . "成功" . ($type == 1 ? "封停" : '解封') . "{$row}个会员");
        }
        $this->success("成功" . ($type == 1 ? "封停" : '解封') . "{$row}个会员");
    }

    // 添加用户
    public function userAdd()
    {
        if (request()->isPost()) {
            $param = input('post.');
            // 判断账号类型
            if ($param['user_type'] == 1) { // 邮箱
                // 检查提交的是不是邮箱
                if (!filter_var($param['user_name'], FILTER_VALIDATE_EMAIL)) {
                    $this->error(lang('请输入正确的邮箱格式'));
                }
                $param['mobile'] = '';
            }
            if ($param['user_type'] == 2) { // 手机
                $param['phone_code'] = "+52";
                $param['mobile'] = $param['user_name'];
                $exist = MemberModel::where('mobile', $param['mobile'])->find();
                if ($exist) {
                    $this->error(lang('账号已存在'));
                }
                // TODO 判断手机号格式
//                $this->error(lang('后台不支持新增手机账号'));
            }

            if (empty($param['password'])) {
                $this->error(lang('请填写密码'));
            }

            // 判断用户名是否已存在
            $exist = MemberModel::where('user_name', $param['user_name'])->find();
            if ($exist) {
                $this->error(lang('账号已存在'));
            }

            if ($param['paypwd']) {
                $param['paypwd'] = md5($param['paypwd'] . config('salt'));
            } else {
                $param['paypwd'] = md5("123456" . config('salt'));
            }

            $user = new MemberModel();
            if ($param['invite_code']) {
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

            $param['password'] = md5($param['password'] . config('salt'));
            $param['avatar'] = mt_rand(1, 100) . '.png';
            $param['invite_code'] = generateInviteCode();
            $param['create_time'] = time();
            $param['ip'] = getip();;

            $flag = $user->insertUser($param);
            if ($flag['code'] == 1) {
                $id = MemberModel::where('mobile', $param['mobile'])->value('id');
                updateTreeById2($id);
                // 判断会员等级
                if ($user->leader1) {
                    checkLevel($user['leader1']); // 会员升级
                }
                insert_operation_log('新增会员', "用户id:{$id}", $id);
            }
            return json(msg($flag['code'], $flag['data'], lang($flag['msg'])));
        }
        // 获取语言
        $langs = getLangs(1);
        View::assign('langs', $langs);
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
        $uid = input('uid/d');
        View::assign('uid', $uid);
        View::assign('money_type', $money_type);
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
            $leader1_info = MemberModel::where('id', $leader1)->find();
            if (!$leader1_info) {
                $this->error(lang("修改的上级ID有误"));
            }
            insert_operation_log('修改会员上级id', "用户ID:{$id},原上级ID:{$user->leader1},修改的上级ID:{$leader1}");
            $user->leader1 = $leader1;
            $user->save();
//            Queue::push("ChangeLeader1", $id);
            Queue::push("ChangeLeaderV2", ['user_id'=>$id, 'tree_id'=>$leader1_info->tree_id."{$id}/"]);
            $redis->set($redis_key, 1);
            $this->success(lang("修改成功"));
        }

        $child_count = MemberModel::whereLike('tree_id', "%/{$id}/%")->count();
        View::assign('user', $user);
        View::assign('child_count', $child_count);
        return View::fetch();
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
//            '详情' => [
//                'auth'     => 'member/useredit',
//                'href'     => url('member/userEdit', ['id' => $id]),
//                'btnStyle' => 'primary',
//            ],
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
            $result[$key]['user_ip_count'] = '<span style="color: #0c80fe;cursor: pointer" class="ip-count" data-ip="'. $vo['ip'] .'" data-user_id="'. $vo['user_id'] .'">'.$ip_count.'</span>';
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
     * 导出文件
     * @throws DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function export_file()
    {
        header('X-Accel-Buffering: no');
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');

        $id = $this->request->param("id/d", 0);
        $level = $this->request->param('level/d', 99);
        $param = $this->request->param();
        $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
        $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
        $limit = $param['pageSize'];
        $offset = ($param['pageNumber'] - 1) * $limit;
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
        if ($id > 0 && ($level >= 1 && $level <= 7)) {
            $where[] = ["leader{$level}", '=', $id];
        } elseif ($id > 0 && $level == 99) {
            $where[] = ['tree_id', 'like', "%/{$id}/%"];
            $where[] = ['tree_id', 'not like', '%/'.$id. '/'];
        }
        $orderby = ['team_count' => 'DESC'];

        $user = new MemberModel();
        $selectResult = $user->getAllUsersByWhere($where, $orderby)->toArray();
        $level_name = LevelModel::column("level_name", 'level_id');
        foreach ($selectResult as $key => $vo) {
            if ($key % 50 == 0) {
                $item_data = [
                    'item' => $key
                ];
                echo "data: " . json_encode($item_data) . "\n\n";
                ob_flush();
                flush();
            }
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
            $withdraw = Db::name('withdrawals')
                ->where('user_id', $vo['id'])
                ->where('status', 4)
                ->sum('original_money');
            $selectResult[$key]['withdraw'] = i18n_sprintf($withdraw);
            $selectResult[$key]['user_money'] = i18n_sprintf($vo['user_money']);
            $selectResult[$key]['reg_ip'] = $reg_ip;
            $selectResult[$key]['login_ip'] = $login_ip;
            $selectResult[$key]['plan_name'] = FinancePlanModel::getPlanName($vo['finance_plan_id']);
            // 在线状态
            $online_status = time() - strtotime($vo['last_login_time']) < 300;
            $selectResult[$key]['online_status'] = $online_status;

            // 获取用户vip信息
            $user_level = MemberLevelModel::where('user_id', $vo['id'])->where('level_end_time', '>', time())->find();
            if ($user_level) {
                $selectResult[$key]['level_star_time'] = $user_level->create_time;
                $selectResult[$key]['level_end_time'] = date('Y-m-d H:i:s', $user_level->level_end_time);
            } else {
                $selectResult[$key]['level_star_time'] = '';
                $selectResult[$key]['level_end_time'] = '';
            }

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
            $product_frozen_money = $finance_product_price + $investment_product_money;
            $selectResult[$key]['product_frozen_money'] = i18n_sprintf($product_frozen_money);

            // 获取下级用户id
            $team_ids = UserModel::where('tree_id', 'like', "%/{$vo['id']}/%")->where('tree_id', 'not like', '%/'.$vo['id'].'/')->column('id');
            $team_ids_str = implode(',', $team_ids);

            // 所在层级
            preg_match_all("/\d+/", $vo['tree_id'], $matches);
            $count = count($matches[0])-1;
            $selectResult[$key]['tree_level'] = $count;

            $selectResult[$key]['lang'] = $vo['lang'] ?? '- -';

            // 最后广告位购买时间
            $last_finance_product_buy_time = FinanceProductOrderModel::where('user_id', $vo['id'])->where('buy_time', '>', 0)->order('id DESC')->value('buy_time');
            $selectResult[$key]['last_finance_product_buy_time'] = $last_finance_product_buy_time ? i18n_datetime2str($last_finance_product_buy_time) : '';


            $recharge_sum = 0;
            $recharge_sum_bank = 0;
            $recharge_sum_contract = 0;
            $recharge_sum_third = 0;
            $withdrawal_sum = 0;
            $user_money = 0;

            if ($team_ids_str) {
                // 获取下级充值总额
                $recharge_sum = RechargeModel::where('user_id in ('. $team_ids_str . ')')->where('status', RechargeModel::STATUS_SUCCESS)->sum('money');
                // 手动补单
                $recharge_sum_bank = RechargeModel::where('user_id in ('. $team_ids_str . ')')
                    ->where('status', RechargeModel::STATUS_SUCCESS)
                    ->where('type', RechargeModel::TYPE_BANK)
                    ->where('from', RechargeRepository::FROM_ADMIN)
                    ->sum('money');
                // 合约
                $recharge_sum_contract = RechargeModel::where('user_id in ('. $team_ids_str . ')')
                    ->where('status', RechargeModel::STATUS_SUCCESS)
                    ->where('type', RechargeModel::TYPE_CONTRACT)
                    ->sum('money');
                // 三方
                $recharge_sum_third = RechargeModel::where('user_id in ('. $team_ids_str . ')')
                    ->where('status', RechargeModel::STATUS_SUCCESS)
                    ->where('type', RechargeModel::TYPE_THIRD)
                    ->sum('money');

                // 获取下级提现总额
                $withdrawal_sum = Db::name('withdrawals')->where('user_id in ('. $team_ids_str . ')')
                    ->where('status',4)
                    ->sum('original_money');

                $user_money = $user->where('id in ('.$team_ids_str.')')->sum('user_money');
                // 获取流量包待结算的金额
                // 获取AI托管待结算的金额
            }

            // 获取下级余额总额
            $selectResult[$key]['team_money'] = i18n_sprintf($user_money);
            $selectResult[$key]['recharge_sum'] = i18n_sprintf($recharge_sum);//手动充值

            $selectResult[$key]['recharge_sum_bank'] = i18n_sprintf($recharge_sum_bank);
            $selectResult[$key]['recharge_sum_contract'] = i18n_sprintf($recharge_sum_contract);
            $selectResult[$key]['recharge_sum_third'] = i18n_sprintf($recharge_sum_third);

            $selectResult[$key]['withdrawal_sum'] = i18n_sprintf($withdrawal_sum);

            $selectResult[$key]['recharge_money'] = i18n_sprintf($selectResult[$key]['recharge_money']);
        }
        $header = [
            'id'                            => '用户ID',
            'user_name'                     => '用户账号',
            'user_money'                    => '余额',
            'tree_level'                    => '所在层级',
            'credit_score'                  => '信用分',
            'create_time'                   => '注册时间',
            'last_finance_product_buy_time' => '最后一次交易时间',
            'recharge_money'                => '充值总额',
            'withdraw'                      => '提现总额',
            'product_frozen_money'          => '冻结资金',
            'team_count'                    => '团队人数',
            'team_money'                    => '下级余额',
        ];

        $save_path = public_path().'storage/export/team/'.date('Ymd').'/';
        if (!is_dir($save_path)) {
            mkdir($save_path, 0777, true);
        }

        $file_name = genXlsx($header, $selectResult, "团队下级用户列表_".$id.'_'.genOrderSN(), "用户列表".date("Ymd"),
            1, true, $save_path);
        $file_path = str_replace(public_path().'storage', "", $save_path).$file_name;
        $file_data = [
            'url'       => toMedia($file_path, 1),
            'file_name' => $file_name
        ];
        echo "event: stream-end\n";
        ob_flush();
        flush();
        echo "data: ".json_encode($file_data)."\n\n";
        ob_flush();
        flush();
    }
}
