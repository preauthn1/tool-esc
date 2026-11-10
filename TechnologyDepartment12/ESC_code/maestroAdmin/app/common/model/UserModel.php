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

namespace app\common\model;

use app\admin\model\ConfigModel;
use app\admin\model\LevelModel;
use app\admin\model\RechargeModel;
use app\admin\model\WithdrawModel;
use app\admin\transform\MemberTransform;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\InvestmentProductOrderRepository;
use app\common\repository\LevelRepository;
use app\common\repository\MoneyLogRepository;
use app\common\repository\TaskSessionRepository;
use app\common\repository\TaskSummaryRepository;
use app\common\repository\WithdrawalRepository;
use app\common\validate\HomeValidate;
use Carbon\Carbon;
use think\db\Where;
use think\facade\Cache;
use think\facade\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\facade\Log;
use think\Model;
use think\model\relation\HasMany;
use think\model\relation\HasOne;
use Throwable;
use think\model\concern\SoftDelete;
use util\Locker;

class UserModel extends Model
{
    use SoftDelete;

    protected $deleteTime = 'delete_time';
    // 确定链接表名
    protected $name = 'home_user';

    const NORMAL = 1;
    const DISABLE = 0;
    const FREEZE = 2;

    /**
     * 根据用户名检测用户数据
     * @param $userName
     */
    public function checkUser($username, $password = '', $phone_code = '')
    {
        $where[] = ['user_name', '=', $username];
        $where[] = ['phone_code', '=', $phone_code];
        if ($password !== '') {
            $password = md5($password . config('app.salt'));
            $where[] = ['password', '=', $password];
        }
        return $this->withTrashed()
            ->field('id, mobile, avatar, status, login_times, delete_time')
            ->where($where)
            ->find();
    }

    /**
     * 根据用户名检测用户数据
     * @param $userName
     */
    public function checkUserName($username, $password = '')
    {
        $where[] = ['user_name|mobile', '=', $username];
        if ($password !== '') {
            $password = md5($password . config('my.salt'));
            $where[] = ['password', '=', $password];
        }
        return $this->withTrashed()->field('id, user_name,mobile, avatar, status, login_times, delete_time')
            ->where($where)
            ->find();
    }

    /**
     * 更新会员状态
     * @param array $param
     */
    public function updateStatus($param = [], $uid = 0)
    {
        try {
            $this->where('id', $uid)->update($param);
            return msg(1, '', 'ok');
        } catch (\Exception $e) {
            return msg(-1, '', $e->getMessage());
        }
    }

    /**
     * 根据id获取用户信息
     * @param $id
     */
    public static function getOneUser($id, $field = 'id', $fields = '*')
    {
        $info = (new self())->where([$field => $id])->field($fields)->find();
        if (empty($info)) {
            return [];
        }
        return $info;
    }

    // 手机验证码
    public function checkVerify($mobile, $code)
    {
        $redis = Cache::store('redis');
        $mobile = str_replace('+', '', $mobile);
        $cache_code = $redis->get($mobile);
        Log::write('短信校验：手机号=' . $mobile . '，提交的验证码=' . $code . '，redis存储的验证码=' . $cache_code, 'debug', [
            'mobile' => $mobile,
            'code' => $code,
            'cache_code' => $cache_code
        ]);
        if (empty($cache_code)) {
            return msg(-1, '', lang('验证码已失效'));
        }
        if ($cache_code != $code) {
            return msg(-1, '', lang('验证码有误'));
        }
        $redis->delete($mobile);
        return msg(200, '', lang('验证码成功'));
    }

    /**
     * 验证邮箱验证码
     * @param $email
     * @param $code
     * @return array
     */
    public function checkVerifyEmail($email, $code)
    {
        $redis = Cache::store('redis');
        $key = 'email_' . md5($email);
        $cache_code = $redis->get($key);
        Log::write('邮箱校验：邮箱=' . $email . '，提交的验证码=' . $code . '，redis存储的验证码=' . $cache_code, 'debug', [
            'email' => $email,
            'code' => $code,
            'cache_code' => $cache_code
        ]);
        if (empty($cache_code)) {
            return msg(-1, '', lang('验证码已失效'));
        }
        if ($cache_code != $code) {
            return msg(-1, '', lang('验证码有误'));
        }
        $redis->delete($key);
        return msg(200, '', lang('验证码成功'));
    }

    /**
     * 登录会员信息
     * @param $account
     * @param $password
     * @param string $phone_code 手机区号
     * @return array
     */
    public function dologin($account, $password, $phone_code = '')
    {
        try {
            $data = [
                'account' => $account,
                'password' => $password
            ];
            $validate = new HomeValidate();
            if (!$validate->check($data)) {
                return msg(-1, '', $validate->getError());
            }
            // 判断用户状态
            $user_status = UserModel::where('user_name', $account)->value('status');
            if ($user_status == UserModel::FREEZE) {
                return msg(-1, '', lang('该账号已被冻结'));
            }
            $hasUser = $this->checkUser($account, $password, $phone_code); // 检查用户信息

            if (!$hasUser) {
                return msg(-1, '', lang('账号或密码错误'));
            }
            // 验证账号状态
            if (2 == $hasUser['status']) {
                return msg(-5, '', lang('该账号被禁用'));
            }
            if (isset($hasUser['delete_time']) && $hasUser['delete_time']) {
                return msg(-5, '', lang('该账号被禁用'));
            }
            // 更新用户状态
            $time = time();
            $param = [
//                'login_times' => $hasUser['login_times'] + 1,
                'last_login_ip' => getip(),
                'login_times' => time(),
                'last_login_time' => time(),
                'login_key' => md5($time . $password)
            ];
            // 更新用户登录信息
            $res = $this->updateStatus($param, $hasUser['id']);
            if (1 != $res['code']) {
                // 判断用户更新状态
                return msg(-6, '', $res['msg']);
            }
            //记录登录日志
            MemberLogModel::insertLoginLog($hasUser['id'], MemberLogModel::LOGIN);
            // 用户信息
            $userInfo = $this->getOneUser($hasUser['id']);
            return msg(200, $userInfo->toArray(), lang('登录成功'));
        } catch (\Exception $e) {
            return msg(-1, '', $e->getMessage());
        }
    }

