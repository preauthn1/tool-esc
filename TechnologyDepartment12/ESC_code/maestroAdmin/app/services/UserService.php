<?php

namespace app\services;

use app\common\model\UserModel;
use app\admin\model\LevelModel;
use think\facade\Db;
use app\admin\model\WithdrawModel;
use app\common\model\WithdrawLimitModel;
use app\common\model\ActivityRecordModel;
use think\Exception;

class UserService {

    /**
     * 判断用户是否参与活动
     * @param type $user_id
     * @return type
     */
    public static function isActivity($user_id) {
        return ActivityRecordModel::where([
            'user_id' => $user_id,
            'status' => 1
        ])->count();
    }
    /**
     * 获取用户提现手续费
     * @param type $user_id
     * @return type
     */
    public static function getUserWithdrawRate($user_id) {
        $config = getConfig();
        if (strexists($config['withdraw_rate'], "|")) {
            $rates = explode("|", $config['withdraw_rate']);
            $count = WithdrawModel::where('user_id', $user_id)->where('status', '<>', 3)->whereTime('create_time', 'month')->count();
            $rate = $rates[count($rates) - 1];
            for ($i = 0; $i <= count($rates); $i++) {
                if ($count == $i && isset($rates[$i])) {
                    $rate = $rates[$i];
                    break;
                }
            }
        }else{
            $rate = $config['withdraw_rate'];
        }
        return floatval($rate);
    }

    /**
     * 获取用户提现限制规则
     * @param type $user_id
     * @return type
     */
    public static function getUserWithdrawLimit($user_id) {
        $limit = WithdrawLimitModel::where('user_id', $user_id)->where('status', 1)->find();
        return empty($limit) ? [] : $limit->toArray();
    }


    public static function getChildLevelByUserId($user_id, $child_user_id) {
        $text = "";
        if (empty($user_id) || empty($child_user_id)) {
            return $text;
        }
        $child_user = UserModel::where('id', $child_user_id)->find();
        if (empty($child_user)) {
            return $text;
        }
        foreach ($child_user->toArray() as $key => $value) {
            if (strexists($key, "leader") !== false && $value == $user_id) {
                $lv = str_replace('leader', '', $key);
                $text .= lang("{$lv}级代理");
            }
        }
        $text .= "<br><{$child_user['mobile']}>";

        return $text;
    }

    /**
     * 检测用户是否升级了，升级了就结束当前合约
     * @param int $user_id
     */
    public static function checkLevel($user_id) {
        checkLevel($user_id);
    }


    /**
     * 记录下级返佣
     * @param int $user_id 用户ID
     * @param int $money 返佣金娥
     * @param int $from_user_id 来自某个用户ID
     * @param int $order_id 合约ID
     * @return boolean
     */
    public static function addRebate($user_id, $money, $from_user_id = 0, $order_id = 0, $remark = '') {
        if ($money < 0) {
            $money *= -1;
        }
        $money_type = config('my.money_type');
        return self::addMoneyLog($user_id, $money, 2, $money_type['2'] . "{$remark}", 0, $from_user_id, $order_id);
    }

    /**
     * 增加充值账变记录
     * @param type $user_id 用户ID
     * @param type $money 充值金额
     * @param type $order_id 订单ID
     * @return type
     */
    public static function addRechargeMoneyLog($user_id, $money, $order_id = 0) {
        if ($money < 0) {
            $money *= -1;
        }
        $money_type = config('my.money_type');
        return self::addMoneyLog($user_id, $money, 1, $money_type['1'], 0, 0, $order_id);
    }

    /**
     * 增加拼单付款账变记录
     * @param type $user_id 用户ID
     * @param type $money 充值金额
     * @param type $order_id 订单ID
     * @return type
     */
    public static function addPurchase($user_id, $money, $order_id = 0) {
        if ($money > 0) {
            $money *= -1;
        }
        $money_type = config('my.money_type');
        return self::addMoneyLog($user_id, $money, 3, $money_type['3'], 0, 0, $order_id);
    }
    /**
     * 增加拼单回购退款到用户账户余额
     * @param type $user_id 用户ID
     * @param type $money 充值金额
     * @param type $order_id 订单ID
     * @return type
     */
    public static function addRePurchase($user_id, $money, $order_id = 0) {
        if ($money < 0) {
            $money *= -1;
        }
        $money_type = config('my.money_type');
        return self::addMoneyLog($user_id, $money, 9, $money_type['9'], 0, 0, $order_id);
    }

