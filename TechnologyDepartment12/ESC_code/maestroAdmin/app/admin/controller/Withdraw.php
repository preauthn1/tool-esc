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
use app\admin\model\UserModel;
use app\admin\model\WithdrawModel;
use app\admin\model\UserMessageModel;
use app\admin\transform\MemberTransform;
use app\common\model\ConfigModel;
use app\common\model\FinanceProductOrderModel;
use app\common\model\InvestmentProductOrderModel;
use app\common\model\MoneyLogModel;
use app\common\model\PaymentPlatformModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\InvestmentProductOrderRepository;
use app\common\repository\WithdrawalRepository;
use app\exception\UpayException;
use app\lib\exception\LockException;
use Carbon\Carbon;
use think\facade\Db;
use think\Exception;
use think\facade\Log;
use think\facade\View;

class Withdraw extends Base
{
    public function index($type = 0)
    {
        $status_arr = config('app.withdrawals_status');
        $status = request()->param('status/d', 0);
        $check = request()->param('check/d', 0);

        if ($this->request->isAjax() || $this->request->isPost()) {
            $export = $this->request->param("export/d", 0);
            $return = $this->getdata($export === 1);
            if ($export) {
                $header = [
                    "id" => "ID",
                    "user_id" => "用户id",
                    "user_name" => "用户名",
                    "tree_level" => "所在层级",
                    "top_leader_remark" => "所属代理",
                    "total_recharge_money" => "总充值金额",
                    "total_withdraw_money" => "总提现金额",
                    "user_money" => "当前余额",
                    "product_frozen_money" => "冻结资金",
                    "today_recharge_money" => "当日充值金额",
                    "today_withdraw_money" => "当日提现金额",
                    "today_finance_product_count" => "当日广告位交易次数",
                    'finance_product_order_day' => '累计交易天数',
                    'finance_product_no_order_day' => '累计未交易天数',
                    "bank_name" => "银行名称",
                    "bank_number" => "银行卡号",
                    "document_type" => "账户类型",
                    "document_id" => "账户账号",
                    "true_name" => "真实姓名",
                    "order_id" => "订单号",
                    "trade_no" => "三方订单号",
                    "channel_name" => "第三方渠道",
                    "original_money" => "提现金额",
                    "handling_fee" => "手续费",
                    "status_text" => "状态",
                    "user_create_time" => "注册时间",
                    "create_time" => "申请时间",
                    "pay_time" => "支付时间",
                    "remark" => "备注",
                    "user_remark" => "用户备注",
                    "level_name" => "等级",
                ];
                return genXlsx($header, $return, "提现记录");
            }
            return json($return);
        }
        $remark = Db::name('withdrawals')->where("remark", "<>", "")->distinct(true)->column("remark");
        View::assign([
            'remark' => array_filter($remark),
            'status_arr' => $status_arr,
            'status' => $status,
            'check' => $check,
            'type' => $type,
        ]);
        $agent = MemberModel::where('is_proxy', 1)->select();
        View::assign('agent', $agent);
        $today_recharge_withdraw_rate_warning = ConfigModel::getConfigValue('today_recharge_withdraw_rate_warning');
        View::assign('today_recharge_withdraw_rate_warning', $today_recharge_withdraw_rate_warning);
        if ($type == 1) {
            $channel = PaymentPlatformModel::where('status', 1)
                ->where('currency_type', PaymentPlatformModel::CURRENCY_TYPE_USDT)
                ->field(['id', 'name'])
                ->select();
            View::assign('channels', $channel);
            return View::fetch('usdt');
        } else {
            $channel = PaymentPlatformModel::where('status', 1)
                ->where('currency_type', PaymentPlatformModel::CURRENCY_TYPE_BANK)
                ->field(['id', 'name'])
                ->select();
            View::assign('channels', $channel);
            return View::fetch();
        }
    }

    public function usdt()
    {
        return $this->index(1);
    }

