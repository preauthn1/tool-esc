<?php

namespace app\api\controller;

use app\admin\model\ArticleModel;
use app\admin\model\HomeUserCardModel;
use app\admin\model\MemberModel;
use app\api\transform\PromotionRewardDayTransform;
use app\api\transform\UserTransform;
use app\common\model\BankModel;
use app\common\model\ConfigModel;
use app\common\model\FinanceProductOrderModel;
use app\common\model\HomeUserBalanceModel;
use app\common\model\HomeUserVirtualModel;
use app\common\model\MemberCreditScoreModel;
use app\common\model\MemberLogModel;
use app\common\model\MemberRealNameAuthModel;
use app\common\model\MoneyLogModel;
use app\common\model\SignModel;
use app\common\model\TurntableCountModel;
use app\common\model\TurntableLogModel;
use app\common\model\TurntableModel;
use app\common\model\TurntablePrizeModel;
use app\common\model\UserModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\MoneyLogRepository;
use app\common\repository\PromotionRewardDayRepository;
use app\common\repository\PromotionRewardRecordRepository;
use app\lib\exception\LockException;
use Carbon\Carbon;
use think\db\exception\DbException;
use think\facade\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\facade\Validate;
use think\response\Json;
use util\Locker;

class User extends Base
{
    public function info(UserTransform $userTransform): Json
    {
        // 获取当前用户信息
        $data = $userTransform->transform($this->userInfo);
        //finance_product_button_count_down
        if (!$data['finance_product_button_count_down']) { // 没有单控
            $tree_ids = explode('/', $this->userInfo['tree_id']);
            // 倒序
            $tree_ids = array_reverse($tree_ids);
            foreach ($tree_ids as $tree_id) {
                // 获取上级用户的信息
                $finance_product_button_count_down = MemberModel::where('id', $tree_id)->value('finance_product_button_count_down');
                if ($finance_product_button_count_down) {
                    $data['finance_product_button_count_down'] = $finance_product_button_count_down;
                    break;
                }
            }
            // 没有群控
            if (!$data['finance_product_button_count_down']) {
                // 使用平台控
                $data['finance_product_button_count_down'] = ConfigModel::getConfigValue('finance_product_button_count_down');
            }
        }

        if ($this->userInfo['is_xuni'] == 1 && $this->userInfo['show_virtual_data'] == 1) {
            // 'user_money' => $user_data['user_money'],// 用户余额
            //                'user_income' => $user_data['user_income'],// 用户收益
            //                'user_brokerage_income' => $user_data['user_brokerage_income'],// 用户佣金收益
            //                'user_recharge_money' => $user_data['user_recharge_money'],// 用户充值金额
            //                'user_withdrawal_amount' => $user_data['user_withdrawal_amount'],// 用户提现金额
            //                'user_team_num' => $user_data['user_team_num'],// 用户团队人数
            //                'user_today_team_num' => $user_data['user_today_team_num']// 用户今日团队人数
            $data_virtual = HomeUserVirtualModel::getUserVirtual($this->userid);
            $data['user_money'] = $data_virtual['user']['user_money'];
            $data['total_income'] = $data_virtual['user']['user_income'];
            $data['total_brokerage_income'] = $data_virtual['user']['user_brokerage_income'];
            $data['recharge_money'] = $data_virtual['user']['user_recharge_money'];
            $data['withdrawal_amount'] = $data_virtual['user']['user_withdrawal_amount'];
            $data['team_num'] = $data_virtual['user']['user_team_num'];
            $data['today_team_num'] = $data_virtual['user']['user_today_team_num'];
            $data['credit_score'] = $data_virtual['user']['credit_score'];
            $data['credit_score_detail'] = [
                'trade' => $data_virtual['credit_score_trade'],
                'active' => $data_virtual['credit_score_active'],
                'new' => $data_virtual['credit_score_new'],
            ];
        } else {
            $team_ids = UserModel::where('tree_id', 'like', "%/{$this->userid}/%")
                ->where('tree_id', 'not like', '%/' . $this->userid . '/')
                ->column('id');
//            $team_ids = UserModel::getTeamAllIds($this->userid, 7, false, []);
            $data['team_num'] = count($team_ids); // 团队人数
//            $today_team_ids = UserModel::getTeamAllIds($this->userid, 7, false, ['start_time' => strtotime(date('Y-m-d')), 'end_time' => time()]);
            $today_team_ids = UserModel::where('tree_id', 'like', "%/{$this->userid}/%")
                ->where('tree_id', 'not like', '%/' . $this->userid . '/')->whereBetween('create_time', [strtotime(date('Y-m-d')), time()])
                ->column('id');
            $data['today_team_num'] = count($today_team_ids); // 今日新增团队人数
            // 任务收益总额
            // 3任务返利 4储值返利 5抽奖活动 6签到活动 7升级返利 8分享奖励 9一级返利 10二级返利 11三级返利 12广告位理财 13AI智能托管理财 14AI智能托管收益 15直推奖励 16广告位收益
            $money_repository = new MoneyLogRepository();
            $data['total_income'] = i18n_currency($money_repository->getTotalMoney([$this->userid], [3, 4, 5, 6, 9, 10, 11, 14, 15, 16]));// 任务收益 + 投资收益
            // 下级返佣总额
            $data['total_brokerage_income'] = i18n_currency($money_repository->getTotalMoney([$this->userid], [4, 9, 10, 11]));

            $data['credit_score_detail'] = [
                'trade' => 0,
                'active' => 0,
                'new' => 0,
            ];
            $data['credit_score'] = 0;
            $credit_score_trade_num = ConfigModel::getConfigValue('credit_score_trade_num');
            $credit_score_active_num = ConfigModel::getConfigValue('credit_score_active_num');
            $credit_score_new_num = ConfigModel::getConfigValue('credit_score_new_num');
            // 获取当月各类型信用分分别的总和
            $credit_score = MemberCreditScoreModel::getUserCreditScore($this->userid);
            if ($credit_score) {
                $data['credit_score_detail'] = [
                    'trade' => round($credit_score['trade'] / intval(explode('_', $credit_score_trade_num)[0]) * 100, 2),
                    'active' => round($credit_score['active'] / intval(explode('_', $credit_score_active_num)[0]) * 100, 2),
                    'new' => round($credit_score['new'] / intval(explode('_', $credit_score_new_num)[0]) * 100, 2),
                ];
                $data['credit_score'] = $credit_score['credit_score'];
            }

            $ai_advance = 0;
            // 获取用户最新已购买的流量包
            $finance_product_order = FinanceProductOrderModel::where('user_id', $this->userid)
                ->whereIn('status', [FinanceProductOrderRepository::STATUS_WAIT_BUY, FinanceProductOrderRepository::STATUS_PURCHASED])
                ->order(['id' => 'desc'])
                ->find();
            if ($finance_product_order) {
                $last_money = MoneyLogModel::where('user_id', $this->userid)
                    ->where('order_id', $finance_product_order->id)
                    ->value('last_money');
                if ($last_money <= $finance_product_order->overdue_money) {
                    $ai_advance = abs($finance_product_order->overdue_money);
                } else {
                    $ai_advance = abs($last_money);
                }
            }
            $data['ai_advance'] = $ai_advance;
        }
        $official_website_url = ConfigModel::getConfigValue('official_website_url');
        $data['share_link'] = $official_website_url . "/#/register?type=2&ic=" . $data['invite_code'];
        return json(msg(0, $data, ''));
    }

