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

use app\admin\model\CollectionDetailModel;
use app\admin\model\CollectionModel;
use app\admin\model\MemberModel;
use app\admin\model\PaymentModel;
use app\admin\model\RechargeModel;
use app\admin\model\UserMessageModel;
use app\common\model\AdPlanModel;
use app\common\model\ConfigModel;
use app\common\model\MoneyLogModel;
use app\common\model\PaymentPlatformModel;
use app\common\model\PddTurntableCountModel;
use app\common\model\TurntableCountModel;
use app\common\model\UserModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\RechargeRepository;
use app\exception\UpayException;
use think\facade\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\db\Where;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\facade\Log;
use think\facade\View;
use think\response\Json;

class Recharge extends Base
{
    public function index()
    {
        $status = config('app.recharge_status');
        $type = $this->request->param('type/d', 3);
        $template = 'third';
        $title = '三方充值记录';
        switch ($type) {
            case 0:
                $template = 'bank';
                $title = '手动补单记录';
                break;
            case 1:
                $template = 'bank';
                $title = '线下充值';
                break;
            case 2:
                $template = 'usdt';
                $title = '合约充值记录';
                break;
            case 3:
                $template = 'third';
                $title = '三方充值记录';
                break;
        }

        if ($this->request->isAjax() || $this->request->isPost()) {
            $export = $this->request->param("export/d", 0);
            $return = $this->getdata($export === 1);
            if ($export) {
                $header = [
                    "id"                => "ID",
                    "uid"               => "用户id",
                    "leader1"           => "上级id",// TODO 新增字段
                    "user_name"         => "用户名",
                    "tree_level"        => "所在层级",
                    "top_leader_remark" => "所属代理",
                    "true_name"         => "真实姓名",
                    "order_id"          => "订单号",
                    "trade_no"          => "三方订单号",
                    "channel_name"      => "第三方渠道",
                    "extra_channel"     => "通道名称",
                    "extra_type"        => "类型",
                    "extra_name"        => "姓名",
                    "extra_account"     => "账户",
                    "is_first"          => "是否首充",// TODO 新增字段
                    "original_money"    => "原始金额",
                    "exchange_rate"     => "汇率",
                    "money"             => "充值金额",
                    "status_text"       => "状态",
                    "pay_time"          => "支付时间",
                    "create_time"       => "创建时间",
                ];
                return genXlsx($header, $return, $title);
            }
            return json($return);
        }
        $paylist = PaymentModel::select();
        $remark = Db::name('recharge')->where("remark", "<>", "")->distinct(true)->column("remark");
        $pay = config('pay');
        View::assign([
            'remark'  => array_filter($remark),
            'status'  => $status,
            'paylist' => empty($paylist) ? [] : $paylist,
            'pay'     => $pay,
            'type'    => $type,
        ]);
        $agent = MemberModel::where('is_proxy', 1)->select();
        View::assign('agent', $agent);
        $fiat_currency_unit = ConfigModel::getConfigValue('fiat_currency_unit');
        View::assign('fiat_currency_unit', $fiat_currency_unit);
        return View::fetch($template);
    }

