<?php

namespace app\api\controller;

use app\admin\model\HomeUserCardModel;
use app\admin\model\LevelModel;
use app\admin\model\MemberModel;
use app\admin\model\WithdrawModel;
use app\api\transform\PaymentPlatformTransform;
use app\common\model\ActivitynewRecordModel;
use app\common\model\ConfigModel;
use app\common\model\HomeUserBalanceModel;
use app\common\model\MemberLogModel;
use app\common\model\MoneyLogModel;
use app\common\model\PaymentPlatformModel;
use app\common\model\UserModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\LevelRepository;
use app\common\repository\WithdrawalRepository;
use think\facade\Db;
use think\Exception;
use think\facade\Log;
use think\response\Json;
use util\Locker;

class Withdrawals extends Base
{
    public function index(): Json
    {
        $platforms = PaymentPlatformModel::getAll('*', 'withdraw');
        $bank_platforms = $usdt_platforms = [];
        foreach ($platforms as $platform) {
            if ($platform['currency_type'] == PaymentPlatformModel::CURRENCY_TYPE_USDT) {
                $usdt_platforms[] = $platform;
            } else {
                if ($platform['currency_type'] == PaymentPlatformModel::CURRENCY_TYPE_BANK) {
                    $bank_platforms[] = $platform;
                }
            }
        }

        $contact_require = false;
        if ($this->configInfo['first_withdraw_input_contact'] == 1) {
            // 判断是不是首次提现
            $count = WithdrawModel::where('user_id', $this->userid)->count();
            if ($count == 0) {
                $contact_require = true;
            }
        }
        // 免费提现次数
        $free_withdraw_fee_num = ConfigModel::getConfigValue('free_withdraw_fee_num');
        // 每日提现次数
        $count = Db::name('withdrawals')
            ->where('user_id', $this->userid)
            ->whereTime("create_time", 'today')
            ->count();
        $day_count = max(ConfigModel::getConfigValue('day_tx_count') - $count, 0);
        // 提现最小手续费
        $withdraw_fee_min = ConfigModel::getConfigValue('withdraw_fee_min');
        // 最大手续费
        $withdraw_fee_max = ConfigModel::getConfigValue('withdraw_fee_max');
        // 提现手续费百分比
        $withdraw_rate = ConfigModel::getConfigValue('withdraw_rate');
        // 手续费扣除方式
        $withdraw_rate_method = ConfigModel::getConfigValue('withdraw_rate_method');

        return json(msg(0, [
            'contact_require' => $contact_require,
            'free_withdraw_fee_num' => intval(max($free_withdraw_fee_num - $count, 0)),
            'day_count' => intval($day_count),
            'withdraw_fee_min' => round(floatval($withdraw_fee_min), 2),
            'withdraw_fee_max' => round(floatval($withdraw_fee_max), 2),
            'withdraw_rate' => round(floatval($withdraw_rate), 2),
            'withdraw_rate_method' => intval($withdraw_rate_method),
            'platforms' => (new PaymentPlatformTransform())->transformCollection($platforms->toArray()),
            'bank_platforms' => (new PaymentPlatformTransform())->transformCollection($bank_platforms),
            'usdt_platforms' => (new PaymentPlatformTransform())->transformCollection($usdt_platforms),
            'user_water' => i18n_sprintf($this->userInfo['water_money']),
        ], ''));
    }

