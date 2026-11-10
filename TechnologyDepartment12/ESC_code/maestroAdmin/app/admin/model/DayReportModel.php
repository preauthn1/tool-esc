<?php

namespace app\admin\model;

use app\common\model\OrderModel;
use app\common\model\PaymentPlatformModel;
use Carbon\Carbon;
use think\facade\Cache;
use think\facade\Db;
use think\Model;

class DayReportModel extends Model
{

    // 确定链接表名
    protected $name = 'day_report';
    protected $autoWriteTimestamp = true;

    /**
     *
     * @param type $date
     * @param type $update 是否更新
     * @return type
     */
    public function stat($date = null, $update = false, $key = null)
    {
        if (is_null($date)) {
            return;
        }
        $where[] = ['is_proxy', '=', 1];
        $user_ids = \app\common\model\UserModel::where($where)->field('id,user_name,remark')->select();
        if (empty($user_ids)) {
            return;
        }
        $user_model = new \app\common\model\UserModel();
        $redis = Cache::store('redis');
        $key_cache_key = "day_report_team_ids";
        $out_report = [];
        foreach ($user_ids as $value) {
            $team_ids = $redis->get($key_cache_key . $value['id']);
            if (empty($team_ids)) {
                $team_ids = $user_model->where('tree_id', 'like', "%/{$value['id']}/%")->where('tree_id', 'not like', '%/' . $value['id'] . '/')->column('id');
                if (empty($team_ids)) {
                    continue;
                }
                $redis->set($key_cache_key . $value['id'], $team_ids, 600);
            }
            $stat_date = Carbon::parse($date);
            $where_time = [$stat_date->startOfDay()->timestamp, $stat_date->endOfDay()->timestamp];
            $where_temp = [
                ['stat_date', '=', $stat_date->format("Y-m-d")],
                ['user_id', '=', $value['id']]
            ];
            $data['stat_date'] = $stat_date->format("Y-m-d");
            $data['user_id'] = $value['id'];
            $report = db('day_report')->where($where_temp)->find();
            if (!$update && $report) {
                return $report;
            }
            $team_ids_str = implode(',', $team_ids);
            $where = [
                ['is_xuni' , '=',  0],
            ];
            $where_user_ids = 'user_id in (' . $team_ids_str . ')';
            if ($key == 'all' || is_null($key)) {
                $data['recharge'] = RechargeModel::where($where)->where($where_user_ids)->where('status', 2)->whereTime('success_time', 'between', $where_time)->sum('money');

                $recharge_json = [
                    //三方充值
                    'three' => RechargeModel::where($where)->where($where_user_ids)->where('type', 3)->where('status', 2)->whereTime('success_time', 'between', $where_time)->sum('money'),
                    //合约充值
                    'contract' => RechargeModel::where($where)->where($where_user_ids)->where('type', 2)->where('status', 2)->whereTime('success_time', 'between', $where_time)->sum('money'),
                    //手动补单
                    'sys' => RechargeModel::where($where)->where($where_user_ids)->where('type', 1)->where('status', 2)->whereTime('success_time', 'between', $where_time)->sum('money'),
                ];
                $data['recharge_json'] = json_encode($recharge_json);
                $data['recharge_user'] = RechargeModel::where($where)->where($where_user_ids)->where('status', 2)->whereTime('success_time', 'between', $where_time)->count('DISTINCT user_id');
                $today_userids = RechargeModel::where($where)->where($where_user_ids)->where('status', 2)->whereTime('success_time', 'between', $where_time)->column('DISTINCT user_id');
                $before_today_userids = RechargeModel::where($where)->where($where_user_ids)->where('status', 2)->whereTime('success_time', "<", $stat_date->timestamp)->column('DISTINCT user_id');
                $first_recharge_user = 0;
                foreach ($today_userids as $id) {
                    if ((isArrayAndNotEmpty($before_today_userids) && !in_array($id, $before_today_userids)) || empty($before_today_userids)) {
                        $first_recharge_user++;
                    }
                }
                $data['first_recharge_user'] = $first_recharge_user;
                $data['withdraw'] = WithdrawModel::where($where)->where($where_user_ids)->where('status', 4)->whereTime('check_time', 'between', $where_time)->sum('original_money');

                $withdraw_list = PaymentPlatformModel::where(['withdraw_is_show' => 1])->field('id,name')->select();
                $withdraw_json = [];
                foreach ($withdraw_list as $val) {
                    $withdraw_json['withdraw_' . $val['id']] = WithdrawModel::where($where)->where($where_user_ids)->where('channel', $val['id'])->where('status', 4)->whereTime('check_time', 'between', $where_time)->sum('original_money');
                }
                $data['withdraw_json'] = json_encode($withdraw_json);

                $data['withdraw_user'] = WithdrawModel::where($where)->where($where_user_ids)->where('status', 4)->whereTime('check_time', 'between', $where_time)->count('DISTINCT user_id');
                $data['wr_diff'] = sprintf4f($data['recharge'] - $data['withdraw']);
            }
            $data['update_time'] = time();
            $data['user_name'] = $value['user_name'] . "(" . $value['remark'] . ")";
            if ($report) {
                $this->where('id', $report['id'])->update($data);
            } else {
                $data['create_time'] = time();
                $this->insert($data);
            }
            if (!is_null($key) && isset($data[$key])) {
                return $data[$key];
            }
        }
        return $this->stat($date);
    }