    public function transfer(): Json
    {
        $param = input('post.');
        $amount = $this->request->param("amount/f", 0);
        if (!$amount || $amount <= 0) {
            $this->error(lang('请输入正确的数量'));
        }
        if (!$param['password']) {
            $this->error(lang('请输入密码'));
        }
        if ($amount > $this->userInfo['brokerage_money']) {
            $this->error(lang('兑换金额不能大于目前账户金额'));
        }

        // 验证密码
        $password = md5($param['password'] . config('app.salt'));
        if ($password != $this->userInfo['password']) {
            $this->error(lang('密码错误'));
        }

        $money_log = new MoneyLogModel();
        $money_log->changeMoney($this->userid, $amount, MoneyLogModel::TYPE_BROKERAGE, 'sub', '转账扣除');
        $money_log->changeMoney($this->userid, $amount, MoneyLogModel::TYPE_USER, 'add', '转账账户',
            '用户转移_' . $amount);
        return json(msg(0, '', lang('转账成功')));
    }

    /**
     * 修改用户登录密码
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @throws PDOException
     */
    public function change_password(): Json
    {
        $userid = $this->userid;
        $step = input('step/d', 2);

        $oldpassword = input('oldpassword');
        $password = input('password');
        $repassword = input('repassword');
        if (!$password) {
            return json(msg(-1, '', lang('缺少必要参数')));
        }

        $password_len = strlen($password);
        if ($password_len > 20 || $password_len < 6) {
            return json(msg(-1, '', lang('密码长度应在6-20位之间')));
        }

        if ($password !== $repassword) {
            return json(msg(-1, '', lang('两次密码不一致')));
        }
        $info = Db::name('home_user')->where(['id' => $userid])->find();
        $oldpassword2 = md5($oldpassword . config('app.salt'));   // 密码
        if ($info['password'] != $oldpassword2 && !password_verify($oldpassword, $info['password'])) {
            return json(msg(-1, '', lang('旧密码错误')));
        }

        $data = [
            'password' => md5($password . config('app.salt')),   // 密码
        ];
        if ($step == 2) {
            // 生成新的login_key使原token失效
            $login_key = random(32);
            $data['login_key'] = $login_key;
        }

        $ret = Db::name('home_user')->where(['id' => $userid])->update($data);
        if ($ret) {
            //记录日志
            MemberLogModel::insertLoginLog($userid, MemberLogModel::CHANGE_PASSWORD);
            return json(msg(0, '', lang('修改成功') . ($step == 2 ? "," . lang('请重新登录') : "")));
        } else {
            return json(msg(-1, '', lang('修改失败')));
        }
    }

