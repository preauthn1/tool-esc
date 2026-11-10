<?php

namespace app\api\controller;

use app\admin\model\RechargeModel;
use app\api\transform\PaymentPlatformTransform;
use app\common\model\ConfigModel;
use app\common\model\MemberLogModel;
use app\common\model\PaymentPlatformModel;
use app\common\repository\ActivityNewPasswordRepository;
use app\common\repository\RechargeRepository;
use think\Exception;
use think\facade\Db;
use think\response\Json;

class Recharge extends Base
{
    public function index(): Json
    {
        $user_id = $this->userid;

        $recharge_channel = PaymentPlatformModel::getAll('*', 'recharge');
        // 判断是不是首充
        $recharge_count = RechargeModel::where('user_id', $user_id)
            ->where('status', RechargeModel::STATUS_SUCCESS)
            ->where('from', RechargeRepository::FROM_FRONT)
            ->count();
        $config_model = new ConfigModel();
        $config = $config_model->getConfig();
        $first_charge_rebate_min = 0; // 首充送款最低金额
        $first_charge_rebate_max = 0; // 首充送款最高金额
        $first_charge_rebate_percent = 0; // 首充送款比例
        $first_charge_rebate_cap = 0; // 首充送款封顶
//        $is_today_register = explode(' ', $this->userInfo['create_time'])[0] == date('Y-m-d');
//        if ($is_today_register && $recharge_count == 0 && $config['is_open_first_charge_rebate']) { // 首充送款开关
        if ($recharge_count == 0 && $config['is_open_first_charge_rebate']) { // 首充送款开关
            // 如果开启首充送款，当日没有充值过的，获取首充渠道
            $recharge_channel = $recharge_channel->where('is_first_charge', 1);
//            if ($is_today_register) { // 如果是当天注册
//                $first_charge_rebate_min = sprintf4f($config['first_charge_rebate_min']); // 首充送款最低金额
//                $first_charge_rebate_max = sprintf4f($config['first_charge_rebate_max']); // 首充送款最高金额
//                $first_charge_rebate_percent = sprintf4f($config['first_charge_rebate_percent']); // 首充送款比例
//                $first_charge_rebate_cap = sprintf4f($config['first_charge_rebate_cap']); // 首充送款封顶
//            }
            $first_charge_rebate_min = sprintf4f($config['first_charge_rebate_min']); // 首充送款最低金额
            $first_charge_rebate_max = sprintf4f($config['first_charge_rebate_max']); // 首充送款最高金额
            $first_charge_rebate_percent = sprintf4f($config['first_charge_rebate_percent']); // 首充送款比例
            $first_charge_rebate_cap = sprintf4f($config['first_charge_rebate_cap']); // 首充送款封顶
        } else {
            $recharge_channel = $recharge_channel->where('is_first_charge', 0);
        }
        $data = (new PaymentPlatformTransform())->transformCollection($recharge_channel->toArray());
        foreach ($data as $key => $value) {
            $data[$key]['first_charge_rebate_min'] = $first_charge_rebate_min;
            $data[$key]['first_charge_rebate_max'] = $first_charge_rebate_max;
            $data[$key]['first_charge_rebate_percent'] = $first_charge_rebate_percent;
            $data[$key]['first_charge_rebate_cap'] = $first_charge_rebate_cap;
        }
        return json(msg(0, $data, lang('success')));
    }