    /**
     * 提现列表
     * @return mixed|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function withdraw_log()
    {
        $user_id = $this->request->param('user_id/s');//用户id
        if ($this->request->isAjax() || $this->request->isPost()) {
            $return = $this->getWithdrawData();
            return json($return);
        }
        View::assign('user_id', $user_id);
        return View::fetch();
    }


    private function getdata($export = false)
    {
        $status_arr = config('app.withdrawals_status');
        $param = input('param.');
        $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
        $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
        $limit = $param['pageSize'];
        $offset = ($param['pageNumber'] - 1) * $limit;
        $uid = $this->request->param("uid/d", 0);
        $time_type = intval($param['time_type']);
        if ($uid > 0) {
            $where[] = ['user_id', '=', $uid];
        }
//        $is_xuni = $param['vuser'] ?? null;
//        if (in_array($is_xuni, [0, 1])) {
//            $where[] = ['hu.is_xuni', '=', (int) $is_xuni];
//        } elseif ($is_xuni == 2) { //冻结用户
//            $where[] = ['hu.status', '=', 2];
//        } elseif ($is_xuni == 3) { //未冻结用户
//            $where[] = ['hu.status', '=', 1];
//        }

        if (!empty($param['searchText'])) {
            $where[] = ['hu.mobile|hu.user_name|w.order_id', 'like', '%' . $param['searchText'] . '%'];
        }
        if (!empty($param['user_id'])) {
            $where[] = ['hu.id', '=', $param['user_id']];
        }
        if (!empty($param['user_name'])) {
            $where[] = ['hu.user_name', '=', $param['user_name']];
        }
        $where[] = ['w.status', '>', -2];

        if (isset($param['channel']) && $param['channel'] != '') {
            $where[] = ['w.channel', '=', $param['channel']];
        }
        if (isset($param['type']) && $param['type'] != '') {
            $where[] = ['w.type', '=', $param['type']];
        }
        if ($param['searchStatus'] != '') {
            $where[] = ['w.status', '=', $param['searchStatus']];
        } else {
            if (isset($param['status'])) {
                if ($param['status'] == 0) {
                    $withdraw_check_hour = ConfigModel::getConfigValue('withdraw_check_hour');
                    if ($param['check']) {
                        $where[] = ['w.create_time', '<', Carbon::now()->subHours($withdraw_check_hour)->timestamp];
                    } else {
                        $where[] = ['w.create_time', '>', Carbon::now()->subHours($withdraw_check_hour)->timestamp];
                    }
                    $where[] = ['w.status', '=', $param['status']];
                } else if ($param['status'] == 7) {
                    $where[] = ['w.status', '=', $param['status']];
                } else {
                    $where[] = ['w.status', '<>', 0];
                    $where[] = ['w.status', '<>', 7];
                }
            }
        }
        if (!empty($param['startTime']) && !empty($param['endTime'])) {
            $start_time = strtotime($param['startTime']);
            $end_time = strtotime($param['endTime']);
            if ($time_type == 0) {
                $where[] = ['w.create_time', 'BETWEEN', [$start_time, $end_time]];
            } else {
                $where[] = ['w.check_time', 'BETWEEN', [$start_time, $end_time]];
            }
        }
        if (!empty($param['leader1'])) {
            $where[] = ['hu.tree_id', 'like', '%/' . $param['leader1'] . '/%'];
            $where[] = ['hu.tree_id', 'not like', '%/' . $param['leader1'] . '/'];
        }
        $orderby = ['id' => 'DESC'];
        if (!empty($param['sortName']) && in_array($param['sortName'],
                ['original_money', 'create_time', 'check_time'])) {
            $orderby = ["w." . $param['sortName'] => ($param['sortOrder'] == "asc" ? "ASC" : "DESC")];
        }

//        //重复IP用户
//        $repeat_flag = $this->request->param('repeatIP/d', 0);
//        if($repeat_flag == '1'){
//            $where[] = ['hu.last_login_ip', 'in', function($query){
//                $query->name('home_user')
//                    ->whereNotIn('last_login_ip', ['', '0.0.0.0'])
//                    ->group('last_login_ip')
//                    ->having('count(1) > 1')
//                    ->field('last_login_ip');
//            }];
//            $orderby = ['last_login_ip' => 'DESC','id' => 'DESC'];
//        }

        $medol = new WithdrawModel();
        $level_name = LevelModel::column("level_name", 'level_id');
        if ($export) {
            $selectResult = $medol->getAllByWhere($where, $orderby);
            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['status_text'] = $status_arr[$vo['status']];
                $top_leader = \app\common\model\UserModel::getTopLeader($vo['user_id']);
                $selectResult[$key]['top_leader_remark'] = '';
                if ($top_leader) {
                    $selectResult[$key]['top_leader_remark'] = $top_leader['remark'];
                }
                $user_info = (new MemberTransform)->transform($vo['user']);
                $today = Carbon::parse($vo['create_time']);
                // 获取今日充值金额
                $today_recharge_money = RechargeModel::where('user_id', $vo['user_id'])
                    ->where('status', RechargeModel::STATUS_SUCCESS)
                    ->where('create_time', '>=', $today->startOfDay()->timestamp)
                    ->sum('money');
                // 获取今日提现成功和申请中的金额
                $today_withdraw_money = WithdrawModel::where('user_id', $vo['user_id'])
                    ->whereIn('status', [WithdrawalRepository::STATUS_PENDING, WithdrawalRepository::STATUS_WAIT, WithdrawalRepository::STATUS_SUCCESS])
                    ->where('create_time', '>=', $today->startOfDay()->timestamp)
                    ->sum('original_money');
                // 获取提现成功的金额
                $total_withdraw_money = WithdrawModel::where('user_id', $vo['user_id'])
                    ->whereIn('status', WithdrawalRepository::STATUS_SUCCESS)
                    ->where('create_time', '<=', $today->endOfDay()->timestamp)
                    ->sum('original_money');
                $selectResult[$key]['today_recharge_money'] = $today_recharge_money;
                $selectResult[$key]['today_withdraw_money'] = $today_withdraw_money;
                $selectResult[$key]['total_withdraw_money'] = $total_withdraw_money;
                $selectResult[$key]['total_recharge_money'] = $user_info['recharge_money'];
                $selectResult[$key]['user_money'] = $user_info['user_money'];
                $selectResult[$key]['user_remark'] = $user_info['remark'] ?: '';
                $selectResult[$key]['level_name'] = $level_name[$user_info['level_id']] ?? '非会员';
                $selectResult[$key]['user_create_time'] = $user_info['create_time'];

                preg_match_all("/\d+/", $user_info['tree_id'], $matches);
                $count = count($matches[0]);
                $selectResult[$key]['tree_level'] = $count;

                // 获取广告位待结算金额和AI智能托管待结算金额
                $finance_product_price = FinanceProductOrderModel::hasWhere('user')
                    ->where('finance_product_order_model.status', 'in', [
                        FinanceProductOrderRepository::STATUS_PURCHASED,
                    ])
                    ->where('user_id', 'in', $vo['user_id'])
                    ->sum('price');

                $investment_product_money = InvestmentProductOrderModel::hasWhere('user')
                    ->where('investment_product_order_model.status', 'in', [
                        InvestmentProductOrderRepository::STATUS_PURCHASED,
                    ])
                    ->where('user_id', 'in', $vo['user_id'])
                    ->sum('money');

                $selectResult[$key]['product_frozen_money'] = $finance_product_price + $investment_product_money;
                $selectResult[$key]['today_finance_product_count'] = (new FinanceProductOrderRepository)->todayUserFinanceProductCount($vo['user_id'], explode(' ', $vo['create_time'])[0]);

                $user_create_time = $vo['user']['create_time'];
                // 获取注册时间
                $register_days = floor(Carbon::parse($vo['create_time'])->diffInDays());
                // 获取累计交易天数
                $selectResult[$key]['finance_product_order_day'] = FinanceProductOrderModel::where('user_id', $vo['user_id'])
                    ->where('status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
                    ->field('COUNT(DISTINCT DATE(FROM_UNIXTIME(create_time))) as order_count')->select()[0]['order_count'];
                $selectResult[$key]['finance_product_no_order_day'] = ($register_days > $selectResult[$key]['finance_product_order_day']) ? $register_days - $selectResult[$key]['finance_product_order_day'] : 0;


            }
            return $selectResult;
        } else {
            $selectResult = $medol->getLlistsByWhere($where, $offset, $limit, $orderby);
        }

        // 拼装参数
        foreach ($selectResult as $key => $vo) {
            if ($vo['status'] == 1 || $vo['status'] == 7) {
                $button_arr = $this->makeButton($vo['id'], $vo['status'], $vo['channel']);
                if ($vo['is_xuni'] == 1) {
                    $button_arr["线下转账"] = [
                        'auth' => 'withdraw/checkstatus',
                        'href' => "javascript:offlineTransfer(" . $vo['id'] . ")",
                        'btnStyle' => 'success',
                        'icon' => 'fa fa-money'
                    ];
                }
                $buttons = showOperate($button_arr);
            } else {
                if ($vo['status'] == 3) {
                    $buttons = showOperate([
                        '删除' => [
                            'auth' => 'withdraw/withdrawdel',
                            'href' => "javascript:withdrawDel(" . $vo['id'] . ")",
                            'btnStyle' => 'danger',
                            'icon' => 'fa fa-trash-o'
                        ],
                        '发送站内信' => [
                            'auth' => 'withdraw/sendmessage',
                            'href' => "javascript:sendMessage(" . $vo['id'] . ")",
                            'btnStyle' => 'info',
                            'icon' => 'fa fa-envelope'
                        ]
                    ]);
                } else {
                    if ($vo['status'] == 5) {
                        $buttons = showOperate([
                            '线下转账' => [
                                'auth' => 'withdraw/checkstatus',
                                'href' => "javascript:offlineTransfer(" . $vo['id'] . ")",
                                'btnStyle' => 'success',
                                'icon' => 'fa fa-money'
                            ],
                            '发送站内信' => [
                                'auth' => 'withdraw/sendmessage',
                                'href' => "javascript:sendMessage(" . $vo['id'] . ")",
                                'btnStyle' => 'info',
                                'icon' => 'fa fa-envelope'
                            ]
                        ]);
                    } else {
                        $buttons = showOperate([
                            '发送站内信' => [
                                'auth' => 'withdraw/sendmessage',
                                'href' => "javascript:sendMessage(" . $vo['id'] . ")",
                                'btnStyle' => 'info',
                                'icon' => 'fa fa-envelope'
                            ]
                        ]);
                    }
                }
            }
            if ($vo['type'] == 4) {
                $selectResult[$key]['usdt_money'] = fiatToUsdt($vo['money'], $vo['exchange_rate']);
            }
//            $selectResult[$key]['true_name'] = $vo['true_name'] . '(ID:' . $vo['user_id'] . ')';
            $selectResult[$key]['check_time'] = $vo['check_time'] > 0 ? date('Y-m-d H:i:s', $vo['check_time']) : '-';
            switch ($vo['status']) {
                case 2: // 审核通过
                case 4: // 已出款
                    $selectResult[$key]['status_text'] = getStatusText(1, $status_arr[$vo['status']]);
                    break;
                case 3: // 审核失败
                case 5: // 出款失败
                case 6: // 出款失败
                    $selectResult[$key]['status_text'] = getStatusText(2, $status_arr[$vo['status']]);
                    break;
                default:
                    $selectResult[$key]['status_text'] = $status_arr[$vo['status']];
            }
            $selectResult[$key]['wcount'] = WithdrawModel::where('user_id', $vo['user_id'])->count();
            $selectResult[$key]['operate'] = $buttons;
            $selectResult[$key]['last_login_ip'] = $vo['last_login_ip'] . "(" . ip2location($vo['last_login_ip']) . ')';
            $selectResult[$key]['top_leader'] = \app\common\model\UserModel::getTopLeader($vo['user_id']);
            if ($vo['ip']) {
                $selectResult[$key]['ip'] = $vo['ip'] . "(" . ip2location($vo['ip']) . ')';
            }
            $ip_count = 0;
            if (isset($vo['user']['user_id'])) {
                $ip_count = count($vo['user']['user_ip']);
            }
            if ($ip_count > 0) {
                $ip_count -= 1;
            }
            $user_info = (new MemberTransform)->transform($vo['user']);
            $selectResult[$key]['user_info'] = $user_info;
            $user_create_time = $vo['user']['create_time'];
            $selectResult[$key]['user_remark'] = $user_info['remark'] ?: '';
            unset($vo['user']);
            $selectResult[$key]['ip_count'] = $ip_count;

            if (isset($vo['bank_kh']) && !empty($vo['bank_kh'])) {
            }

//            if (isset($vo['trade_no']) && !empty($vo['trade_no'])) {
//                $selectResult[$key]['order_id'] .= "<hr>{$vo['trade_no']}";
//            }

            $recharge = RechargeModel::where('user_id', $vo['user_id'])
                ->where("status", 2)
                ->order(['create_time' => 'desc'])
                ->find();

            $selectResult[$key]['last_recharge_time'] = $recharge ? $recharge->create_time : '--';
            $channel = PaymentPlatformModel::find($vo['channel']);
            $selectResult[$key]['channel'] = $channel;

            $admin = UserModel::find($vo['admin_id']);
            $selectResult[$key]['admin_name'] = $admin ? $admin->user_name : '系统';

            $result = $vo['result'];
            $tx_status_text = '待打款';
            $tx_fail_reason = '';
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
                            case 'Watchpay':
                                if (isset($result['tradeResult'])) {
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
                                }
                                break;
                            case 'Gctpkpay':
                                // status=7表示订单成功,status=2或8表示订单失败
                                if (isset($result['status'])) {
                                    switch ($result['status']) {
                                        case 7:
                                            $tx_status_text = getStatusText(1, '转账成功');
                                            break;
                                        case 2:
                                        case 8:
                                            $tx_status_text = getStatusText(2, '转账失败');
                                            break;
                                    }
                                }
                                break;
                            case 'Vortaqpay':
                                // status=7表示订单成功,status=2或8表示订单失败
                                if (isset($result['status_code'])) {
                                    switch ($result['status_code']) {
                                        case 100:
                                            $tx_status_text = getStatusText(1, '转账成功');
                                            break;
                                        case 1:
                                            $tx_status_text = getStatusText(2, '下单');
                                            break;
                                        case 2:
                                            $tx_status_text = getStatusText(2, '待审核');
                                            break;
                                        case 3:
                                            $tx_status_text = getStatusText(2, '出款中');
                                            break;
                                        case 4:
                                            $tx_status_text = getStatusText(2, '出款失败');
                                            break;
                                        case 5:
                                            $tx_status_text = getStatusText(2, '转账失败');
                                            break;
                                    }
                                }
                                break;
                            case 'Shpay':
                                // status=7表示订单成功,status=2或8表示订单失败
                                if (isset($result['transStatus'])) {
                                    switch ($result['transStatus']) {
                                        case 'SUCCESS':
                                            $tx_status_text = getStatusText(1, '转账成功');
                                            break;
                                        case 'PROCESSING ':
                                            $tx_status_text = getStatusText(2, '处理中');
                                            break;
                                        case 'FAIL':
                                            $tx_status_text = getStatusText(2, '支付失败');
                                            break;
                                    }
                                }
                                break;
                            case 'Odpay':
                                // status=7表示订单成功,status=2或8表示订单失败
                                if (isset($result['status'])) {
                                    switch ($result['status']) {
                                        case 1:
                                            $tx_status_text = getStatusText(2, '交易中');
                                            break;
                                        case 2:
                                            $tx_status_text = getStatusText(1, '转账成功');
                                            break;
                                        case 3:
                                            $tx_status_text = getStatusText(2, '转账失败');
                                            break;
                                        case 5:
                                            $tx_status_text = getStatusText(2, '退款， 成功转失败');
                                            break;
                                    }
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
                            case "Vipdcpay":
                                $tx_fail_reason = "系统错误";
                                switch ($result['status']) {
                                    case 2:
                                        $tx_status_text = getStatusText(1, '出款成功');
                                        break;
                                    case -1:
                                        $tx_status_text = getStatusText(2, '出款失败');
                                        break;
                                    case -2:
                                        $tx_status_text = getStatusText(2, '出款已取消');
                                        break;
                                }
                                break;
                        }
                    }
                }
            }
            $selectResult[$key]['tx_status_text'] = $tx_status_text;
            $selectResult[$key]['tx_fail_reason'] = $tx_fail_reason;
            $today = Carbon::parse($vo['create_time']);
            // 获取今日充值金额
            $today_recharge_money = RechargeModel::where('user_id', $vo['user_id'])
                ->where('status', RechargeModel::STATUS_SUCCESS)
                ->where('create_time', '>=', $today->startOfDay()->timestamp)
                ->sum('money');
            // 获取今日提现成功和申请中的金额
            $today_withdraw_money = WithdrawModel::where('user_id', $vo['user_id'])
                ->whereIn('status', [WithdrawalRepository::STATUS_PENDING, WithdrawalRepository::STATUS_WAIT, WithdrawalRepository::STATUS_SUCCESS])
                ->where('create_time', '>=', $today->startOfDay()->timestamp)
                ->sum('original_money');
            // 获取提现成功的金额
            $total_withdraw_money = WithdrawModel::where('user_id', $vo['user_id'])
                ->whereIn('status', WithdrawalRepository::STATUS_SUCCESS)
                ->where('create_time', '<=', $today->endOfDay()->timestamp)
                ->sum('original_money');
            $selectResult[$key]['today_recharge_money'] = $today_recharge_money;
            $selectResult[$key]['today_withdraw_money'] = $today_withdraw_money;
            $selectResult[$key]['total_withdraw_money'] = $total_withdraw_money;
            $selectResult[$key]['total_recharge_money'] = $user_info['recharge_money'];
            $selectResult[$key]['user_money'] = $total_withdraw_money;
            $selectResult[$key]['level_name'] = $level_name[$user_info['level_id']] ?? '非会员';
            preg_match_all("/\d+/", $user_info['tree_id'], $matches);
            $count = count($matches[0]);
            $selectResult[$key]['tree_level'] = $count;

            // 获取广告位待结算金额和AI智能托管待结算金额
            $finance_product_price = FinanceProductOrderModel::hasWhere('user')
                ->where('finance_product_order_model.status', 'in', [
                    FinanceProductOrderRepository::STATUS_PURCHASED,
                ])
                ->where('user_id', 'in', $vo['user_id'])
                ->sum('price');

            $investment_product_money = InvestmentProductOrderModel::hasWhere('user')
                ->where('investment_product_order_model.status', 'in', [
                    InvestmentProductOrderRepository::STATUS_PURCHASED,
                ])
                ->where('user_id', 'in', $vo['user_id'])
                ->sum('money');

            $selectResult[$key]['product_frozen_money'] = $finance_product_price + $investment_product_money;
            $selectResult[$key]['today_finance_product_count'] = (new FinanceProductOrderRepository)->todayUserFinanceProductCount($vo['user_id'], explode(' ', $vo['create_time'])[0]);

            // 获取注册时间
            $register_days = floor(Carbon::parse($vo['create_time'])->diffInDays());
            // 获取累计交易天数
            $selectResult[$key]['finance_product_order_day'] = FinanceProductOrderModel::where('user_id', $vo['user_id'])
                ->where('status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED])
                ->field('COUNT(DISTINCT DATE(FROM_UNIXTIME(create_time))) as order_count')->select()[0]['order_count'];
            $selectResult[$key]['finance_product_no_order_day'] = ($register_days > $selectResult[$key]['finance_product_order_day']) ? $register_days - $selectResult[$key]['finance_product_order_day'] : 0;

        }

        if ($export) {
            return $selectResult;
        }
        $return['total'] = $medol->getAllLists($where);  //总数据
        $return['rows'] = $selectResult;

        $model = new WithdrawModel();
        $stat = $model->alias('w')
            ->join('home_user hu', 'w.user_id = hu.id', 'left')
            ->where($where)->group('status')->field('w.status,sum(w.original_money) AS total')->select();
        if ($stat) {
            foreach ($stat as $k => $v) {
                $stat[$k]['text'] = $status_arr[$v['status']];
                $stat[$k]['total'] = i18n_sprintf($v['total']);
            }
        }
        $return['stat'] = $stat;
        $return['status'] = $param['status'];
        return $return;
    }

    // 审核
    public function checkStatus()
    {
        $id = input('param.id');
        $status = input('param.status');
        $remark = input('param.remark');
        $channel = input('param.channel', '');
        if (!in_array($status, [
            WithdrawalRepository::STATUS_WAIT, WithdrawalRepository::STATUS_AGREE,
            WithdrawalRepository::STATUS_REJECT, WithdrawalRepository::STATUS_SUCCESS, WithdrawalRepository::STATUS_RESTRICTED,
        ])) { //0-待处理 1-审核中 2-通过 3-失败 4-自动出款 5-出款失败
            return json(msg(-1, '', lang('审核状态有误')));
        }
        if (empty($remark)) {
            return json(msg(-1, '', lang('备注不能为空')));
        }
//        if ($status == 4 && empty($channel)) {
//            return json(msg(-1, '', lang('出款渠道不能为空')));
//        }
        $ids = explode(",", $id);
        foreach ($ids as $id) {
            $info = Db::name('withdrawals')->where('id', $id)
                ->whereIn("status", [WithdrawalRepository::STATUS_PENDING, WithdrawalRepository::STATUS_WAIT, WithdrawalRepository::STATUS_RESTRICTED])
                ->lock()
                ->find();
            if (!$info) {
                return json(msg(-1, '', lang('提现信息不存在')));
            }
        }
//        $config = (new \app\admin\model\ConfigModel())->getConfig();
        foreach ($ids as $id) {
            WithdrawModel::startTrans();

            try {
                $info = WithdrawModel::where('id', $id)
                    ->whereIn("status", [WithdrawalRepository::STATUS_PENDING, WithdrawalRepository::STATUS_WAIT, WithdrawalRepository::STATUS_RESTRICTED])
                    ->lock()
                    ->find();
                $status_tmp = $status;//虚拟号不允许自动出款
                if ($info['is_xuni'] == 1 && $status_tmp == 4) {
                    $status_tmp = 2;
                }
                $remark_tmp = '';
                $user_status = Db::name('home_user')->where(['id' => $info['user_id']])->value('status');
                if ($user_status == 2) {
                    $status_tmp = 3;
                    $remark_tmp = lang("用户账号被冻结,强制审核失败");
                }
                $admin_id = session('id');
                if ($status_tmp == 3) {
                    $money = $info['withdraw_money'];
                    insert_operation_log('审核提现:失败', "订单号:{$info['order_id']}{$remark_tmp}", $info['user_id']);
                    $money_log = new MoneyLogModel();
                    $money_log->changeMoney($info['user_id'], $money, MoneyLogModel::TYPE_FROZEN, 'sub', '提现申请拒绝',
                        '用户提现' . $money, 2, $admin_id, '', $info->id);
                    $money_log->changeMoney($info['user_id'], $money, MoneyLogModel::TYPE_BROKERAGE, 'add',
                        '提现申请拒绝',
                        '用户提现' . $money, 2, $admin_id, '', $info->id);
                    if ($info['handling_fee']) {
                        $money_log->changeMoney($info['user_id'], $info['handling_fee'], MoneyLogModel::TYPE_BROKERAGE,
                            'add',
                            '用户提现手续费',
                            '用户提现手续费' . $info['handling_fee'], 2, $admin_id, '', $info->id);
                    }
                } else {
                    if ($status_tmp == 2 || $status_tmp == 4) {
                        // 出款
                        if ($channel) {
                            $channel = PaymentPlatformModel::getOne($channel, 'withdraw');
                        } else {
                            $channel = PaymentPlatformModel::getOne($info->channel, 'withdraw');
                        }
                        if (!$channel) {
                            $this->error(lang('找不到可用的提现渠道'));
                        }
                        $config = json_decode($channel->config, true);

                        // 如果是虚拟货币
                        if ($channel->currency_type === PaymentPlatformModel::CURRENCY_TYPE_USDT) {
                            try {
                                PaymentPlatformModel::withdraw($info->user_id, $info->channel, $info->convert_money,
                                    $info->order_id);
                            } catch (UpayException | Exception $e) {
                                WithdrawModel::rollback();
                                Log::write($e->getMessage() . "\n" . $e->getTraceAsString(), "error");
                                $this->error(lang('出款失败') . lang($e->getMessage()));
                            }
                        } else {
                            if ($config['class_name'] !== 'Offlinepay') {
                                try {
                                    $class_name = "transfer\\" . $config['class_name'];
                                    if (!class_exists($class_name)) {
                                        return json(msg(-1, '', lang('支付方式不存在,请联系管理员')));
                                    }
                                    $result = (new $class_name($config))->pay($info);
                                    $info->result = json_encode($result);
                                } catch (LockException $e) {
                                    WithdrawModel::rollback();
                                    return json(msg(-1, '', $e->msg));
                                } catch (Exception $e) {
                                    WithdrawModel::rollback();
                                    return json(msg(-1, '', $e->getMessage()));
                                }
                            } else {
                                $status_tmp = 5;
                                $remark_tmp = '提现转账失败';
                                $info->result = json_encode([
                                    'success' => 0,
                                ]);
                            }

                        }

                        insert_operation_log('审核提现:自动付款', "订单号:{$info['order_id']}", $info['user_id']);
                    }
                }
                $info->status = $status_tmp;
                $info->admin_id = $admin_id;
                $info->check_time = time();
                $info->remark = $remark . $remark_tmp;
                $info->channel = ($status == 4 || $status == 2) ? $channel->id : $info->channel;
                $info->save();
                WithdrawModel::commit();
            } catch (Exception $e) {
                WithdrawModel::rollback();
            }

        }
        return json(msg(1, '', lang('操作成功')));
    }

    // 删除提现记录
    public function withdrawDel()
    {
        $id = input('param.id');
        $model = new WithdrawModel();
        $info = $model->where('id', $id)->where("status", 3)->find();
        if (empty($info)) {
            return json(msg(-1, '', lang('数据不存在')));
        }
        insert_operation_log("删除提现记录", $info->toArray(), $info['user_id']);
        $info->delete();
        return json(msg(1, '', lang('操作成功')));
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id, $status, $channel = '')
    {
        $button = [
//            '自动出款' => [
//                'auth' => 'withdraw/checkstatus',
//                'href' => "javascript:checkStatus(" . $id . ", 4)",
//                'btnStyle' => 'suucess',
//                'icon' => 'fa fa-paste'
//            ],
            '审核通过' => [
                'auth' => 'withdraw/checkstatus',
                'href' => "javascript:checkStatus(" . $id . ", 2, " . $channel . ")",
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '审核失败' => [
                'auth' => 'withdraw/checkstatus',
                'href' => "javascript:checkStatus(" . $id . ", 3)",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-paste'
            ],
            '发送站内信' => [
                'auth' => 'withdraw/sendmessage',
                'href' => "javascript:sendMessage(" . $id . ")",
                'btnStyle' => 'info',
                'icon' => 'fa fa-envelope'
            ],

        ];
        if ($status != 7) {
            $button['卡审核'] = [
                'auth' => 'withdraw/checkstatus',
                'href' => "javascript:checkStatus(" . $id . ", 7)",
                'btnStyle' => 'warning',
                'icon' => 'fa fa-paste'
            ];
        }
        return $button;
    }

    public function checkNew()
    {
        $count = WithdrawModel::count();
        $this->success('', '', $count);
    }

    /**
     * 发送站内信
     */
    public function sendMessage()
    {
        if (request()->isPost()) {
            $param = input('post.');

            if (empty($param['title']) || empty($param['content'])) {
                return json(msg(-1, '', lang('标题和内容不能为空')));
            }

            $withdraw = WithdrawModel::find($param['id']);
            if (!$withdraw) {
                return json(msg(-1, '', lang('提现记录不存在')));
            }

            $message = new UserMessageModel();
            $data = [
                'user_id' => $withdraw['user_id'],
                'admin_id' => session('id'),
                'type' => UserMessageModel::TYPE_CUSTOM,
                'title' => $param['title'],
                'content' => $param['content'],
                'is_pop' => 1
            ];

            if (false === $message->save($data)) {
                return json(msg(-1, '', lang('发送失败')));
            }

            insert_operation_log('发送站内信', $data, $withdraw['user_id']);
            return json(msg(1, '', lang('发送成功')));
        }
    }