    /**
     * 注册会员
     * @param $mobile
     * @param $password
     * @param $code
     * @param $phone_code 手机区号
     * @param string $user_name
     * @param string $paypwd
     * @param string $invite
     * @param string $pix
     * @param string $platform
     * @param string $click_id
     * @param int $demo
     * @return array
     */
    public function reg($mobile, $password, $code, $phone_code = '', $paypwd = '', $user_name = '', $invite = '', $pix = '', $platform = '', $click_id = '', $demo = 0)
    {
        $config = new ConfigModel();
        $configInfo = $config->getConfigInfo();
        // 验证手机验证码
        if ($configInfo['is_verify_phone_code'] == 1 && !empty($mobile)) {
            if ($phone_code == '+86') {
                $verify = $this->checkVerify($mobile, $code);
                if ($verify['code'] != 200) {
                    return $verify;
                }
            } else {
                $verify = $this->checkVerify($phone_code . $mobile, $code);
                if ($verify['code'] != 200) {
                    return $verify;
                }
            }

        }
        // 验证邮箱验证码
        if ($configInfo['is_verify_email_code'] == 1 && empty($mobile)) {
            $verify = $this->checkVerifyEmail($user_name, $code);
            if ($verify['code'] != 200) {
                return $verify;
            }
        }

        if (!empty($mobile)) {
            $hasUser = $this->checkUser($mobile, '', $phone_code); // 检查用户信息
            // 验证用户是否存在
            if ($hasUser) {
                return msg(-1, '', lang('手机号已存在'));
            }
        }
        $hasNameUser = $this->checkUserName($user_name); // 检查用户信息
        // 验证用户是否存在
        if ($hasNameUser) {
            return msg(-1, '', lang('账号已存在'));
        }
        $ip = getip();
        $redis = Cache::store("redis")->handler();
        $ip_count = $redis->HGET("registered", $ip);
        $reg_ip_limit = isset($configInfo['reg_ip_limit']) ? intval($configInfo['reg_ip_limit']) : 0;
        //同IP注册数量限制
        if ($reg_ip_limit > 0 && intval($ip_count) >= $reg_ip_limit) {
            return msg(-1, '', lang('同地址注册账号数量超过限制,无法注册'));
        }
        try {
            if ($demo == 1) {
                $level_id = 1;
                $user_money = 2000;
            } else {
                $level_id = 0;
                $user_money = 0;
            }
            $avatar = mt_rand(1, 100) . '.png';
            $password = md5($password . config('app.salt'));
            $paypwd = $paypwd ? md5($paypwd . config('app.salt')) : '123456';
            $create_time = time();

            $data = [
                'avatar' => $avatar,
                'mobile' => $mobile,
                'phone_code' => $phone_code,
                'password' => $password,
                'paypwd' => $paypwd,
                'user_name' => $user_name,
                'level_id' => $level_id,
                'user_money' => $user_money,
                'tyj_money' => isset($configInfo['tyj_money']) ? floatval($configInfo['tyj_money']) : 0,
                'frozen_money' => 0,
                'create_time' => $create_time,
                'invite_code' => generateInviteCode(),
                'ip' => $ip,
                'pix' => $pix,
                'platform' => $platform,
                'click_id' => $click_id,
                'is_demo' => $demo
            ];
            if ($invite) { // 存在邀请人
                $family = getFamily($invite, 'invite_code');
                $data['leader1'] = $family['leader1'];
                $data['leader2'] = $family['leader2'];
                $data['leader3'] = $family['leader3'];
                $data['leader4'] = $family['leader4'];
                $data['leader5'] = $family['leader5'];
                $data['leader6'] = $family['leader6'];
                $data['leader7'] = $family['leader7'];

            }
            $user_id = Db::name('home_user')->insertGetId($data);
//            checkLevel($ret); // 会员升级
            updateTreeById2($user_id);
//            triggerActivityTask([
//                'user_id' => $user_id,
//                'type' => 2,
//            ]);
            if (isset($data['leader1']) && $data['leader1'] > 0) {
                //下级注册触发规则
//                triggerActivityTask([
//                    'user_id' => $data['leader1'],
//                    'from_user_id' => $user_id,
//                    'trigger_type' => 4,
//                    'type' => 6,
//                ]);
            }
            $redis->HINCRBY("registered", $ip, 1);
            //记录日志
            MemberLogModel::insertLoginLog($user_id, MemberLogModel::REGISTER);
            // 用户信息
            $userInfo = $this->getOneUser($user_id);
            return msg(200, $userInfo, lang('注册成功'));
        } catch (\Exception $e) {
            return msg(-1, '', $e->getMessage());
        }
    }

    /**
     * 记录用户登录日志
     * @param type $userid
     */
    public function insertLoginLog($userid)
    {
        $login_log = [
            'user_id' => $userid,
            'ip' => getip(),
            'create_time' => time(),
            'update_time' => time(),
        ];
        Db::name('home_user_login')->insert($login_log);
    }