    // 获取充值地址
    public function getRechargeAddress()
    {
        $platform_id = $this->request->param("platform_id/d");
        $activity_id = $this->request->param("activity_id/d", 0);
        $recharge_bonus_id = $this->request->param("recharge_bonus_id/d", 0);
        if (!$platform_id) {
            // 获取默认
            $platform = PaymentPlatformModel::where('recharge_is_show', 1)->find();
        } else {
            $platform = PaymentPlatformModel::find($platform_id);
        }

        if (!$platform) {
            return json(msg(-1, '', lang('参数错误')));
        }

        $user_id = $this->userid;
        // 判断是不是首充
        $recharge_count = RechargeModel::where('user_id', $user_id)
            ->where('status', RechargeModel::STATUS_SUCCESS)
            ->where('from', RechargeRepository::FROM_FRONT)
            ->count();
        $is_today_register = explode(' ', $this->userInfo['create_time'])[0] == date('Y-m-d');
        if (($recharge_count !== 0) && $platform->is_first_charge) {// del [!$is_today_register || ]
            return json(msg(-1, '', lang('请选择其他支付方式')));
        }
        $money = $this->request->param("money/f", 1);
        if ($platform->currency_type == 2) { // 1-虚拟币 2-货币
            if ($money < $platform->min_recharge_money) {
                return json(msg(-1, '',
                    lang('入金金额低于最低限额 %s', [i18n_currency($platform->min_recharge_money)])));
            }
        }

        // 增加验证器
        if ($activity_id) {
            // 判断活动状态
            $join_status = (new ActivitynewPasswordRepository)->checkPasswordStatus($this->userid, $activity_id);
            if (!$join_status) {
                return json(msg(-1, [], lang('请重新输入活动解锁密码')));
            }
        }

        $config = json_decode($platform->config, true);

        switch ($platform->currency_type) {
            case 1: // 虚拟币
                break;
            case 2: // 货币
                try {

                    $data = [
                        'money' => $money
                    ];
                    $result = $this->validate($data, 'RechargeValidate');
                    if (true !== $result) {
                        return json(msg(-1, '', lang($result)));
                    }
                    // 创建订单
                    $order = RechargeModel::create([
                        'order_id'          => genOrderSN(random(6, 'number'), 'RE'),
                        'money'             => $money,
                        'original_money'    => $money,
                        'user_id'           => $this->userid,
                        'platform_id'       => $platform->id,
                        'amount'            => $money,
                        'currency_type'     => $platform->currency_type,
                        'type'              => RechargeModel::TYPE_THIRD,
                        'status'            => 1, // 状态 1 待支付 2 通过 3 失效
                        'trade_no'          => '',
                        'pay_id'            => $platform_id,
                        'create_time'       => time(),
                        'update_time'       => time(),
                        'activity_id'       => $activity_id,
                        'recharge_bonus_id' => $recharge_bonus_id,
                        'from'              => RechargeRepository::FROM_FRONT
                    ]);
                    $class_name = "pay\\".$config['class_name'];
                    if (!class_exists($class_name)) {
                        return json(msg(-1, '', lang('支付方式不存在,请联系客服')));
                    }
                    $url = (new $class_name($config))->pay($order);
                    return json(msg(0, ['url' => $url], lang('获取成功')));
                } catch (Exception $e) {
                    return json(msg(-1, '', $e->getMessage()));
                }

                break;

        }
        $recharge_address = '';
        switch ($config['chain']) {
            case 'TRC20':
                if ($this->userInfo['recharge_address']) {
                    $recharge_address = $this->userInfo['recharge_address'];
                }
                break;
            case 'BEP20':
                if ($this->userInfo['bsc_address']) {
                    $recharge_address = $this->userInfo['bsc_address'];
                }
                break;
        }
        if (!$recharge_address) {
            $user_id = $this->userInfo['id'];
            try {
                $recharge_address = PaymentPlatformModel::getRechargeAddress($user_id, $platform->id);
            } catch (Exception $e) {
                return json(msg(-1, '', $e->getMessage()));
            }
        }


        if (!$recharge_address) {
            return json(msg(-1, '', lang('获取充值地址失败，请联系客服')));
        }

        $platform->instruction = lang($platform->instruction);
        return json(msg(0, compact('recharge_address'), ''));
    }

    public function lists()
    {
        $userid = $this->userid;

        $start_time = strtotime('-7 days');
        $end_time = strtotime('+1 days');

        $page = $this->request->param('page/d', 1);   // 页数
        $limit = $this->request->param('pageSize/d', 10);   // 页数
        $offset = ($page >= 1 ? $page - 1 : 0) * $limit;
        $where[] = ['user_id', '=', $userid];
        $where[] = ['create_time', 'between', [$start_time, $end_time]];
        $where[] = ['type', '<>', RechargeRepository::TYPE_THIRD];
        $lists = [];
        if (empty($lists)) {
            $lists = Db::name('recharge')->where($where)->limit($offset, $limit)->order('id DESC')->select();
        }
        $recharge_status = config('app.recharge_status');
        foreach ($lists as $k => $val) {
            $val['create_time'] = i18n_datetime2str($val['create_time']);
            $val['remark'] = isset($val['remark']) && $val['remark'] ? lang($val['remark']) : '-';
            $val['status_str'] = isset($recharge_status[$val['status']]) ? lang($recharge_status[$val['status']]) : '';
            if ($val['status'] == RechargeRepository::STATUS_PENDING && $val['type'] != RechargeRepository::TYPE_THIRD) {
                $val['is_btn'] = 'block';
            } else {
                $val['is_btn'] = 'none';
            }
            $lists[$k] = $val;
        }
        return json(msg(0, $lists, ''));

    }