    /**
     * 线下转账处理
     */
    public function offlineTransfer()
    {
        $id = input('param.id');
        $remark = input('param.remark');
        $trade_no = input('param.trade_no');
        $status = input('param.status/d', 6); // 默认成功状态4，失败状态6

        if (empty($remark)) {
            return json(msg(-1, '', lang('备注不能为空')));
        }
        // 只有在成功状态时才验证三方订单号
        if ($status == 4 && empty($trade_no)) {
            return json(msg(-1, '', lang('三方订单号不能为空')));
        }

        $model = new WithdrawModel();
        $info = $model->where('id', $id)->find();
        //->where('status', 5)
        if (empty($info) || ($info['status'] != 5 && $info['is_xuni'] != 1)) {
            return json(msg(-1, '', lang('数据不存在或状态已改变')));
        }
        // 查找线下支付渠道
        $platform = PaymentPlatformModel::json(['config'])
            ->where('config->class_name', 'Offlinepay')
            ->where('status', 1)
            ->find();
        Db::startTrans();
        try {
            $result = json_decode($info->result, true) ?? [];
            if ($status == 4) {
                $result['success'] = true;
            } else {
                if ($status == 6) {
                    $result['success'] = false;
                    $trade_no = $info->trade_no;
                }
            }
            $info->result = json_encode($result);
            $info->status = $status; // 根据参数设置状态
            $info->trade_no = $trade_no; // 只有成功时才记录三方订单号
            $info->remark = $info->remark . '_' . $remark;
            $info->channel = $platform->id;
            $info->check_time = time();
            $info->admin_id = session('id');
            $info->save();

            insert_operation_log($status == 4 ? '线下转账处理' : '线下转账失败', $info->toArray(), $info['user_id']);

            // 如果是转账失败，需要退还用户余额
            if ($status == 6) {
                $title = '提现失败';
                $remark = '提现转账失败';
                $user_id = $info['user_id'];
                $money = $info['original_money'];
                $money_log = new MoneyLogModel();
                $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_FROZEN, 'sub', $title,
                    $remark, 2, session('id'), '', $info->id);
                $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_BROKERAGE, 'add',
                    $title,
                    $remark, 2, session('id'), '', $info->id);
                if ($info['handling_fee']) {
                    $money_log->changeMoney($user_id, $info['handling_fee'], MoneyLogModel::TYPE_BROKERAGE,
                        'add',
                        '用户提现手续费',
                        '用户提现手续费', 2, session('id'), '', $info->id);
                }
            } else if ($status == 4) {
                // 线下提现成功，恢复冻结金额
                $title = '线下提现成功';
                $remark = '';
                $user_id = $info['user_id'];
                $money = $info['original_money'];
                $money_log = new MoneyLogModel();
                $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_FROZEN, 'sub', $title,
                    $remark, 2, session('id'), '', $info->id);