    /**
     * 修改用户登录安全密码
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @throws PDOException
     */
    public function change_paypwd(): Json
    {
        $userid = $this->userid;
        $data = [];

        $oldpassword = input('oldpassword');
        $password = input('password');
        $repassword = input('repassword');
        if (!$password) {
            return json(msg(-1, '', lang('缺少必要参数')));
        }

        $password_len = strlen($password);
        if ($password_len > 20 || $password_len < 6) {
            return json(msg(-1, '', lang('密码长度应在6-20位之间')));
        }

        if ($password !== $repassword) {
            return json(msg(-1, '', lang('两次密码不一致')));
        }
        $info = Db::name('home_user')->where(['id' => $userid])->find();
        $oldpassword2 = md5($oldpassword . config('app.salt'));   // 密码
        if ($info['paypwd'] != $oldpassword2 && !password_verify($oldpassword, $info['paypwd'])) {
            return json(msg(-1, '', lang('旧密码错误')));
        }
        $data['paypwd'] = md5($password . config('app.salt'));   // 密码

        $ret = Db::name('home_user')->where(['id' => $userid])->update($data);
        if ($ret !== false) {
            //记录日志
            MemberLogModel::insertLoginLog($userid, MemberLogModel::CHANGE_PAY_PASSWORD);
            // 返回新的 token
            return json(msg(0, '', lang('修改成功')));
        } else {
            return json(msg(-1, '', lang('修改失败')));
        }

    }


    /**
     * 获取签到列表
     * @return Json
     */
    public function signList(): Json
    {
        $sign_id = input('sign_id/d', 0);
        $sign_list = SignModel::getSignList($sign_id, $this->userid);
        return json(msg(0, $sign_list, lang('success')));
    }

    /**
     * 签到
     * @return Json
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function sign(): Json
    {
        $sign_id = input('sign_id/d', 0);
        $user_id = $this->userid;
        // 获取用户vip信息
//        $user_level = MemberLevelModel::where('user_id', $user_id)->where(function($query) {
//            $query->where('to_level', '>',0)->whereOr(function($query) {
//                $query->where('level_end_time', '>', time());
//            });
//        })->find();
//        if (!$user_level) {
//            return json(msg(-1, '', lang('等级解锁已过期')));
//        }
        // 当天有买广告位才能签到
        $finance_product_count = (new FinanceProductOrderRepository())->whereCount([
            ['user_id', '=', $user_id],
            [
                'status', 'in',
                [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]
            ],
            ['buy_time', 'between', [Carbon::now()->startOfday()->timestamp, Carbon::now()->endOfday()->timestamp]]
        ]);
        if ($finance_product_count <= 0) {
            return json(msg(-1, '', lang('当天没有购买广告位')));
        }

        $ret = SignModel::addSignRecord($user_id, $sign_id);
        if ($ret) {
            return json(msg(0, '', lang('签到成功')));
        } else {
            return json(msg(-1, '', lang('签到失败')));
        }
    }

    /**
     * 获取连续推广奖励列表
     * @return Json
     */
    public function promotion_reward_list(): Json
    {
        $sign_id = input('sign_id/d', 0);
        $sign_list = (new PromotionRewardDayRepository)->getDayList($sign_id, $this->userid);
        $data = (new PromotionRewardDayTransform)->transformCollection($sign_list->toArray());
        return json(msg(0, $data, lang('success')));
    }