    // 撤销充值订单
    public function cancelOrder(): Json
    {
        $id = $this->request->param('id/d');  // 订单ID
        $order = Db::name('recharge')
            ->where('user_id', $this->userid)
            ->where('id', $id)
            ->where('type', "<>", RechargeRepository::TYPE_THIRD)
            ->find();
        if (!$order) {
            return json(msg(-1, '', lang('信息有误')));
        }
        if ($order['status'] != RechargeRepository::STATUS_PENDING) { // 订单不是待审核
            return json(msg(-1, '', lang('非法操作')));
        }
        $ret = Db::name('recharge')
            ->where('id', $id)
            ->update(['status' => RechargeRepository::STATUS_FAIL]); // 状态 1 待支付 2 通过 3 失效
        if ($ret) {
            return json(msg(0, '', lang('success')));
        } else {
            return json(msg(-1, '', lang('操作失败')));
        }
    }


    public function offline_pay()
    {
        $money = $this->request->param("money/f");
        $platform_id = $this->request->param("platform_id/d");
        $order_id = $this->request->param("order_id/d");
        $car_num = $this->request->param("car_num/s");
        $user_note = $this->request->param("user_note/s");
        $bank_user_name = $this->request->param("bank_user_name/s");
        $bank_account = $this->request->param("bank_account/s", '');
        $img = $this->request->param("img/s");
        $channel = $this->request->param("channel/s");

        if (!$img) {
            return json(msg(-1, '', lang('请上传支付凭证')));
        }

        $channel_arr = json_decode($channel, true);
        if (!$channel_arr || !array_key_exists('channel', $channel_arr) || !array_key_exists('type', $channel_arr) || !array_key_exists('name', $channel_arr) || !array_key_exists('account', $channel_arr)) {
            return json(msg(-1, '', lang('参数错误')));
        }

        $data = [
            'money' => $money
        ];
        $this->validate($data, 'RechargeValidate');
        $platform = PaymentPlatformModel::find($platform_id);
        if (!$platform) {
            return json(msg(-1, '', lang('参数错误')));
        }
        $user_id = $this->userid;
        // 判断是不是首充
        $recharge_count = RechargeModel::where('user_id', $user_id)
            ->where('status', RechargeModel::STATUS_SUCCESS)
            ->where('from', RechargeRepository::FROM_FRONT)
            ->count();
        $is_today_register = explode(' ', $this->userInfo['create_time'])[0] == date('Y-m-d');
        if (($recharge_count !== 0) && $platform->is_first_charge) {// del 【 !$is_today_register || 】移除判断
            return json(msg(-1, '', lang('请选择其他支付方式')));
        }

        if ($money < $platform->min_recharge_money) {
            return json(msg(-1, '', lang('入金金额低于最低限额 %s', [i18n_currency($platform->min_recharge_money)])));
        }


        $type = RechargeModel::TYPE_BANK;


        if ($order_id > 0) {
            $order = RechargeModel::where('id', $order_id)->find();
            if ($order) {
                $order->car_num = $car_num;
                $order->save();
                return json(msg(200, '', lang('提交成功')));
            }
        }
        $info = Db::name('recharge')->whereTime('create_time', 'today')
            ->where(['type' => $type, 'status' => RechargeModel::STATUS_PENDING, 'user_id' => $user_id])
            ->find();
        if ($info) {
            return json(msg(-1, '', lang('当前有1笔充值订单未完成，请查看充值记录完成或撤销')));
        }


        $data = [
            'order_id'       => genOrderSN(random(6, 'number'), 'RE'),
            'user_id'        => $user_id,
            'original_money' => $money,
            'money'          => $money,
            'car_num'        => $bank_account,
            'pay_account'    => $bank_user_name,
            'type'           => RechargeModel::TYPE_BANK,
            'pay_id'         => $platform_id,
            'is_xuni'        => $this->userInfo['is_xuni'],
            'status'         => 0,
            'img'            => $img,
            'user_note'      => $user_note,
            'create_time'    => time(),
            'update_time'    => time(),
            'success_time'   => time(),
            'from'           => RechargeRepository::FROM_FRONT,
            'extra'          => $channel
        ];

        $ret = Db::name('recharge')->insert($data);
        if ($ret) {
            //记录充值日志
            MemberLogModel::insertLoginLog($this->userid, MemberLogModel::RECHARGE);
            return json(msg(0, [], lang('提交成功')));
        } else {
            return json(msg(-1, '', lang('提交失败')));
        }
    }
}