    /**
     * 获取用户指定字段值
     * @param $user_id
     * @param $field
     * @return int|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getFieldData($user_id, $field)
    {
        $user = self::where('id', $user_id)->field($field)->find();
        if (empty($user)) {
            return '';
        }
        return $user[$field];
    }

    /**
     * 获取用户等级ID
     * @param $user_id
     * @return int|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getLevelId($user_id)
    {
        $user = self::where('id', $user_id)->field('level_id')->find();
        if (empty($user)) {
            return 0;
        }
        return $user['level_id'];
    }


    /**
     * 用户完成任务的条件判断
     * @param $user_id
     * @param string $level_id 当前任务等级
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function endTaskCondition($user_id, $level_id = '')
    {
        $user_data = self::where('id', $user_id)->field('level_id,is_upgrade,upgrade_status,do_task_status,task_count')->find();
        if (empty($user_data)) {
            return [
                'code' => 101,
                'msg' => lang('用户信息有误')
            ];
        }
//        if ($user_data['is_upgrade'] == 1 && $user_data['upgrade_status'] == 0) {
//            return [
//                'code' => 102,
//                'msg' => lang('请先升级')
//            ];
//        }
        $is_task_count = ConfigModel::getConfigValue('is_task_count');
        if ($is_task_count == 1) {
            if ($user_data['task_count'] <= 0) {
                return [
                    'code' => 102,
                    'msg' => lang('请先升级或者邀请新用户充值')
                ];
            }
        }
        if ($user_data['is_upgrade'] == 1) {
            if (!empty($level_id) && $user_data['level_id'] <= $level_id) {
                return [
                    'code' => 103,
                    'msg' => lang('等级不符合要求')
                ];
            }
        }
        if ($user_data['do_task_status'] == 0) {
            return [
                'code' => 104,
                'msg' => lang('当前访问人数过多，请稍等...')
            ];
        }
        return [
            'code' => 1,
            'msg' => lang('可以完成任务')
        ];
    }

    /**
     * 更新升级标识状态
     * @param $user_id
     * @param int $status 0未升级 1已升级
     * @return false
     */
    public static function upgradeStatus($user_id, $status = 1)
    {
        $user_data = new UserModel();
        $user_data->where('id', $user_id)->update(['upgrade_status' => $status]);
        return false;
    }

    /**
     * 获取团队满足充值的人数
     * @param $user_id
     * @param int $money
     * @return int|string
     * @throws \think\Exception
     */
    public static function getTeamEffRecharge($user_id, $money = 0)
    {
        $user_model = new UserModel();
        $team_ids1 = $user_model->where('leader1', $user_id)->column('id');
        if (empty($team_ids1)) {
            $team_ids1 = [];
        }
        $team_ids2 = $user_model->where('leader2', $user_id)->column('id');
        if (empty($team_ids2)) {
            $team_ids2 = [];
        }
        $team_ids3 = $user_model->where('leader3', $user_id)->column('id');
        if (empty($team_ids3)) {
            $team_ids3 = [];
        }
        $team_ids = array_merge($team_ids1, $team_ids2, $team_ids3);
        return $user_model->whereIn('id', $team_ids)->where('recharge_money', '>=', $money)->count();
    }