    /**
     * 领取连续推广奖励
     * @param int $day
     * @return Json
     * @throws LockException
     */
    public function receive_promotion_reward(int $day = 1): Json
    {
        return json(msg(-1, '', lang('该功能已关闭')));
        (new Locker('receive_promotion_reward_lock_' . $this->userid))->lock();
        $user_id = $this->userid;
        $res = (new PromotionRewardRecordRepository())->receiveReward($user_id, $day);
        if ($res) {
            return json(msg(0, '', lang('领取成功')));
        }
        return json(msg(-1, '', lang('奖励已领取')));
    }

    /**
     * 获取余额宝基本数据
     * @return Json
     */
    public function getBalance(): Json
    {
        $ret = HomeUserBalanceModel::getBalance($this->userid);
        return json(msg(0, $ret, lang('success')));
    }

    /**
     * 获取余额宝收益规则
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getBalanceRule(): Json
    {
        $ret = ArticleModel::where('type', ArticleModel::TYPE_BALANCE_RULE)->find();
        return json(msg(0, $ret, lang('success')));
    }


    /**
     * 获取余额宝收益明细
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getBalanceLog(): Json
    {
        $page = input('page/d', 1);
        $limit = input('limit/d', 10);
        $start_time = input('start_time', '');
        $end_time = input('end_time', '');
        $ret = HomeUserBalanceModel::getBalanceLog($this->userid, $page, $limit, $start_time, $end_time);
        return json(msg(0, $ret, lang('success')));
    }

    /**
     * 余额宝账户充值
     * @return Json
     */
    public function addBalance(): Json
    {
        $money = input('money/f', 0);
        $data = [
            'money' => $money
        ];
        $result = $this->validate($data, 'HomeUserBalanceValidate');
        if (true !== $result) {
            return json(msg(-1, '', lang($result)));
        }

        return HomeUserBalanceModel::addBalance($this->userid, $money);
    }

    /**
     * 余额宝提现
     * @return Json
     */
    public function subBalance(): Json
    {
        $money = input('money/f', 0);
        $data = [
            'money' => $money
        ];
        $result = $this->validate($data, 'HomeUserBalanceValidate');
        if (true !== $result) {
            return json(msg(-1, '', lang($result)));
        }
        return HomeUserBalanceModel::subBalance($this->userid, $money);
    }

    /**
     * 获取实名认证信息
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRealNameAuth(): Json
    {
        $user_id = $this->userid;
        $real_name_auth = MemberRealNameAuthModel::where(['user_id' => $user_id])
            ->find();
        if ($real_name_auth) {
            return json(msg(0, $real_name_auth, lang('success')));
        } else {
            return json(msg(-1, '', lang('您还没有提交实名认证信息')));
        }
    }

    /**
     * 提交实名认证
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     * @throws PDOException
     */
    public function realNameAuth(): Json
    {
        (new Locker('real_name_auth_lock'))->lock();
        // 先判断是否已经提交过实名认证
        $user_id = $this->userid;
        $real_name_auth = MemberRealNameAuthModel::where(['user_id' => $user_id])
            ->where('status', '<>', MemberRealNameAuthModel::STATUS_FAIL)
            ->find();
        if ($real_name_auth) {
            return json(msg(-1, '', lang('您已经提交过实名认证，请勿重复提交')));
        }
        $param = input('post.');
        $validate = new Validate([
            'real_name' => 'require|max:50',
            'id_card' => 'require|max:50',
            'id_card_front' => 'require|url',
            'id_card_back' => 'require|url',
            'hand_hold_photo' => 'require|url',
        ], [
            'real_name.require' => lang('请输入真实姓名'),
            'real_name.max' => lang('真实姓名不能超过50个字符'),
            'id_card.require' => lang('请输入身份证号'),
            'id_card.max' => lang('身份证号不能超过50个字符'),
            'id_card_front.require' => lang('请上传身份证正面照片'),
            'id_card_front.url' => lang('请上传身份证正面照片'),
            'id_card_back.require' => lang('请上传身份证反面照片'),
            'id_card_back.url' => lang('请上传身份证反面照片'),
            'hand_hold_photo.require' => lang('请上传手持身份证照片'),
            'hand_hold_photo.url' => lang('请上传手持身份证照片'),
        ]);

        if (!$validate->check($param)) {
            return json(msg(-1, '', $validate->getError()));
        }
        $real_name = $param['real_name'];
        $id_card = $param['id_card'];
        $id_card_front = $param['id_card_front'];
        $id_card_back = $param['id_card_back'];
        $hand_hold_photo = $param['hand_hold_photo'];
        // 是否自动审核
        $auto_real_name_auth = ConfigModel::getConfigValue('auto_real_name_auth');
        $data = [
            'user_id' => $user_id,
            'real_name' => $real_name,
            'id_card' => $id_card,
            'id_card_front' => $id_card_front,
            'id_card_back' => $id_card_back,
            'hand_hold_photo' => $hand_hold_photo,
            'status' => $auto_real_name_auth ? MemberRealNameAuthModel::STATUS_SUCCESS : 0,
        ];
        if ($auto_real_name_auth) {
            // 设置用户kyc_status = 1
            Db::name('home_user')->where(['id' => $user_id])->update(['kyc_status' => 1]);
        }
        $ret = MemberRealNameAuthModel::create($data);
        if ($ret) {
            return json(msg(0, '', lang('提交成功')));
        } else {
            return json(msg(-1, '', lang('提交失败')));
        }
    }