                // 增加提现钱包数据
                $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_WITHDRAWAL, 'add', $title,
                    $remark, 2, session('id'), '', $info->id);
            }

            Db::commit();
            return json(msg(1, '', lang('操作成功')));
        } catch (\Exception $e) {
            Db::rollback();
            return json(msg(-1, '', lang('操作失败')));
        }
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
        $uids = [$user_id];
        $where = [];
        $where[] = ['w.user_id', 'in', $uids];
        $orderby = ['status' => 'ASC', 'id' => 'DESC'];
        $medol = new WithdrawModel();
        $selectResult = $medol->getLlistsByWhere($where, $offset, $limit, $orderby);
        $status_arr = config('app.withdrawals_status');
        // 拼装参数
        foreach ($selectResult as $key => $vo) {
            if ($vo['type'] == 4) {
                $selectResult[$key]['usdt_money'] = fiatToUsdt($vo['money'], $vo['exchange_rate']);
            }
            $selectResult[$key]['check_time'] = $vo['check_time'] > 0 ? date('Y-m-d H:i:s', $vo['check_time']) : '-';
            $selectResult[$key]['pay_time'] = empty($vo['pay_time']) ? '-' : $vo['pay_time'];
            $selectResult[$key]['status_text'] = $status_arr[$vo['status']];
            $selectResult[$key]['operate'] = "-";
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
                            case "Vipdcpay":
                                switch ($result['status']) {
                                    case 2:
                                        $tx_status_text = getStatusText(1, '出款成功');
                                        break;
                                    case -1:
                                        $tx_status_text = getStatusText(2, '出款失败');
                                        break;
                                    case -2:
                                        $tx_status_text = getStatusText(2, '出款已取消');
                                        break;
                                }
                                break;
                        }
                    }
                }
            }
            $selectResult[$key]['tx_status_text'] = $tx_status_text;
            $selectResult[$key]['tx_fail_reason'] = $tx_fail_reason ?? "系统错误";
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

}