    /**
     * VIP升级
     * @param int $user_id
     * @param int $level_id 指定等级  为空 默认下一级
     * @param int $mode 1根据基础余额直接升级不扣钱，2.扣除基础余额，并返还之前等级解锁金额到佣金。
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @throws PDOException|Throwable
     */
    public static function vipUpgradeStatus(int $user_id, int $level_id = 0, int $mode = 1): array
    {
        $user_data = (new UserModel)->where('id', $user_id)->find();
        $level_data = (new LevelRepository())->getLevelByLevelID($level_id);
        if (empty($level_data)) {
            return [
                'code' => 101,
                'msg' => lang('广告库存不存在')
            ];
        }

        if ($user_data['do_task_status'] == 0) {
            return [
                'code' => 102,
                'msg' => lang('当前访问人数过多，请稍等...')
            ];
        }
        $startTime = microtime(true);
        list($orders, $task_session) = (new FinanceProductOrderRepository)->getLevelSessionOrders($level_id, $user_id);
        trace_log("FinanceProductOrderRepository->getLevelSessionOrders: " . microtime(true) - $startTime);
        // 获取当前的时段
        if (!$task_session) {  // 判断时段
            return [
                'code' => 102,
                'msg' => $level_data['unlock_time_disabled_tips'] ?: lang('目前无法购买')
            ];
        }
        // 获取用户待匹配的广告位
        $pending_order_level = (new FinanceProductOrderModel())->order(['id' => 'desc'])
            ->where([
                'user_id' => $user_id,
                'status' => FinanceProductOrderRepository::STATUS_PENDING
            ])->find();
        // 判断用户等级和广告位等级是否匹配
        if ($pending_order_level && $pending_order_level['session_no'] && $pending_order_level['level_id'] != $level_id) {
            return [
                'code' => 12,
                'msg' => lang("无法进入当前广告位")
            ];
        }
        if ($orders->isEmpty()) {
            // 判断库存
            list($current_inventory, $total_inventory, $can_over_match, $stock_range,$user_money_interval) = FinanceProductOrderRepository::isUserMoneyInRangeAndStockEnough($level_data,
                $user_data);
            if (!$can_over_match && !$current_inventory && !$total_inventory) {
                return [
                    'code' => 102,
                    'msg' => lang('广告库存已售空')
                ];
            }
        }

        $member_level_model = new MemberLevelModel();
        // 体验区升级
//        if ($level_data['type'] == LevelRepository::TYPE_EXPERIENCE) // 体验区可以24小时解锁
//        {
//            // 判断之前是否有过升级记录
//            $exist = $member_level_model->where('user_id', $user_id)->where('to_level', $level_id)->find();
//            if ($exist) {
//                return [
//                    'code' => 13,
//                    'msg' => lang("无需重复升级")
//                ];
//            }
//            $level_data['level_time'] = $level_data['level_time'] ?: 365;
//            if ($user_data['user_money'] < $level_data['price']) {
//                return [
//                    'code' => 102,
//                    'msg' => lang("余额不足，请充值")
//                ];
//            }
//            return $member_level_model->addUserLevelLog($user_id, $user_data['level_id'], $level_data['level_id'], $level_data['price'], $level_data['level_time'], MemberLevelModel::LEVEL_TYPE_NATURAL_DAYS, $level_data['type']);
//        }
        // 获取当前的时段
        $task_session = (new TaskSessionRepository())->getCurrentTaskSessionTime($level_id);
        $time_interval = $task_session['time_interval'];
        if (empty($time_interval)) {
            return [
                'code' => 102,
                'msg' => lang('目前无法购买')
            ];
        }
        //获取有效时间段有效的升级等级 如果不在时间段内 等级无效
        $now_level_type = $member_level_model->where([
            ['user_id', '=', $user_id],
            [
                'create_time', 'between', [
                Carbon::parse($time_interval->start_time)->timestamp,
                Carbon::parse($time_interval->end_time)->timestamp
            ]
            ]
        ])->order('id desc')->value('type');
        if (!empty($now_level_type)) {
            //用户纯在有效升级等级类型 需要判断等级之间的互斥关系
            if ($now_level_type == $level_data['type']) {
                //当前等级依然有效无需升级
                return [
                    'code' => 1,
                    'msg' => lang("升级成功")
                ];
            } else {
                return [
                    'code' => 12,
                    'msg' => lang("无法进入当前广告位")
                ];
            }
        }
        // 判断邀请任务
        if ($level_data['invite_num'] > 0) {
            $invite_num_count = self::getTeamEffRecharge($user_id, $level_data['invite_num_money']);
            if ($invite_num_count < $level_data['invite_num']) {
                return [
                    'code' => 11,
                    'msg' => lang("邀请任务未完成")
                ];
            }
        }

        // 如果是推广代理区，需要判断活跃人数
        if ($level_data['type'] == LevelRepository::TYPE_PROMOTION && $level_data['active_num'] > 0) {
            $yesterday = Carbon::yesterday()->format('Ymd');
            $active_num_count = TaskSummaryRepository::getUserActiveByDate($user_id, 3, $yesterday);
            if ($active_num_count < $level_data['active_num']) {
                return [
                    'code' => 11,
                    'msg' => lang("昨日活跃人数未达标")
                ];
            }
        }


        if ($mode == 1) {
            //根据基础余额直接升级不扣钱
            if ($user_data['user_money'] >= $level_data['price']) {
                //可以升级
                $member_level_model = new MemberLevelModel();
                $ret = $member_level_model->addUserLevelLog($user_id, $user_data['level_id'], $level_data['level_id'], $level_data['price'], $level_data['level_time'], MemberLevelModel::LEVEL_TYPE_NATURAL_DAYS, $level_data['type']);
                if ($ret['code'] == 1) {
                    MemberLogModel::insertLoginLog($user_id, MemberLogModel::UPGRADE_LEVEL);
                    // 清除缓存
                    Cache::store('redis')->delete('user_levels_' . $user_id);
                    Cache::store('redis')->delete('user_level_' . $level_id . '_' . $user_id);
                    return [
                        'code' => 1,
                        'msg' => lang("升级成功")
                    ];
                } else {
                    return $ret;
                }
            } else {
                return [
                    'code' => 12,
                    'data' => number_format($level_data['price'] - $user_data['user_money'], 2, '.', ','),
                    'msg' => lang("当前账户余额未满足该交易区最低竞价区间要求")
                ];
            }
        } else {
            //扣除基础余额，并返还之前等级解锁金额到佣金。
            if ($user_data['user_money'] >= $level_data['price']) {
                $level_old = (new LevelRepository())->getLevelByLevelID($level_id);
                //上次升级消耗的基础余额
                $level_price = $level_old['price'];
                //本次升级需要消耗的基础余额
                $up_level_price = $level_data['price'];
                $money_model = new MoneyLogModel();
//                $money_model->startTrans();
                //扣除基础余额
                $data_temp = $money_model->changeMoney($user_id, $up_level_price, MoneyLogModel::TYPE_USER, 'sub', '升级会员', '升级会员扣除余额:-' . $up_level_price, 17);
                if ($data_temp['code'] == 1) {
//                    if ($data_temp1['code'] == 1 || $data_temp1['code'] == 9) {
                    //可以升级
                    $member_level_model = new MemberLevelModel();
                    $ret = $member_level_model->addUserLevelLog($user_id, $user_data['level_id'], $level_data['level_id'], $level_data['price'], $level_data['level_time']);
                    if ($ret['code'] == 1) {
//                        $money_model->commit();
                        //返还上次升级基础余额
                        $money_model->changeMoney($user_id, $level_price, MoneyLogModel::TYPE_BROKERAGE, 'add', '返还升级费用到佣金', '返还升级费用到佣金:' . $level_price, 7);
                        MemberLogModel::insertLoginLog($user_id, MemberLogModel::UPGRADE_LEVEL);
                        // 清除缓存
                        Cache::store('redis')->delete('user_levels_' . $user_id);
                        Cache::store('redis')->delete('user_level_' . $level_id . '_' . $user_id);
                        return [
                            'code' => 1,
                            'msg' => lang("升级成功")
                        ];

                    }
                    $money_model->changeMoney($user_id, $up_level_price, MoneyLogModel::TYPE_USER, 'add', '升级会员失败', '升级会员扣除余额:-' . $up_level_price, 18);
                    return $ret;
                }
//                $money_model->rollback();
                return $data_temp;
            } else {
                return [
                    'code' => 12,
                    'data' => number_format($level_data['price'] - $user_data['user_money'], 2, '.', ','),
                    'msg' => lang("当前账户余额未满足该交易区最低竞价区间要求")
                ];
            }

        }
    }