    public function store(): Json
    {
        (new Locker('withdrawal_lock_' . $this->userid))->lock();
        $userid = $this->userid; // 用户ID
        if (!$this->userInfo['withdraw_status']) {
            $is_status = (new ActivitynewRecordModel())->where(['reward_type' => 3, 'user_id' => $userid])->value('status');
            if ($is_status == 1) {
                return json(msg(-1, '', lang('余额有体验金额，暂无法提现')));
            }
            return json(msg(-1, '', lang('禁止提现，请联系客服')));
        }
        $money = input('money/f');
        $data = [
            'money' => $money
        ];
        $result = $this->validate($data, 'WithdrawalValidate');
        if (true !== $result) {
            return json(msg(-1, '', lang($result)));
        }
        // 所有用户都收流水限制 如果不是提现需要判断流水是否满足
        // 0-不限制 1-默认 2-限制
        if ($this->userInfo['user_water_status'] == 2) {
            return json(msg(0, [
                'code' => -1,
                'msg' => lang($this->configInfo['user_water_limit_tips'])
            ], lang('success')));
        } else if ($this->configInfo['withdraw_water_limit'] > 0 && $this->userInfo['user_water_status'] == 1) {
//            if ($this->userInfo['water_money'] != 0) {// del by zhang 2025年10月3日 修改提现审核逻辑
            //1、用户流水小于0，表示用户欠系统的钱。需要完成订单后补齐并且要把流水补齐（这你的流水没有补齐），此时不影响提现。
            //2、用户流水大于0，表示用户充值大于消费金额，需要把这笔钱消费完（流水等于0）时才可以进行提现
            if ($this->userInfo['water_money'] > 0) {
                return json(msg(0, [
                    'code' => -1,
                    'msg' => lang('流水不足无法提现')
                ], lang('success')));
            }
        }
        if ($this->configInfo['first_withdraw_is_min'] == 1) {
            // 判断是不是首次提现
            $count = WithdrawModel::where('user_id', $this->userid)->count();
            if ($count == 0) {
                $first_withdraw_min_money = $this->configInfo['first_withdraw_min_money'];
                if ($money < $first_withdraw_min_money) {
                    return json(msg(0, [
                        'code' => -1,
                        'msg' => '首次提现需达到' . $first_withdraw_min_money
                    ], lang('success')));
                }
            }
        }
        $wait_buy_finance_product_order_count = FinanceProductOrderRepository::getWaitBuyFinanceProductOrderCount($this->userid);
        if ($wait_buy_finance_product_order_count) {
            return json(msg(0, [
                'code' => -1,
                'msg' => lang('请完成订单库存之后再提现')
            ], lang('success')));
        }
        $password = input('password/s');
        $platform_id = input('platform_id/d');
        //手续费扣除方式 0从余额中扣除，1从提现金额中扣除
        $withdraw_rate_method = $this->configInfo['withdraw_rate_method'];


        if (!$this->userInfo['withdraw_status']) {
            return json(msg(-1, '', lang('禁止提现，请联系客服')));
        }

        if ($this->userInfo['is_demo'] == 1) {
            return json(msg(-1, '', lang('测试账号，不能使用该功能')));
        }

        // 提现是否需要实名认证证
//        $withdraw_need_kyc = $this->configInfo['withdraw_need_kyc'];
//        if ($withdraw_need_kyc && $this->userInfo['kyc_status'] == 0) {
//            return json(msg(-1, '', lang('请先完成实名认证')));
//        }

        if (isset($this->configInfo['withdraw_open']) && $this->configInfo['withdraw_open'] == 0) {
            return json(msg(-1, '', lang('提现系统维护中')));
        }

        if ($this->configInfo['withdraw_need_package_or_balance'] && $this->userInfo['level_id'] != 0) {
            //1、获取最新的提现记录和最新的广告位购买记录和余额宝记录
            //2、判断广告位或余额宝记录的时间是否大于提现记录的时间，如果大于则可以提现，否则不能提现

            $withdraw_record = (new WithdrawalRepository)->order(['create_time' => 'desc'])
                ->findWhere([['user_id', '=', $userid]]);


            $finance_product_order_record = (new FinanceProductOrderRepository)->order(['create_time' => 'desc'])
                ->findWhere([
                    ['user_id', '=', $userid],
                    ['status',
                        'in',
                        [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]
                    ]
                ]);
            $home_user_balance_record = HomeUserBalanceModel::where('user_id', $userid)
                ->order(['create_time' => 'desc'])
                ->find();

            // 检查是否有广告位或余额宝记录
            if (!$finance_product_order_record && !$home_user_balance_record) {
                return json(msg(0, [
                    'code' => -1,
                    'msg' => lang('请记住：要提取资金，您必须首先购买广告库存。感谢您的信任！')
                ], lang('success')));
            }

            // 如果有提现记录,检查时间限制
            if ($withdraw_record) {
                $withdraw_time = $withdraw_record['create_time'];
                $can_withdraw = false;

                // 检查广告位记录时间
                if ($finance_product_order_record && $withdraw_time < $finance_product_order_record['create_time']) {
                    $can_withdraw = true;
                }

                // 检查余额宝记录时间
                if ($home_user_balance_record && $withdraw_time < $home_user_balance_record['create_time']) {
                    $can_withdraw = true;
                }

                if (!$can_withdraw) {
                    return json(msg(0, [
                        'code' => -1,
                        'msg' => lang('请记住：要提取资金，您必须首先购买广告库存。感谢您的信任！')
                    ], lang('success')));
                }
            }
        }

        //判断用户是否有推荐限制提现
        //判断邀请人是否满足提现条件
        $withdraw_rec_count = $this->userInfo['withdraw_rec_count'];
        if (!empty($withdraw_rec_count)) {
            $invite_user = Db::name('home_user')->where('leader1', $this->userInfo['id'])->column('id');
            if (count($invite_user) < $withdraw_rec_count) {
                return json(msg(-1, '', lang('当前推荐人数量不足')));
            }
        }
        //判断邀请人充值金额
        $withdraw_rec_recharge = $this->userInfo['withdraw_rec_recharge'];
        if (!empty($withdraw_rec_recharge)) {
            $invite_user = Db::name('home_user')->where('leader1', $this->userInfo['id'])->column('id');
            $recharge_money = 0;
            if ($invite_user) {
                $recharge_money = Db::name('recharge')
                    ->where('user_id', 'in', $invite_user)
                    ->where('status', 2)
                    ->sum('money');
            }
            if ($recharge_money < $withdraw_rec_recharge) {
                return json(msg(-1, '', lang('当前推荐人充值不足')));

            }
        }

        // vip0是否可以提现
        if (isset($this->configInfo['vip0_is_withdraw']) && empty($this->configInfo['vip0_is_withdraw']) && $this->userInfo['level_id'] === 0) {
            return json(msg(-1, '', lang('当前等级不允许提现')));
        }

        // 如果用户有任务，不允许提现
        if ($this->userInfo['plan_id']) {
            return json(msg(-1, '', lang('请先完成所有任务')));
        }

        // 获取vip提现星期
        $level = (new LevelRepository())->getLevelByLevelID($this->userInfo['level_id']);
        if (!$level) {
            return json(msg(-1, '', lang('当前等级不允许提现')));
        }

        if ($level->withdraw_week != '') {
            $withdraw_day = explode(",", $level->withdraw_week);
            if (!in_array(date('N'), $withdraw_day)) {
                if (in_array(date('N'), [6, 7])) {
                    return json(msg(-1, '', lang('周末无法提现审核，请在工作日申请提现')));
                }
                return json(msg(-1, '', lang('今天不能提现')));
            }
        }

        if (isset($this->configInfo['withdraw_time_start']) && !empty($this->configInfo['withdraw_time_start'])
            && isset($this->configInfo['withdraw_time_end']) && !empty($this->configInfo['withdraw_time_end'])
        ) {
            $time = time();  // 时间
            $start_time = strtotime(date('Y-m-d') . $this->configInfo['withdraw_time_start']);
            $end_time = strtotime(date('Y-m-d') . $this->configInfo['withdraw_time_end']);
            if ($time < $start_time || $time > $end_time) {
                return json(msg(-1, '',
                    lang('提现时间为') . ":" . $this->configInfo['withdraw_time_start'] . '-' . $this->configInfo['withdraw_time_end']));
            }
        }


        $platform = PaymentPlatformModel::find($platform_id);
        if (!$platform) {
            return json(msg(-1, '', lang('参数错误')));
        }
        $config = json_decode($platform->config, true);
        $bank_number = '';
        switch ($platform->currency_type) {
            case 1: // 虚拟币
                $bank_number = input('bank_number/s');
                if (!$bank_number) {
                    return json(msg(-1, '', lang('请输入提现地址')));
                }
                $true_name = $this->userInfo['true_name'] ?? '';
                $bank_name = $this->userInfo['bank_name'] ?? '';
                $bank_code = $this->userInfo['bank_name'] ?? '';;
                break;
            case 2: // 货币
            case 3: // 银行卡
                $card_id = input('card_id/s', '');
                $card_info = HomeUserCardModel::where('id', $card_id)->find();
                if (empty($card_info)) {
                    return json(msg(-1, '', lang('请先绑定提现账号')));
                }
                $bank_number = $card_info['bank_number'];
                $true_name = $card_info['true_name'];
                $bank_name = $card_info['bank_name'];
                $bank_code = $card_info['bank_code'];
                break;
        }

        // 获取今日提现次数
        $day_count = $this->configInfo['day_tx_count'];
        $count = Db::name('withdrawals')
            ->where('user_id', $userid)
            ->whereTime("create_time", 'today')
            ->count();
        if ($day_count > 0 && $count >= $day_count) {
            return json(msg(-1, '', lang('每天只能申请%d次提现', [$day_count])));
        }


        $usdt_taxfee = 0;

        // 最小手续费
        if ($this->configInfo['withdraw_fee_min'] > 0) {
            $usdt_taxfee = $this->configInfo['withdraw_fee_min'];
        }

        // if (!$withdraw_address) {
        //     return json(msg(-1, '', lang('请输入提现地址')));
        // }

        if (!$password) {
            return json(msg(-1, '', lang('请输入安全密码')));
        }


        $password_hash = md5($password . config('app.salt'));   // 密码
        if ($this->userInfo['paypwd'] != $password_hash) {
            return json(msg(-1, '', lang('安全密码错误')));
        }

        $usdt_money = $money; // 转换为usdt
        // 获取手续费
        if ($this->configInfo['withdraw_rate'] > 0) {
            //免手续费金额 单日免手续费笔数
            if ($this->configInfo['free_withdraw_fee_num'] > 0 && $count < $this->configInfo['free_withdraw_fee_num']) {
                $usdt_taxfee = 0;
            } else {
                $usdt_taxfee = round($usdt_money * $this->configInfo['withdraw_rate'], 2);
                // 最大手续费判断
                if ($this->configInfo['withdraw_fee_max'] > 0 && $usdt_taxfee > $this->configInfo['withdraw_fee_max']) {
                    // 手续费大于最大手续费，取最大手续费
                    $usdt_taxfee = min($this->configInfo['withdraw_fee_max'], $usdt_taxfee);
                }
            }
        }
        $total = $withdraw_rate_method == 1 ? $usdt_money : $usdt_money + $usdt_taxfee;
        if ($this->userInfo[MoneyLogModel::$TYPE_FIELDS[MoneyLogModel::TYPE_BROKERAGE]] < $total) {
            return json(msg(-1, '', lang('余额不足')));
        }

        // 校验最小、最大提现金额
        {
            if ($this->configInfo['min_withdraw_config'] == 'global') { // 根据提现币种的提现最小金额配置
                //
                if ($usdt_money < $platform->min_withdraw_money) {
                    return json(msg(-1, '', lang('出金金额低于最低限额 %s', [i18n_currency($platform->min_withdraw_money)])));
                }
            } else {
                if ($this->configInfo['min_withdraw_config'] == 'local') // 优先级个人设置>VIP设置>币种设置
                {

                    // 判断个人最大提现
                    if ($this->userInfo['withdraw_max_usdt'] > 0) {
                        // 判断金额
                        if ($usdt_money > $this->userInfo['withdraw_max_usdt']) {
                            return json(msg(-1, '', lang('平台最高提现金额 %f',
                                [i18n_sprintf($this->userInfo['withdraw_max_usdt'])])));
                        }
                    } else {
                        // 获取用户vip配置
                        $level = LevelModel::where('level_id', $this->userInfo['level_id'])->find();
                        if (!$level) {
                            return json(msg(-1, '', lang('当前等级不允许提现')));
                        }

                        if ($level->withdraw_max_usdt > 0) {
                            $withdraw_max = min($platform->max_withdraw_money, $level->withdraw_max_usdt);
                        } else {
                            $withdraw_max = $platform->max_withdraw_money;
                        }

                        if ($usdt_money > $withdraw_max) {
                            return json(msg(-1, '', lang('平台最高提现金额 %f', [i18n_sprintf($withdraw_max)])));
                        }
                    }
                }
            }
        }


//            $withdraw_limit = UserService::getUserWithdrawLimit($this->userid);
//            if (isArrayAndNotEmpty($withdraw_limit)) {
//                $this->result([], -1, lang($withdraw_limit['remark']));
//            }

//            $lock = userLock($this->userid, 'usercash', 60);
//            if (!$lock) {
//                $this->result([], -1, lang('请勿重复提交'));
//            }
        // 获取用户提现总次数
        $withdrawal_count = (new WithdrawalRepository)->model()
            ->where('user_id', $userid)
            ->count();
        $withdraw_no_auth_times = $this->configInfo['withdraw_no_auth_times']; // 免审核次数
        $status = WithdrawalRepository::STATUS_PENDING;
        if ($withdrawal_count < $withdraw_no_auth_times || $platform->currency_type === PaymentPlatformModel::CURRENCY_TYPE_USDT) {
            $status = WithdrawalRepository::STATUS_WAIT;
        }

        $order_id = genOrderSN(random(6, 'number'), 'W');
        $withdraw_money = $withdraw_rate_method == 0 ? $usdt_money : sprintf4f($usdt_money - $usdt_taxfee); // 实际到账
        $data = [
            'user_id' => $userid,
            'order_id' => $order_id,
            'exchange_rate' => $platform->exchange_rate, // 汇率
            'original_money' => $usdt_money, // 原始金额
            'withdraw_money' => $withdraw_money * $platform->exchange_rate,
            'convert_money' => $withdraw_money, // 出款金额
            'handling_fee' => $usdt_taxfee * $platform->exchange_rate,
            'true_name' => $true_name ?? '',
            'bank_name' => $bank_name ?? '',
            'bank_code' => $bank_code ?? '',
            'bank_number' => $bank_number,
//            'bank_kh'            => $this->userInfo['bank_kh'] ?? '',
//            'bank_address'       => $this->userInfo['bank_address'] ?? '',
//            'document_type'      => $this->userInfo['document_type'] ?? '',
//            'document_id'        => $this->userInfo['document_id'] ?? '',
            'is_xuni' => $this->userInfo['is_xuni'],
            'channel' => $platform_id,
            'withdrawal_address' => $bank_number,
            'ip' => getip(),
            'create_time' => time(),
            'status' => $status,
            'type' => $platform->currency_type
        ];

        Db::startTrans();
        try {
            $order_id = Db::name('withdrawals')->insertGetId($data);

            if ($order_id) {
                $money_log = new MoneyLogModel();
                $money_log->changeMoney($userid, $withdraw_money + $usdt_taxfee, MoneyLogModel::TYPE_FROZEN, 'add',
                    '提现申请', '', 2, '', '', $order_id, true, $this->userInfo['is_xuni'], false);
                $money_log->changeMoney($userid, $withdraw_money + $usdt_taxfee, MoneyLogModel::TYPE_BROKERAGE, 'sub',
                    '提现申请', '', 2, '', '', $order_id, true, $this->userInfo['is_xuni'], true);
                if ($usdt_taxfee > 0) {
                    $money_log->changeMoney($userid, $usdt_taxfee, MoneyLogModel::TYPE_BROKERAGE, 'sub',
                        '提现申请手续费', '', 2, '', '', $order_id, true, $this->userInfo['is_xuni'], true);
                }
                Db::commit();
                MemberLogModel::insertLoginLog($userid, MemberLogModel::WITHDRAW);
                return json(msg(0, '', lang('申请成功')));
            } else {
                return json(msg(-1, '', lang('申请失败')));
            }
        } catch (Exception $exc) {
            Log::write($exc->getMessage() . "\n" . $exc->getTraceAsString(), "error");
            // 回滚事务
            Db::rollback();
            return json(msg(-1, '', $exc->getMessage()));
        }
    }
}
