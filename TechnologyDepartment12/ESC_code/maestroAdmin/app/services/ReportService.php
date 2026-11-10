<?php

namespace app\services;

use app\common\model\UserModel;
use think\facade\Db;
use Carbon\Carbon;
use think\facade\Cache;

class ReportService {

    /**
     * 统计用户今天下级返佣总额
     * @param int $user_id 用户ID
     * @return int
     */
    public static function getTodaySubIncomeByUserId($user_id) {
        $income = self::getIncomeByUserId($user_id, Carbon::today()->format('Ymd'), 2);
        return $income;
    }

    /**
     * 统计用户昨天下级返佣总额
     * @param int $user_id 用户ID
     * @return int
     */
    public static function getYesterdaySubIncomeByUserId($user_id) {
        $income = self::getIncomeByUserId($user_id, Carbon::yesterday()->format('Ymd'), 2);
        return $income;
    }
    /**
     * 统计用户今天合约收益总额
     * @param int $user_id 用户ID
     * @return int
     */
    public static function getTodayIncomeByUserId($user_id) {
        $income = self::getIncomeByUserId($user_id, Carbon::today()->format('Ymd'), 4);
        return $income;
    }

    /**
     * 统计用户昨天合约收益总额
     * @param int $user_id 用户ID
     * @return int
     */
    public static function getYesterdayIncomeByUserId($user_id) {
        $income = self::getIncomeByUserId($user_id, Carbon::yesterday()->format('Ymd'), 4);
        return $income;
    }

    /**
     * 统计用户本周合约收益总额
     * @param int $user_id 用户ID
     * @return int
     */
    public static function getWeekIncomeByUserId($user_id) {
        $dates = [];
        $now = Carbon::now();
        for ($i = $now->startOfWeek(Carbon::TUESDAY)->timestamp; $i <= $now->endOfWeek(Carbon::MONDAY)->timestamp; $i += 86400) {
            $dates[] = Carbon::parse($i)->format('Ymd');
        }
        $income = self::getIncomeByUserId($user_id, $dates);
        return $income;
    }

    /**
     * 统计用户本月合约收益总额
     * @param int $user_id 用户ID
     * @return int
     */
    public static function getMonthIncomeByUserId($user_id) {
        $dates = [];
        $now = Carbon::now();
        for ($i = $now->firstOfMonth()->timestamp; $i <= $now->endOfMonth()->timestamp; $i += 86400) {
            $dates[] = Carbon::parse($i)->format('Ymd');
        }
        $income = self::getIncomeByUserId($user_id, $dates);
        return $income;
    }

    /**
     * 统计用户上月合约收益总额
     * @param int $user_id 用户ID
     * @return int
     */
    public static function getLastMonthIncomeByUserId($user_id) {
        $dates = [];
        $now = Carbon::now()->subMonth();
        for ($i = $now->firstOfMonth()->timestamp; $i <= $now->endOfMonth()->timestamp; $i += 86400) {
            $dates[] = Carbon::parse($i)->format('Ymd');
        }
        $income = self::getIncomeByUserId($user_id, $dates);
        return $income;
    }

    /**
     * 统计用户总合约收益总额
     * @param int $user_id 用户ID
     * @return int
     */
    public static function getTotalIncomeByUserId($user_id) {
        $user = UserModel::where('id', $user_id)->where('status', 1)->find();
        if (empty($user)) {
            return 0;
        }
        $dates = [];
        $reg_time = Carbon::parse($user['create_time']);
        for ($i = $reg_time->timestamp; $i <= Carbon::tomorrow()->timestamp; $i += 86400) {
            $dates[] = Carbon::parse($i)->format('Ymd');
        }
        $income = self::getIncomeByUserId($user_id, $dates);
        return $income;
    }

    /**
     * 统计用户合约收益总额
     * @param int $user_id 用户ID
     * @param string|array $date 日期数组，日期格式Ymd
     * @return int
     */
    public static function getIncomeByUserId($user_id, $date = null, $type = [4, 2]) {
        return self::getMoneyLogByUserId($user_id, $date, $type);
    }

    /**
     * 根据用户ID，日期，类型统计用户账变记录金额
     * @param int $user_id 用户ID
     * @param string|array $dates 日期数组，日期格式Ymd
     * @param int|array $type 参考config.my.money_type
     * @return int
     */
    public static function getMoneyLogByUserId($user_id, $dates = null, $type = null) {
        if (empty($user_id)) {
            return 0;
        }
        if (is_null($dates)) {
            $dates = [Carbon::today()->format('Ymd')];
        }elseif(!is_array($dates)) {
            $dates = [$dates];
        }
        $where = [
            ['user_id' , '=',  $user_id]
        ];
        if (is_numeric($type)) {
            $where[] = ['type', '=', $type];
        }elseif(isArrayAndNotEmpty($type)) {
            $where[] = ['type', 'IN', $type];
        }
        $redis = Cache::store('redis');
        $redis_key = config('redis_cache_key.money_log') . ":" . md5(json_encode([$where, $dates]));
        if ($redis->has($redis_key)) {
            return $redis->get($redis_key);
        }
        $result = Db::table('qd_money_log')->where($where)->sum('money');
        $num = i18n_number_format($result);
        $redis->set($redis_key, $num, count($dates) * 120);

        return $num;
    }