    //升级成功的回调都在这里处理
    private static function _vipUpgradeSuccess($up_data)
    {
        //VIP升级回调处理
//        $up_data['user_id'] = 111;
//        $up_data['level_id'] = 1;//升级后等级
//        $up_data['last_level_id'] = 0;//升级前等级
//        $up_data['price'] = 100;//升级需要的金额
//        $up_data['last_price'] = 50;//升级前需要的金额
        //升级成功获得一次任务机会
        self::incTaskCount($up_data['user_id']);
    }

    //升级成功增加任务次数
    public static function incTaskCount($user_id)
    {
        $is_task_count = ConfigModel::getConfigValue('is_task_count');
        if ($is_task_count == 1) {
            UserModel::where('id', $user_id)->setInc('task_count', 1);
        }
    }

    //做任务次数减1
    public static function decTaskCount($user_id)
    {
        $is_task_count = ConfigModel::getConfigValue('is_task_count');
        if ($is_task_count == 1) {
            $task_count = UserModel::where('id', $user_id)->value('task_count');
            if ($task_count > 0) {
                UserModel::where('id', $user_id)->setDec('task_count', 1);
            }
        }
    }

    //充成功后父级任务次数
    public static function addRechargeTaskCount($user_id)
    {
        // 获取充值金额
        $money = RechargeModel::getRecharge($user_id);
        //有效充值金额
        $validity_recharge = ConfigModel::getConfigValue('recharge_num_valid_user');
        if ($money >= $validity_recharge) {
            $user_model = new UserModel();
            $puser_id = $user_model->where('id', $user_id)->value('leader1');
            self::incTaskCount($puser_id);
        }
    }

    /**
     * 获取指定级别 团队用户id
     * @param $user_id
     * @param $level //级别
     * @return array
     */
    public static function getTeamIds($user_id, $level = 1)
    {
        $user_model = new UserModel();
        $team_ids = $user_model->where('leader' . $level, $user_id)->column('id');
        if (empty($team_ids)) {
            $team_ids = [];
        }
        return $team_ids;
    }

    /**
     * 有效推荐人数
     * @param $user_id
     * @param $level //级别
     * @return array
     */
    public static function getTeamIdsValidity($user_id, $level = 1)
    {
        $user_model = new UserModel();
        $validity_recharge = ConfigModel::getConfigValue('recharge_num_valid_user');
        $team_ids = $user_model->where('leader' . $level, $user_id)->where('recharge_money', '>=', $validity_recharge)->column('id');
        if (empty($team_ids)) {
            $team_ids = [];
        }
        return $team_ids;
    }

    /**
     * 获取用户级别信息
     * @param $user_id
     */
    public static function getTeamLevelInfo($user_id)
    {
        $level_id = UserModel::getLevelId($user_id);
        $level_data = LevelModel::where('level_id', $level_id)->find();
        $task_rebate = str_replace('[', '', $level_data['recharge_rebate']);
        $task_rebate = str_replace(']', '', $task_rebate);
        $task_rebate_arr = explode(',', $task_rebate);
        $level_list = [];
        for ($i = 1; $i <= 3; $i++) {
            //团队总人数
            $user_ids = self::getTeamIds($user_id, $i);
            $all_num = count($user_ids);
            //有有效人数
            $valid_ids = self::getTeamIdsValidity($user_id, $i);
            $valid_num = count($valid_ids);
            //佣金比例
            $brokerage_ratio = $task_rebate_arr[$i - 1];
            //总佣金
            $brokerage_all = $user_ids ? self::getTeamRecharge($user_ids) : 0;
            $level_list[$i] = [
                'all_num' => $all_num,
                'valid_num' => $valid_num,
                'brokerage_ratio' => $brokerage_ratio,
                'brokerage_all' => number_format($brokerage_all * ($brokerage_ratio / 100), 2, '.', ','),
            ];
        }
        return $level_list;
    }

    /**
     * 获取指定级别 全部团队用户id
     * @param $user_id
     * @param $level //级别
     * @param $new //是否是新人
     * @param $time_arr //时间区间
     * @return array
     */
    public static function getTeamAllIds($user_id, $level = 1, $new = false, $time_arr = [])
    {
        $user_model = new UserModel();
        $team_ids1 = [];
        $map = "";
        if (!empty($time_arr)) {
            $map = "create_time between " . $time_arr['start_time'] . " and " . $time_arr['end_time'];
        }
        for ($i = 1; $i <= $level; $i++) {
            if ($new) {
                $team_ids = $user_model->where('leader' . $i, $user_id)->where($map)->where('create_time', '>', strtotime(date("Y-m-d")))->column('id');
            } else {
                $team_ids = $user_model->where('leader' . $i, $user_id)->where($map)->column('id');
            }
            if (empty($team_ids)) {
                $team_ids = [];
            }
            $team_ids1 = array_merge($team_ids1, $team_ids);
        }
        return $team_ids1;
    }