    /**
     * 添加回购佣金到用户账户余额
     * @param int $user_id 用户ID
     * @param int $money 返佣金娥
     * @param int $order_id 投资订单记录ID
     * @return boolean
     */
    public static function addBonus($user_id, $money, $order_id = 0, $remark = '') {
        if ($money < 0) {
            $money *= -1;
        }
        $money_type = config('my.money_type');
        return self::addMoneyLog($user_id, $money, 4, $money_type['4'] . "{$remark}", 0, 0, $order_id);
    }

    /**
     * 更新用户的总收益
     * @param $user_id
     * @param $money
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function updUserEarnings($user_id, $money) {
        if ($money < 0) {
            $money *= -1;
        }
        $userInfo = UserModel::where('id', $user_id)->find();
        $userInfo['earnings_money'] = $userInfo['earnings_money'] == null ? 0 : $userInfo['earnings_money'];
        $userInfo['earnings_money'] += $money;
        $upd_data = [
            'earnings_money' => $userInfo['earnings_money'],
            'id' => $user_id
        ];
        UserModel::update($upd_data);
    }


    /**
     * 后台管理员操作帐变记录
     * @param int $user_id 用户ID
     * @param int $money 变更金额
     * @param int $type 变更类型
     * @param string $remark 备注
     * @param int $admin_id
     * @return boolean
     */
    public static function adminAddMoneyLog($user_id, $money, $type = 0, $remark = '', $admin_id = 0) {
        return self::addMoneyLog($user_id, $money, $type, $remark, 0, 0, 0, $admin_id);
    }

    /**
     * 余额变更记录
     * @param int $user_id 用户ID
     * @param int $money 变更金额
     * @param int $type 变更类型
     * @param string $remarks 备注
     * @param int $is_frozen 是否是冻结金额
     * @param int $from_user_id 来自某个用户ID
     * @param int $order_id 订单ID
     * @param int $admin_id 管理员ID
     * @return boolean
     */
    public static function addMoneyLog($user_id, $money, $type = 0, $remarks = '', $is_frozen = 0, $from_user_id = 0, $order_id = 0, $admin_id = 0) {
        if ($user_id <= 0) {
            return;
        }
        $full_table_name = getMoneyLogTableByDay();
        $is_xuni = Db::name('home_user')->where('id', $user_id)->value('is_xuni');
        $data = [
            'user_id' => $user_id, // 用户ID
            'is_xuni' => $is_xuni == 1 ? 1 : 0,
            'user_money' => 0,
            'money' => $money, // 金额
            'remarks' => $remarks, // 备注
            'type' => $type, // 类型
            'from_user_id' => $from_user_id, // 来自用户
            'order_id' => $order_id, // 订单ID
            'admin_id' => $admin_id, // 操作官员ID
            'create_time' => time()    // 创建时间
        ];
        $ret = Db::table($full_table_name)->insertGetId($data);
        if ($ret) {
            // 变更账户余额
            if ($type == 20) {
                Db::name('home_user')->where(['id' => $user_id])->setInc('points', $money);
                $user_money = Db::name('home_user')->where(['id' => $user_id])->value('points');
            }else{
                Db::name('home_user')->where(['id' => $user_id])->setInc('user_money', $money);
                // 是否冻结账户
                if ($is_frozen == 1) {
                    Db::name('home_user')->where(['id' => $user_id])->setInc('frozen_money', -1 * $money);
                } elseif ($is_frozen == 2) {
                    Db::name('home_user')->where(['id' => $user_id])->setInc('frozen_money', $money);
                }
                // 判断资产是否增加，并且是否是户内变动
                if ($money > 0 && $is_frozen == 0) {
                    Db::name('home_user')->where(['id' => $user_id])->setInc('total_money', $money);
                }
                if ($money > 0 && $type == 1) {
                    Db::name('home_user')->where(['id' => $user_id])->setInc('send_money', $money);
                }
                $user_money = Db::name('home_user')->where(['id' => $user_id])->value('user_money');
            }
            Db::table($full_table_name)->where('id', $ret)->update(['user_money' => floatval($user_money)]);
            checkLevel($user_id);
            return true;
        } else {
            return false;
        }
    }

}