    /**
     * 获取团队总人数
     */
    public static function getTeamTotalCountByUserId($user_id = 0) {
        return UserModel::where(implode("|", getLeaderFieldName()), $user_id)->count();
    }
    /**
     * 获取团队当天注册人数
     */
    public static function getTeamTodayUsersByUserId($user_id = 0) {
        return UserModel::where(implode("|", getLeaderFieldName()), $user_id)->whereTime('create_time', "today")->count();
    }

    /**
     * 获取团队总人数
     */
    public static function getTeamTotalUserMoneyByUserId($user_id = 0) {
        return UserModel::where(implode("|", getLeaderFieldName()), $user_id)->sum('user_money');
    }

    /**
     * 获取团队总收益
     */
    public static function getTeamTotalGreyByUserId($user_id = 0) {
        $fenxiao1 = Db::table('qd_money_log')->whereIn('type', [2, 4])->where('user_id', "IN", function($query) use($user_id){
            $query->name('home_user')->whereNull('delete_time')->where(implode("|", getLeaderFieldName()), $user_id)->field('id');
        })->sum('money');
        $fenxiao2 = Db::name('user_report')->whereIn('type', [2, 4])->where('user_id', "IN", function($query) use($user_id){
            $query->name('home_user')->whereNull('delete_time')->where(implode("|", getLeaderFieldName()), $user_id)->field('id');
        })->sum('total');
        return i18n_sprintf($fenxiao1 + $fenxiao2);
    }

    /**
     * 获取团队总充值
     */
    public static function getTeamTotalRechargeMoneyByUserId($user_id = 0) {
        $result = Db::name('recharge')->where('user_id', "IN", function($query) use($user_id) {
                    $query->name('home_user')->whereNull('delete_time')->where(implode("|", getLeaderFieldName()), $user_id)->field('id');
                })->where("status", 2)->sum('money');
        return $result;
    }

    /**
     * 获取团队总充值人数
     */
    public static function getTeamTotalRechargeUsersByUserId($user_id = 0) {
        $result = Db::name('recharge')->where('user_id', "IN", function($query) use($user_id) {
                    $query->name('home_user')->whereNull('delete_time')->where(implode("|", getLeaderFieldName()), $user_id)->field('id');
                })->where("status", 2)->count('DISTINCT user_id');

        return $result;
    }

    /**
     * 获取团队总提现
     */
    public static function getTeamTotalWithdrawMoneyByUserId($user_id = 0) {
        $result = Db::name('withdrawals')->where('user_id', "IN", function($query) use($user_id) {
                    $query->name('home_user')->whereNull('delete_time')->where(implode("|", getLeaderFieldName()), $user_id)->field('id');
                })->where("status", 2)->sum('money');
        return $result;
    }

    /**
     * 获取团队总提现人数
     */
    public static function getTeamTotalWithdrawUsersByUserId($user_id = 0) {
        $result = Db::name('withdrawals')->where('user_id', "IN", function($query) use($user_id) {
                    $query->name('home_user')->whereNull('delete_time')->where(implode("|", getLeaderFieldName()), $user_id)->field('id');
                })->where("status", 2)->count('DISTINCT user_id');

        return $result;
    }

    /**
     * 获取团队总活跃人数
     */
    public static function getTeamTotalActiveUsersByUserId($user_id = 0) {
        $result = Db::name('contract')->where('user_id', "IN", function($query) use($user_id) {
                    $query->name('home_user')->whereNull('delete_time')->where(implode("|", getLeaderFieldName()), $user_id)->field('id');
                })->count('DISTINCT user_id');

        return $result;
    }

    /**
     * 团队报表
     * @param type $user_id
     * @return type
     */
    public static function getTeamReport($user_id) {
        $redis_key = "getTeamReport:v2:{$user_id}";
        $redis = Cache::store('redis');
        if ($redis->has($redis_key)) {
            return $redis->get($redis_key);
        }
        $result = [
            'team_total_count' => self::getTeamTotalCountByUserId($user_id),
            'team_total_grey' => self::getTeamTotalGreyByUserId($user_id),
            'team_total_recharge_money' => self::getTeamTotalRechargeMoneyByUserId($user_id),
            'team_total_recharge_users' => self::getTeamTotalRechargeUsersByUserId($user_id),
            'team_total_withdraw_money' => self::getTeamTotalWithdrawMoneyByUserId($user_id),
            'team_total_withdraw_users' => self::getTeamTotalWithdrawUsersByUserId($user_id),
            'team_total_user_money' => self::getTeamTotalUserMoneyByUserId($user_id),
//            'team_total_active_users' => self::getTeamTotalActiveUsersByUserId($user_id),
            'team_today_new_users' => self::getTeamTodayUsersByUserId($user_id),
        ];

        $redis->set($redis_key, $result, 60);

        return $result;
    }


    // 获取新增人数
    public static function getLevelCountByUserId($userid, $level = '1') {
        if (!in_array($level, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10])) {
            return 0;
        }
        return Db::name('home_user')->whereNull('delete_time')->where("leader{$level}", $userid)->count();
    }

    // 获取会员级别用户数
    public static function getUserCountByLevelId($level_id = 0) {
        return Db::name('home_user')->whereNull('delete_time')->where("level_id", $level_id)->count();
    }
}