    public static function getTopLeader($user_id, $field = ['*']): array
    {
        $user = Cache::remember('need_find_top_leader_user_' . $user_id, function () use ($user_id, $field) {
            return self::where('id', $user_id)->field($field)->find();
        }, 60);

        $top_leader_id = 0;
        for ($i = 7; $i >= 1; $i--) {
            if (isset($user['leader' . $i])) {
                if ($user['leader' . $i]) {
                    $top_leader_id = $user['leader' . $i];
                    break;
                }
            }
        }
        $cache_key = 'top_leader_' . $user_id;
        if ($top_leader_id) {
            if ($i == 7) { // 判断是否还有上级
                $top_leader = Cache::remember($cache_key, function () use ($top_leader_id, $field) {
                    return self::getTopLeader($top_leader_id, $field);
                }, 60);
            } else {
                $top_leader = Cache::remember($cache_key, function () use ($top_leader_id, $field) {
                    return self::where('id', $top_leader_id)->field($field)->find();
                }, 60);
            }
            if ($top_leader) {
                return (new MemberTransform())->transform($top_leader);
            }
        } else {
            if ($user) {
                return (new MemberTransform())->transform($user);
            }
        }
        return [];
    }

    /**
     * 获取指定级别的上级
     * @param string $tree_id
     * @param int $level
     * @param array $field
     * @return array
     */
    public static function getLeaderByTree(string $tree_id, int $level = 1, array $field = ['*']): array
    {
        $tree_ids = explode('/', $tree_id);
        if (!empty($tree_ids)) {
            // 删除空值
            $tree_ids = array_filter($tree_ids);
        }
        if (count($tree_ids) >= $level) {
            $cache_key = 'user_leader_' . $tree_ids[$level];
            $leader_id = $tree_ids[$level];
            return Cache::remember($cache_key, function () use ($leader_id, $field) {
                $leader_info = self::where('id', $leader_id)->field($field)->find();
                if ($leader_info) {
                    return (new MemberTransform())->transform($leader_info);
                }
                return [];
            }, 60);

        }
        return [];
    }