    /**
     *
     * @param type $date
     * @param type $update 是否更新
     * @return type
     */
    public function agent_stat($date = null, $update = false, $user_id = 0, $key = null)
    {
        if (is_null($date) || empty($user_id)) {
            return;
        }
        $stat_date = Carbon::parse(Carbon::parse($date)->format("Y-m-d 00:00:00"));
        $where_time = [$stat_date->timestamp, Carbon::parse($stat_date)->addDays()->timestamp];
        $Ymd = $stat_date->format("Ymd");
        $data = [
            'stat_date' => $stat_date->format("Y-m-d"),
            'user_id' => $user_id
        ];
        $report = db('day_report')->where($data)->find();
        if (!$update && $report) {
            return $report;
        }

        if ($key == 'all' || is_null($key)) {
            $data['recharge'] = RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 2)->whereLike('u.tree_id', "%/{$user_id}/%")->whereTime('r.success_time', 'between', $where_time)->sum('r.money');
            $data['recharge_user'] = RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 2)->whereLike('u.tree_id', "%/{$user_id}/%")->whereTime('r.success_time', 'between', $where_time)->count('DISTINCT user_id');
            $today_userids = RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 2)->whereLike('u.tree_id', "%/{$user_id}/%")->whereTime('r.success_time', 'between', $where_time)->column('DISTINCT user_id');
            $before_today_userids = RechargeModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 2)->whereLike('u.tree_id', "%/{$user_id}/%")->whereTime('r.success_time', "<", $stat_date->timestamp)->column('DISTINCT user_id');
            $first_recharge_user = 0;
            foreach ($today_userids as $id) {
                if ((isArrayAndNotEmpty($before_today_userids) && !in_array($id, $before_today_userids)) || empty($before_today_userids)) {
                    $first_recharge_user++;
                }
            }
            $data['first_recharge_user'] = $first_recharge_user;
            $data['withdraw'] = WithdrawModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 2)->whereLike('u.tree_id', "%/{$user_id}/%")->whereTime('r.check_time', 'between', $where_time)->sum('r.money');
            $data['withdraw_user'] = WithdrawModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.status', 2)->whereLike('u.tree_id', "%/{$user_id}/%")->whereTime('r.check_time', 'between', $where_time)->count('DISTINCT user_id');
            $data['wr_diff'] = sprintf4f($data['recharge'] - $data['withdraw']);
            $data['user_money'] = MemberModel::where('is_xuni', 0)->whereLike('tree_id', "%/{$user_id}/%")->whereTime('create_time', "<", $stat_date->timestamp)->sum('user_money');
            $data['online_user'] = Db::name('home_user_login')->alias('l')->join('home_user u', 'u.id=l.user_id')->where('u.is_xuni', 0)
                ->whereLike('u.tree_id', "%/{$user_id}/%")->whereTime('l.create_time', 'between', $where_time)->count('DISTINCT user_id');
        }
        if ($key == 'all' || $key == 'order_user') {
            $data['order_user'] = OrderModel::where('is_xuni', 0)->where('paid_status', 2)
                ->whereTime('create_time', 'between', $where_time)->whereIn('user_id', function ($query) use ($user_id) {
                    $query->name('home_user')->where('is_xuni', 0)->whereLike('tree_id', "%/{$user_id}/%")->field('id');
                })->count('DISTINCT user_id');
        }
        if ($key == 'all' || $key == 'order_price') {
            $data['order_price'] = OrderModel::where('is_xuni', 0)->where('paid_status', 2)->whereTime('create_time', 'between', $where_time)
                ->whereIn('user_id', function ($query) use ($user_id) {
                    $query->name('home_user')->where('is_xuni', 0)->whereLike('tree_id', "%/{$user_id}/%")->field('id');
                })->sum('retail_price');
        }
        if ($key == 'all' || $key == 'order_gray') {
            $data['order_gray'] = OrderModel::where('is_xuni', 0)->where('status', 2)->where('repurchase_status', 3)->whereIn('user_id', function ($query) use ($user_id) {
                $query->name('home_user')->where('is_xuni', 0)->whereLike('tree_id', "%/{$user_id}/%")->field('id');
            })->whereTime('update_time', 'between', $where_time)->sum('return_bonus');
        }
        if ($key == 'all' || $key == 'rebate') {
            $data['rebate'] = MoneyModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.type', 2)->whereLike('u.tree_id', "%/{$user_id}/%")
                ->whereTime('r.create_time', 'between', $where_time)->sum('r.money');
        }
        if ($key == 'all' || $key == 'manual_money') {
            $data['manual_money'] = MoneyModel::alias('r')->join('home_user u', 'u.id=r.user_id')
                ->where('u.is_xuni', 0)->where('r.type', 0)->whereLike('u.tree_id', "%/{$user_id}/%")
                ->whereTime('r.create_time', 'between', $where_time)->sum('r.money');
        }
        $data['update_time'] = time();
        if ($report) {
            db('day_report')->where('id', $report['id'])->update($data);
        } else {
            $data['create_time'] = time();
            db('day_report')->insert($data);
        }
        if (!is_null($key) && isset($data[$key])) {
            return $data[$key];
        }
        return $this->agent_stat($date, false, $user_id);
    }
}