    /**
     * 获取转盘信息
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getTurntable(): Json
    {
        $turntable_model = new TurntableModel();
        $turntable = $turntable_model->getTurntable();
        $turntable_count = TurntableCountModel::getTurntableCount($this->userid, $turntable['id']);
        $data = [
            'turntable_count' => $turntable_count,
            'turntable' => $turntable,
        ];
        $type = $turntable_count['reg_num'] > 0 ? 1 : 2;
        $level_id = $turntable['prize'] == 1 ? -1 : $this->userInfo['level_id'];
        $turntable_prize = TurntablePrizeModel::getPrizeList($turntable['id'], $type, $level_id);
        foreach ($turntable_prize as $k => $v) {
            $v['angle'] = (($k + 1) * 45) . "deg";
        }
        $data['turntable_prize'] = $turntable_prize;
        return json(msg(0, $data, lang('获取成功')));
    }

    /**
     * 转盘抽奖
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function onTurntable(): Json
    {
        $turntable_model = new TurntableModel();
        $turntable_id = $this->request->param('id');
        $data = $turntable_model->startTurntable($this->userid, $turntable_id);
        $data['msg'] = lang($data['msg']);
        return json($data);
    }

    /**
     * 获取转盘记录
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getTurntableLog(): Json
    {
        $turntable_log_model = new TurntableLogModel();
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);
        $data = $turntable_log_model->where('user_id', $this->userid)->paginate(['page' => $page, 'list_rows' => $limit]);
        $list = $data->items();
        return json(msg(0, $list, lang('success')));
    }

    /**
     * 绑定银行卡
     * @return Json
     * @throws DataNotFoundException
     * @throws LockException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function bind_bank(): Json
    {
        (new Locker('bind_card_lock_' . $this->userid))->lock();
        $type = input('type/s', 'Bank');
        $bank_number = input('bank_number/s', '');
        $true_name = input('true_name/s', '');
        $bank_id = input('bank_id/d', '');
        $document_type = input('document_type/s', '');
        $document_id = input('document_id/s', '');
        if (!$true_name) {
            return json(msg(-1, '', lang('请输入正确的银行信息')));
        }
        if ($type == 'Bank') {
            if (!$bank_id) {
                return json(msg(-1, '', lang('请选择正确的银行')));
            } else {
                // 判断银行是否存在
                $bank = BankModel::where('id', $bank_id)->find();
                if (!$bank) {
                    return json(msg(-1, '', lang('请选择正确的银行')));
                }
                $bank_name = $bank['name'];
                $bank_code = $bank['code'];
            }
        } else {
            switch ($type) {
                case 'Alipay':
                    $bank_name = '支付宝';
                    $bank_code = 'Alipay';
                    break;
                default:
                    return json(msg(-1, '', lang('请选择正确的类型')));
            }
        }
        if (mb_strlen($true_name) > 60) {
            return json(msg(-1, '', lang('真实姓名不能超过60个字符')));
        }

        // 判断银行卡和姓名是否重复
//        $bank_info_exist = MemberModel::where('id', '<>', $this->userid)
//            ->where(function ($query) use ($true_name, $bank_number) {
//                $query->where('true_name', $true_name);
//            })->find();
//        if ($bank_info_exist) {
////            return json(msg(-1, '', lang('银行卡信息已被其他用户绑定，请联系客服')));
//            return json(msg(0, ['code' => -1, 'msg' => lang('根据平台的政策，禁止同一IP地址或同一人注册多个账户进行推广活动。')], lang('success')));
//        }
//        // 所有的clabe都是18位的，所有的银行卡号账号都是16位的  BNVA开头都是012，STP开头都是646
//        // Clabe代付选择MXNSTP银行编码，账号格式为18位数字组成，其他银行账号格式为16位数字组成
////        if ($bank->name === 'MXNSTP') {
////            // 验证银行卡号是否正确，18位数字
////            if (!preg_match('/^[0-9]{18}$/', $bank_number)) {
////                return json(msg(-1, '', lang('请输入正确的银行卡号')));
////            }
////        } else {
////            // 验证银行卡号是否正确，16位数字
////            if (!preg_match('/^[0-9]{16}$/', $bank_number)) {
////                return json(msg(-1, '', lang('请输入正确的银行卡号')));
////            }
////        }
////        if (strlen($bank_number) > 50 || strlen($bank_number) <= 10 ) {
////            return json(msg(-1, '', lang('请输入正确的银行卡号')));
////        }
//        // 更新用户信息
//        MemberModel::where('id', $this->userid)->update([
//            'bank_name' => $bank->name,
//            'bank_code' => $bank->bank_code,
//            'true_name' => $true_name,
//            'bank_number' => $bank_number,
//            'document_type' => $document_type,
//            'document_id' => $document_id,
//        ]);

        $HomeUserCardModel = new HomeUserCardModel();
        $HomeUserCardModel->bindCard([
            'user_id' => $this->userid,
            'type' => $type,
            'bank_name' => $bank_name,
            'bank_id' => $bank_id,
            'bank_code' => $bank_code,
            'true_name' => $true_name,
            'bank_number' => $bank_number
        ]);
        MemberLogModel::insertLoginLog($this->userid, MemberLogModel::BIND_CARD);
        return json(msg(0, '', lang('绑定成功')));
    }

    /**
     * 绑定银行卡列表
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function bind_bank_list(): Json
    {
        $type = input('type/s', 'Bank');
        $HomeUserCardModel = new HomeUserCardModel();
        if (empty($type)) {
            $list = $HomeUserCardModel->where('user_id', $this->userid)->select();
        } else {
            $list = $HomeUserCardModel->where('user_id', $this->userid)->where('type', $type)->select();
        }
        if (!empty($list)) {
            $list = $list->toArray();
        } else {
            $list = [];
        }
        return json(msg(0, $list, lang('success')));
    }


    /**
     * 转账给其他用户
     * @throws LockException
     */
    public function transfer_money(): Json
    {
        (new Locker('transfer_money_lock_' . $this->userid))->lock();
        $param = $this->request->param();
        $param['paypwd'] = $this->userInfo['paypwd'];
        $user_id = $param['user_id'] = $this->userid; // 用户ID
        $result = $this->validate($param, 'TransferMoneyValidate');
        if (true !== $result) {
            return json(msg(-1, '', lang($result)));
        }

//        $to_user = UserModel::getOneUser($param['user_id'], 'id', 'id,user_name'); //TODO 暂时恢复一下
        $to_user = UserModel::getOneUser($param['invite_code'], 'invite_code', 'id,user_name');
        $to_user_id = $to_user->id; // 用户ID
        $to_user_name = $to_user->user_name;

        $money = $param['money'];
        $money_log = new MoneyLogModel();
        try {
            $fiat_currency_unit = ConfigModel::getConfigValue('fiat_currency_unit');
            $res = $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_BROKERAGE, 'sub',
                '转账给用户', "转账给用户 {$to_user_name} ：{$money} {$fiat_currency_unit}", 22, '', '', '', true, $this->userInfo['is_xuni'], true);
            if ($res['code'] !== 1) {
                return json(msg(-1, '', lang($res['msg'])));
            }
            $money_log->changeMoney($to_user_id, $money, MoneyLogModel::TYPE_BROKERAGE, 'add',
                '收到其他用户转账', "收到用户 {$this->userInfo['user_name']} 的转账：{$money} {$fiat_currency_unit}", 23, '', $user_id, '', true, '', false);
            return json(msg(0, '', lang('转账成功')));
        } catch (\Exception $e) {
            return json(msg(-1, '', lang('转账失败')));
        }
    }
}