    /**
     * 获取团队充值数据
     * @param $user_ids
     * @param bool $new //首次充值 人数
     * @param array $time_arr //时间区间
     * @return float
     */
    public static function getTeamRecharge($user_ids, $new = false, $time_arr = [])
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        $model = new RechargeModel();
        $map = "";
        if (!empty($time_arr)) {
            $map = "create_time between " . $time_arr['start_time'] . " and " . $time_arr['end_time'];
        }
        if ($new) {
            return $model->where('user_id in (' . $user_ids . ')')
                ->where($map)
                ->where('status', 2)
                ->where('is_first', 1)
                ->where('create_time', '>', strtotime(date("Y-m-d")))
                ->count();
        } else {
            return $model->where('user_id in (' . $user_ids . ')')->where($map)->where('status', 2)->sum('money');
        }
    }

    /**
     * 获取团队充值人数
     * @param $user_ids
     * @return int
     */
    public static function getTeamRechargeCount($user_ids, $new = false, $time_arr = [])
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        $model = new RechargeModel();
        $map = "";
        if (!empty($time_arr)) {
            $map = "create_time between " . $time_arr['start_time'] . " and " . $time_arr['end_time'];
        }
        if ($new) {
            return $model->where('user_id in (' . $user_ids . ')')
                ->where($map)
                ->where('status', 2)
                ->where('create_time', '>', strtotime(date("Y-m-d")))
                ->group('user_id')
                ->count();
        } else {
            return $model->where('user_id in (' . $user_ids . ')')
                ->where($map)
                ->where('status', 2)
                ->group('user_id')->count();
        }
    }

    /**
     * 获取团队提现数据
     * @param $user_ids
     * @param bool $new //首次提现
     * @param array $time_arr //时间区间
     * @return float
     */
    public static function getTeamWithdrawals($user_ids, $new = false, $time_arr = [])
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        $model = new WithdrawModel();
        $map = "";
        if (!empty($time_arr)) {
            $map = "create_time between " . $time_arr['start_time'] . " and " . $time_arr['end_time'];
        }
        if ($new) {
            return $model->where('user_id in (' . $user_ids . ')')->where($map)->where('status', 4)->where('create_time', '>', strtotime(date("Y-m-d")))->count();
        } else {
            return $model->where('user_id in (' . $user_ids . ')')->where($map)->where('status', 4)->sum('withdraw_money');
        }
    }

    /**
     * 获取团队提现数据
     * @param $user_ids
     * @return int
     */
    public static function getTeamWithdrawalsCount($user_ids)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        $model = new WithdrawModel();
        return $model->where('user_id in (' . $user_ids . ')')->where('status', 4)->group('user_id')->count();
    }

    /**
     * 获取团队提现数据
     * @param $user_ids
     * @param bool $new //首次提现
     * @param array $time_arr //时间区间
     * @return float
     */
    public static function getTeamWithdrawalsPending($user_ids, $new = false, $time_arr = [])
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        $model = new WithdrawModel();
        $map = "";
        if (!empty($time_arr)) {
            $map = "create_time between " . $time_arr['start_time'] . " and " . $time_arr['end_time'];
        }
        if ($new) {
            return $model->whereIn('user_id', $user_ids)->where($map)->where('status', WithdrawalRepository::STATUS_PENDING)->where('create_time', '>', strtotime(date("Y-m-d")))->count();
        } else {
            return $model->whereIn('user_id', $user_ids)->where($map)->where('status', WithdrawalRepository::STATUS_PENDING)->sum('withdraw_money');
        }
    }

    /**
     * 获取团队提现数据
     * @param $user_ids
     * @return int
     */
    public static function getTeamWithdrawalsPendingCount($user_ids)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        $model = new WithdrawModel();
        return $model->where('user_id in (' . $user_ids . ')')->where('status', WithdrawalRepository::STATUS_PENDING)->group('user_id')->count();
    }

    /**
     * 获取团队提现数据
     * @param $user_ids
     * @param bool $new //首次提现
     * @param array $time_arr //时间区间
     * @return float
     */
    public static function getTeamWithdrawalsWait($user_ids, $new = false, $time_arr = [])
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        $model = new WithdrawModel();
        $map = "";
        if (!empty($time_arr)) {
            $map = "create_time between " . $time_arr['start_time'] . " and " . $time_arr['end_time'];
        }
        if ($new) {
            return $model->where('user_id in (' . $user_ids . ')')->where($map)->where('status', WithdrawalRepository::STATUS_WAIT)->where('create_time', '>', strtotime(date("Y-m-d")))->count();
        } else {
            return $model->where('user_id in (' . $user_ids . ')')->where($map)->where('status', WithdrawalRepository::STATUS_WAIT)->sum('withdraw_money');
        }
    }

    /**
     * 获取团队提现数据
     * @param $user_ids
     * @return int
     */
    public static function getTeamWithdrawalsWaitCount($user_ids)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        $model = new WithdrawModel();
        return $model->where('user_id in (' . $user_ids . ')')->where('status', WithdrawalRepository::STATUS_WAIT)->group('user_id')->count();
    }

    /**
     * 获取推广用户信息列表
     * @param $user_ids
     * @return bool|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getTeamUserInfo($user_ids)
    {
        $user_model = new UserModel();
        $user_list = $user_model->whereIn('id', $user_ids)
            ->field('id,user_name,mobile,recharge_money,create_time,avatar')
            ->select();
        foreach ($user_list as &$value) {
            $value['recharge_money'] = RechargeModel::getRecharge($value['id']);
            $value['recharge_money'] = number_format($value['recharge_money'], 2, '.', ',');
            $earnings = self::getEarnings($value['id']);
            $value['recharge_rebate'] = number_format($earnings['recharge_rebate'], 2, '.', ',');
            $value['task_rebate'] = number_format($earnings['task_rebate'], 2, '.', ',');
            $user_name = $value['user_name'] ? $value['user_name'] : $value['mobile'];
            $value['user_name'] = hideUserName($user_name);
            $value['avatar'] = toMedia($value['avatar'], '/static/admin/images/heads/', 1);
            $value['one_count'] = count(UserModel::getTeamAllIds($value['id'], 1));
            $value['create_time_str'] = date("Y/m/d H:i:s", strtotime($value['create_time']));
        }
        return $user_list;
    }

    // 余额总数
    public static function getTeamUserMoney($user_ids)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        $user_model = new UserModel();
        return $user_model->where('id in (' . $user_ids . ')')->sum('user_money');
    }

    // 注册有做任务（当天）
    public static function getTeamTodayTaskUser($user_ids)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        // 今日做任务的用户
        return FinanceProductOrderModel::where('user_id in (' . $user_ids . ')')->whereTime('create_time', "today")->group('user_id')->count();
    }

    // 注册有做任务
    public static function getTeamTask($user_ids)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return FinanceProductOrderModel::where('user_id in (' . $user_ids . ')')->group('user_id')->count();
    }

    // 团队任务佣金(当天)
    public static function getTeamTodayTaskRebate($user_ids): float
    {
        if (!$user_ids) {
            return 0;
        }
        $money_repository = new MoneyLogRepository();
        return $money_repository->getTodayMoney($user_ids, [24, 25]);
    }

    // 团队任务佣金
    public static function getTeamTaskRebate($user_ids): float
    {
        if (!$user_ids) {
            return 0;
        }
        $money_repository = new MoneyLogRepository();
        // 24-连续推广奖励 25-领取佣金池佣金
        return $money_repository->getTotalMoney($user_ids, [24, 25]);
    }

    // 首充人数
    public static function getTeamFirstRecharge($user_ids, $new = false)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        if ($new) {
            return RechargeModel::where('user_id in (' . $user_ids . ')')
                ->where('is_first', 1)
                ->where('create_time', '>', strtotime(date("Y-m-d")))
                ->count();
        } else {
            return RechargeModel::where('user_id in (' . $user_ids . ')')->where('is_first', 1)->count();
        }
    }

    // 首充人数
    public static function getTeamFirstRechargeMoney($user_ids, $new = false)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        if ($new) {
            return RechargeModel::where('user_id in (' . $user_ids . ')')
                ->where('is_first', 1)
                ->where('create_time', '>', strtotime(date("Y-m-d")))
                ->sum('money');
        } else {
            return RechargeModel::where('user_id in (' . $user_ids . ')')
                ->where('is_first', 1)
                ->sum('money');
        }
    }

    // 一次复充人数
    public static function getTeamRepeatRecharge($user_ids)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return RechargeModel::where('user_id in (' . $user_ids . ')')->where('is_first', 0)->group('user_id')->having('count(user_id)=1')->count();
    }

    // 多次复充人数
    public static function getTeamMultiRepeatRecharge($user_ids)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        return RechargeModel::where('user_id in (' . $user_ids . ')')->where('is_first', 0)->group('user_id')->having('count(user_id)>1')->count();
    }

    /**
     * 获取用户收益情况
     * @param $user_id
     * @return array
     */
    public static function getEarnings($user_id)
    {
        $recharge_rebate = MoneyLogModel::getRechargeRebate($user_id);
        $task_rebate = MoneyLogModel::getTaskRebate($user_id);
        return [
            'recharge_rebate' => $recharge_rebate,
            'task_rebate' => $task_rebate
        ];
    }

    /**
     * 获取父级ID
     * @param $user_id
     * @return int|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getParentId($user_id)
    {
        $redis = Cache::store('redis');
        return $redis->remember('user_leader1_' . $user_id, function () use ($user_id) {
            $temp = self::where('id', $user_id)->field('leader1')->find();
            if (empty($temp)) {
                return 0;
            }
            return $temp['leader1'];
        }, 300);
    }

    /**
     *  获取用户流水
     * @param $user_id
     * @param array $where_time
     * @return float|int
     */
    public static function getUserWater($user_id, array $where_time = []): float|int
    {
        if (empty($user_id)) {
            return 0;
        }
        //明细类型 0未定义 1用户充值 2用户提现 3任务返利 4储值返利(充值送款） 5抽奖活动 6签到活动 7升级返利 8分享奖励 9一级返利 10二级返利 11三级返利 12广告位理财 13AI智能托管理财 14AI智能托管收益 15直推奖励 16广告位收益 17 解锁vip
        // 只有购买广告库存才算流水
        return (new  MoneyLogRepository())->getTotalMoney([$user_id], [12], 2, $where_time);
    }

    /**
     * 获取用户入金
     * @param $user_id
     * @return float
     */
    public static function getUserAddBalance($user_id): float
    {
        $money_log_model = new MoneyLogModel();
        return $money_log_model->where(['user_id' => $user_id, 'change' => 1])->where('type', 1)->whereIn('detail_type', [0, 1])->sum('money');
    }

    /**
     * 获取最新充值记录
     * @param $user_id
     * @return mixed
     */
    public static function getLatestRechargeRecord($user_id): mixed
    {
        return (new MoneyLogRepository())->order(['id' => 'desc'])
            ->findWhere([
                'user_id' => $user_id,
                'change' => 1, // 1 加钱 2-减钱
                'type' => MoneyLogModel::TYPE_USER, // 基础钱包
                ['detail_type', 'in', [0, 1]],// 充值 0未定义(后台补单） 1用户充值
            ]);
    }

    public function financeProductOrder(): HasMany
    {
        return $this->hasMany('FinanceProductOrderModel', 'user_id', 'id');
    }

    /**
     * 获取用户的直推数量
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     */
    public static function getLevel1ActiveUserNum($user_id)
    {
        // 获取一级下级已经充值并且已经购买广告位的用户数
        //有效充值
        $recharge_num_valid_user = \app\common\model\ConfigModel::getConfigValue('recharge_num_valid_user');
        return UserModel::hasWhere('financeProductOrder', [
            ['status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]],
            ['level_id', '>', 0]
        ])
            ->where(['leader1' => $user_id])
            ->where('recharge_money', '>=', $recharge_num_valid_user)
            ->count();
    }

    /**
     * @param $user_id
     * @param $recharge_money
     * @return int
     * 新定义【直推用户 = 用户注册、充值（首冲大于$recharge_money）、购买广告位】
     * 如果是获取当天的直推用户数的话添加时间条件
     */
    public static function getLevel1ActiveUserNumForRecharge($user_id, $recharge_money, $today = false)
    {
        $orderWhere[] = ['status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]];
        if ($today) {
            $orderWhere[] = ['create_time', 'between', [strtotime(date("Y-m-d")), strtotime(date("Y-m-d 23:59:59"))]];
            // 当天
        }
        $orderWhere[] = ['level_id', '>', 0];
        // 获取一级下级首次充值达到一定数额并且已经购买广告位的用户数
        $query = UserModel::hasWhere('financeProductOrder', $orderWhere
        )->hasWhere("recharge", [['money', '>=', $recharge_money], ['is_first', "=", 1]])
            ->where(['leader1' => $user_id])->where("create_time", ">", strtotime(config('app.activity_start_time')));
        return $query->count();
    }

    public function adPlan(): HasOne
    {
        return $this->hasOne(AdPlanModel::class, 'id', 'ad_plan_id');
    }

    public static function getRegisterUser($user_ids, $new = false)
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        if ($new) {
            $user_model = new UserModel();
            return $user_model->where('id in (' . $user_ids . ')')
                ->where('create_time', '>', strtotime(date("Y-m-d")))
                ->count();
        } else {
            return 0;
        }
    }

    // 团队活跃人数（当天）流量包和AI未结算的
    public static function getTeamTodayActiveUser($user_ids): int
    {
        if (is_array($user_ids)) {
            $user_ids = implode(',', $user_ids);
        }
        if (!$user_ids) {
            return 0;
        }
        // 获取
        // 今日做任务的用户
        $finance_product_user = FinanceProductOrderModel::where('user_id in (' . $user_ids . ')')
            ->where('status', 'in', [FinanceProductOrderRepository::STATUS_WAIT_BUY, FinanceProductOrderRepository::STATUS_PURCHASED])
            ->group('user_id')
            ->count();
        $investment_product_user = InvestmentProductOrderModel::where('user_id in (' . $user_ids . ')')
            ->where('status', InvestmentProductOrderRepository::STATUS_PURCHASED)
            ->group('user_id')
            ->count();

        return $finance_product_user + $investment_product_user;
    }

    public function recharge(): hasMany
    {
        return $this->hasMany(RechargeModel::class, 'user_id', 'id');
    }
}