    private function getdata($export = 0)
    {

        $status_text = config('app.recharge_status');
        $param = input('param.');
        $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
        $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
        if (!isset($param['pageNumber'])) {
            $param['pageNumber'] = 1;
        }
        if (!isset($param['pageSize'])) {
            $param['pageSize'] = 10;
        }
        $limit = $param['pageSize'];
        $offset = ($param['pageNumber'] - 1) * $limit;
        $uid = $this->request->param("uid/d", 0);
        $time_type = intval($param['time_type']);
        $where = [];
        if ($uid > 0) {
            $where[] = ['user_id', '=' ,$uid];
        }
        if (isset($param['vuser']) && in_array($param['vuser'], [0, 1])) {
            if ($param['vuser'] == 1) {
                $where[] = ['hu.is_xuni', '=' , 1];
            } else {
                $where[] = ['hu.is_xuni', '<>', 1];
            }
        }
        if (!empty($param['searchText'])) {
            $where[] = ['hu.mobile|hu.user_name|r.order_id|txid|trade_no|r.batch_no', 'like', '%'.$param['searchText'].'%'];
        }
        if (!empty($param['user_id'])) {
            $where[] = ['hu.id', '=', $param['user_id']];
        }
//        $where[] = ['r.status', '>', -2];
        if ($param['searchStatus']) {
            $where[] = ['r.status', '=' ,$param['searchStatus']];
        } else {
            $where[] = ['r.status', '<>', 1];
        }
        if (isset($param['pay_id']) && $param['pay_id'] != '') {
            $where[] = ['r.pay_id', '=' ,$param['pay_id']];
        }
        if (isset($param['type']) && $param['type'] != '') {
            switch ($param['type']) {
                case 0:
                    $where[] = ['r.from', '=' ,2];
                    break;
                case 1:
                case 2:
                case 3:
                    $where[] = ['r.from', '=', 1];
                    $where[] = ['r.type', '=', $param['type']];
                    break;
            }

        }
        if (!empty($param['leader1'])) {
            $where[] = ['hu.tree_id', 'like', '%/'.$param['leader1']. '/%'];
            $where[] = ['hu.tree_id', 'not like', '%/'.$param['leader1']. '/'];
        }
        $orderby = ['id' => 'DESC'];
        if (!empty($param['sortName']) && in_array($param['sortName'], ['money', 'create_time'])) {
            $orderby = ["r.".$param['sortName'] => ($param['sortOrder'] == "asc" ? "ASC" : "DESC")];
        }
        if (!empty($param['startTime']) && !empty($param['endTime'])) {
            $start_time = strtotime($param['startTime']);
            $end_time = strtotime($param['endTime']);
            if ($time_type == 0) {
                $where[] = ['r.create_time', 'BETWEEN', [$start_time, $end_time]];
            } else {
                $where[] = ['r.success_time', 'BETWEEN', [$start_time, $end_time]];
            }
        }
        $model = new RechargeModel();
        if ($export) {
            $selectResult = $model->getAllByWhere($where, $orderby);
            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['is_first'] = '累充';
                if ($vo['is_first']) {
                    $selectResult[$key]['is_first'] = '首冲';
                }
                $selectResult[$key]['status_text'] = $status_text[$vo['status']];
                $top_leader = UserModel::getTopLeader($vo['user_id']);
                $selectResult[$key]['top_leader_remark'] = '';
                if ($top_leader) {
                    $selectResult[$key]['top_leader_remark'] = $top_leader['remark'];
                }
                preg_match_all("/\d+/", $vo['tree_id'], $matches);
                $count = count($matches[0]);
                $selectResult[$key]['tree_level'] = $count;

                $selectResult[$key]['extra'] = '';
                $selectResult[$key]['extra_channel'] = '';
                $selectResult[$key]['extra_type'] = '';
                $selectResult[$key]['extra_name'] = '';
                $selectResult[$key]['extra_account'] = '';
                $type = $this->request->param('type/d', 3);
                if ($vo['extra']) {
                    if ($type == RechargeRepository::TYPE_BANK ) {
                        $selectResult[$key]['extra'] = $extra = json_decode($vo['extra'], true);
                        $selectResult[$key]['extra_channel'] = $extra['channel'];
                        $selectResult[$key]['extra_type'] = $extra['type'];
                        $selectResult[$key]['extra_name'] = $extra['name'];
                        $selectResult[$key]['extra_account'] = $extra['account'];
                    }
                }
            }
            return $selectResult;
        } else {
            $selectResult = $model->getLlistsByWhere($where, $offset, $limit, $orderby);
        }
//        dd($selectResult);
//        $paylist = PaymentPlatformModel::column('*', 'id');
        // 拼装参数
        foreach ($selectResult as $key => $vo) {
            $buttons = showOperate($this->makeButton($vo['id'], $vo['status']));
//            if ($vo['type'] == 4) {
//                $selectResult[$key]['usdt_money'] = fiatToUsdt($vo['money'], $vo['exchange_rate']);
//            }
//            $selectResult[$key]['type'] = $type_text[$vo['type']];
//            $selectResult[$key]['money'] = i18n_sprintf($vo['money']);
            $selectResult[$key]['status_text'] = $status_text[$vo['status']];
            $selectResult[$key]['operate'] = $buttons;
            $selectResult[$key]['top_leader'] = UserModel::getTopLeader($vo['user_id']);
            $selectResult[$key]['img'] = '';

            if ($vo['img']) {
                $images = explode(';', $vo['img']);
                foreach ($images as $img) {
                    $selectResult[$key]['img'] .= show_img(toMedia($img));
                }
            }
//            $selectResult[$key]['last_login_ip'] = $vo['last_login_ip']."<br>".ip2location($vo['last_login_ip']);
//            if ($vo['ip']) {
//                $selectResult[$key]['ip'] = $vo['ip']."<br>".ip2location($vo['ip']);
//            }

//            if (isset($paylist[$vo['pay_id']])) {
//                $selectResult[$key]['channel'] = $pay[$paylist[$vo['pay_id']]['account']]['name'];
//                $selectResult[$key]['bank_name'] = $paylist[$vo['pay_id']]['bank_name'];
//            } else {
//                $selectResult[$key]['channel'] = '';
//                $selectResult[$key]['bank_name'] = '';
//            }

            $selectResult[$key]['success_time'] = date('Y-m-d H:i:s', $vo['success_time']);
            $selectResult[$key]['channel'] = PaymentPlatformModel::find($vo['pay_id']);
            preg_match_all("/\d+/", $vo['tree_id'], $matches);
            $count = count($matches[0]);
            $selectResult[$key]['tree_level'] = $count;

            $selectResult[$key]['extra'] = '';
            $selectResult[$key]['extra_channel'] = '';
            $selectResult[$key]['extra_type'] = '';
            $selectResult[$key]['extra_name'] = '';
            $selectResult[$key]['extra_account'] = '';
            $type = $this->request->param('type/d', 3);
            if ($vo['extra']) {
                if ($type == RechargeRepository::TYPE_BANK ) {
                    $selectResult[$key]['extra'] = $extra = json_decode($vo['extra'], true);
                    $selectResult[$key]['extra_channel'] = $extra['channel'];
                    $selectResult[$key]['extra_type'] = $extra['type'];
                    $selectResult[$key]['extra_name'] = $extra['name'];
                    $selectResult[$key]['extra_account'] = $extra['account'];
                }
            }
        }
        if ($export) {
            return $selectResult;
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
     * 充值审核
     */
    public function checkStatus(): Json
    {
        $id = input('param.id');
        $status = input('param.status');
        $remark = input('param.remark');
        if (!in_array($status, array_keys(RechargeModel::$statusMap))) {
            return json(msg(-1, '', '审核状态有误'));
        }
        if (empty($remark)) {
            return json(msg(-1, '', '备注不能为空'));
        }
        $ids = explode(",", $id);
        foreach ($ids as $id) {
            $info = RechargeModel::where('id', $id)
                ->where("status", RechargeModel::STATUS_PENDING)
                ->lock()
                ->find();
            if (!$info) {
                return json(msg(-1, '', '充值信息不存在'));
            }
        }
        $admin_id = session('id', '');
        foreach ($ids as $id) {
            RechargeModel::startTrans();

            try {
                $info = RechargeModel::where('id', $id)
                    ->where("status", RechargeModel::STATUS_PENDING)
                    ->lock()
                    ->find();

                // 类型
                $type = $info['type'];
                $money = $info['money'];
                $userid = $info['user_id'];

                $pay_time = null;
                if ($status == RechargeRepository::STATUS_SUCCESS) {
                    $pay_time = timeToStr();
                }
                // 判断是不是首充
                $recharge_count = RechargeModel::where('user_id', $userid)
                    ->where('status', RechargeModel::STATUS_SUCCESS)
                    ->where('from', RechargeRepository::FROM_FRONT)
                    ->count();
                $is_first = false;
                if ($recharge_count == 0) {
                    $is_first = true;
                }

                $res = RechargeModel::where(['id' => $id])
                    ->where("status", RechargeModel::STATUS_PENDING)
                    ->update([
                        'status'   => $status,
                        'remark'   => $remark,
                        'admin_id' => $admin_id,
                        'pay_time' => $pay_time,
                    ]);
                if ($res && $status == RechargeRepository::STATUS_SUCCESS) {
                    $info->is_first = $is_first;
                    $info->save();
                    // 充值成功
                    $info = $info->toArray();
                    $info['is_first'] = $is_first;
                    $money_log = new MoneyLogModel();
                    $money_log->changeMoney($userid, $money, MoneyLogModel::TYPE_USER, 'add', '用户充值', '用户充值_' . $money, 1,
                        $admin_id);
                    $money_log->changeMoney($userid, $money, MoneyLogModel::TYPE_RECHARGE, 'add', '', '', 1,
                        $admin_id, '', '', false);
                    event('RechargeSuccess', $info);
                    insert_operation_log('审核充值:成功', "订单号:{$info['order_id']}");
                } else {
                    insert_operation_log('审核充值:失败', "订单号:{$info['order_id']}");
                }
                RechargeModel::commit();
            } catch (Exception $e) {
                Log::write($e->getMessage()."\n".$e->getTraceAsString(), "error");
                RechargeModel::rollback();
            }

        }
        return json(msg(1, '', '操作成功'));
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id, $status)
    {
        $button = [];
        switch ($status) {
            case 0:
                $button = [
                    '审核通过' => [
                        'auth'     => 'recharge/checkstatus',
                        'href'     => "javascript:checkStatus(".$id.", 2)",
                        'btnStyle' => 'primary',
                        'icon'     => 'fa fa-paste'
                    ],
                    '审核失败' => [
                        'auth'     => 'recharge/checkstatus',
                        'href'     => "javascript:checkStatus(".$id.", 3)",
                        'btnStyle' => 'danger',
                        'icon'     => 'fa fa-paste'
                    ],

                ];
                break;
        }
        if (in_array($status, [0, 3])) {
            $button['发送站内信'] = [
                'auth'     => 'recharge/sendmessage',
                'href'     => "javascript:sendMessage(".$id.")",
                'btnStyle' => 'info',
                'icon'     => 'fa fa-envelope'
            ];
        }

        return $button;
    }

    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws PDOException
     * @throws DbException
     * @throws Exception
     */
    public function add()
    {
        if ($this->request->isAjax()) {
            $user_id = $this->request->param('user_id/s');
            $win = $this->request->param('win/d', 0);
            $money = $this->request->param('money/f');
            $user = MemberModel::where('id', $user_id)->find();
            if (!$user) {
                $user = MemberModel::where('user_name', $user_id)->find();
            }
            if (!$user) {
                $this->error('用户信息不存在');
            }
            if ($money <= 0) {
                $this->error('充值金额有误');
            }
            $userid = $user->id;

            $data = [
                'order_id'       => genOrderSN(random(4, 'number'), 'MU'),
                'user_id'        => $userid,
                'original_money' => $money,
                'money'          => $money,
                'car_num'        => '9999999999',
                'type'           => RechargeModel::TYPE_BANK,
                'pay_id'         => 0,
                'is_xuni'        => $user->is_xuni,
                'remark'         => '手动补单',
                'status'         => 2,
                'pay_time'       => date("Y-m-d H:i:s"),
                'create_time'    => time(),
                'update_time'    => time(),
                'success_time'   => time(),
                'from'           => RechargeRepository::FROM_ADMIN,
                'is_first'       => 0, // 后台补单不算首充 2025-09-04 禅道执行#98
            ];
            $recharge_order = (new RechargeRepository)->create($data);
            if ($recharge_order) {
                $admin_id = session('id', '');
                $remarks = "后台补单到基础账户:{$money}";
                $money_log = new MoneyLogModel();
                $money_log->changeMoney($userid, $money, MoneyLogModel::TYPE_USER, 'add', '后台补单', $remarks, 1,
                    $admin_id);
                $money_log->changeMoney($userid, $money, MoneyLogModel::TYPE_RECHARGE, 'add', '', '', 1,
                    $admin_id, '', '', false);

//                $userModel = new UserModel();
//                $mode = ConfigModel::getConfigValue('level_upgrade_type');
                // 取消自动升级
//                $userModel->vipUpgradeStatus($user['leader1'], '', $mode);
//                $userModel->vipUpgradeStatus($userid, '', $mode);
//                TurntableCountModel::isAddOnePCount($userid, 1, $money);
                //充值完成判断上级是否可以领取pdd抽奖次数
//                PddTurntableCountModel::addLeader1Count($user_id, $money);
                UserModel::addRechargeTaskCount($userid);
                insert_operation_log('充值:手动补单', "订单号:{$data['order_id']}", $userid);
                // 恢复已匹配未支付的广告位订单
                (new FinanceProductOrderRepository())->recoverOrder($userid);
                $recharge_order = $recharge_order->toArray();
                $recharge_order['admin_id'] = $admin_id;
                event('RechargeSuccess', $recharge_order);
                $this->success('手动补单成功', url('index')->build(), $win ? 'close' : '');
            } else {
                $this->error('手动补单失败');
            }
        }
        $user_id = $this->request->param('user_id/d');
        View::assign('user_id', $user_id);
        View::assign('win', 1);
        return View::fetch();
    }


    public function checkNew()
    {
        $count = RechargeModel::count();
        $this->success('', '', $count);
    }

    /**
     * 归集查询结果
     * @param $id
     * @return int[]|mixed
     */
    public function swapCheck($id)
    {
        $recharge = RechargeModel::find($id);
        $collection = CollectionModel::where('batch_no', $recharge->batch_no)->find();

        if (!$collection) {
            $this->error(lang('归集记录不存在'));
        }

        // 归集结果
        $result = CollectionModel::swapRecord($recharge->order_id, $collection);

        $this->success(lang('查询成功').': '.CollectionDetailModel::$statusMap[$result['status']]);
    }

    // 单笔归集
    public function swapSingle($id)
    {
        $recharge = RechargeModel::find($id);
        try {
            $channel = $recharge->channel;
            $config = json_decode($channel->config, true);
            $chain = $config['chain'];
            $address = PaymentPlatformModel::getCollectionAddress($chain);
            $res = PaymentPlatformModel::swapSingle($address, $recharge->to_address, $recharge->money, $config['coin'],
                $chain);
            $data = $res['data'];

            // 记录归集订单号 $data['batchNo']
            $recharge->batch_no = $data['batchNo'];
            $recharge->save();

            CollectionModel::singleSwap($data, $recharge->to_address, $address, $recharge->order_id);
        } catch (UpayException|\Exception $e) {
            Log::write($e->getMessage(), 'upay');
            $this->success(lang('发起归集失败'));
        }
        $this->success(lang('发起归集成功'));
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

            $recharge = RechargeModel::find($param['id']);
            if (!$recharge) {
                return json(msg(-1, '', lang('充值记录不存在')));
            }

            $message = new UserMessageModel();
            $data = [
                'user_id' => $recharge['user_id'],
                'admin_id' => session('id'),
                'type' => UserMessageModel::TYPE_CUSTOM,
                'title' => $param['title'],
                'content' => $param['content'],
                'is_pop' => 1
            ];

            if (false === $message->save($data)) {
                return json(msg(-1, '', lang('发送失败')));
            }

            insert_operation_log('发送站内信', $data, $recharge['user_id']);
            return json(msg(1, '', lang('发送成功')));
        }
    }
}
