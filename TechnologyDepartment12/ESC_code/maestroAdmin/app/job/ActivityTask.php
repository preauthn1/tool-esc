<?php
namespace app\job;

use app\admin\model\RechargeModel;
use app\common\model\ActivitynewModel;
use app\common\model\ActivityRecordModel;
use app\common\model\UserModel;
use Carbon\Carbon;
use think\facade\Db;
use think\facade\Log;
use think\queue\Job;

class ActivityTask {
    public function __construct()
    {
        setTimezone();
    }

    protected $exp = ['>', '<', '=', '>=', '<='];
    private function isActive($params) {
        $activity = $params['activity'] ?? null;
        $user = $params['user'] ?? null;
        $recharge = $params['recharge'] ?? null;
        $from_user_id = isset($params['from_user_id']) ? intval($params['from_user_id']) : 0;
        $trigger_type = isset($params['trigger_type']) ? intval($params['trigger_type']) : 0;
        $vip_id = isset($params['vip_id']) ? intval($params['vip_id']) : 0;
        if (!$activity || !$user) {
            return false;
        }
        $isActive = true;
        if (!in_array(0, $activity['user_type'])) {
            if (in_array(6, $activity['user_type'])) { //未充值用户
                $count = RechargeModel::where('user_id', $user->id)->where('status', 2)->count();
                if ($activity->type == 1) {
                    $isActive = $count == 1;
                }else{
                    $isActive = $count == 0;
                }
            }
            if (in_array(1, $activity['user_type'])) { //充值用户
                $count = RechargeModel::where('user_id', $user->id)->where('status', 2)->count();
                if ($activity->type == 1) {
                    $isActive = $count >= 2;
                }else{
                    $isActive = $count >= 1;
                }
            }
            if (in_array(2, $activity['user_type'])) { //二次充值用户
                $count = RechargeModel::where('user_id', $user->id)->where('status', 2)->count();
                if ($activity->type == 1) {
                    $isActive = $count >= 3;
                }else{
                    $isActive = $count >= 2;
                }
            }
            $date_interval = Carbon::parse($user->create_time)->diff($activity->start_time);
            if (in_array(3, $activity['user_type'])) { //注册时间大于活动开始时间为新用户
                $isActive = $date_interval->invert == 1;
            }
            if (in_array(4, $activity['user_type'])) { //注册时间小于活动开始时间为老用户
                $isActive = $date_interval->invert == 0;
            }
            if (in_array(5, $activity['user_type'])) { //有直属下级的用户
                $child_count = UserModel::where('leader1', $user->id)->count();
                $isActive = $child_count >= 0;
            }
        }
        $isActive2 = true;
        if ( (
                in_array($activity->type, [1, 3]) || // 充值活动判断
                (in_array($activity->type, [6]) && in_array($trigger_type, [1, 2, 7])) //转盘抽奖 充值相关
                ) && $activity->extra != '') { //充值活动判断
            $extra = array_unique(array_filter(explode("\n", html_entity_decode($activity->extra))));
            $isActive2 = !is_null($recharge);
            if (isArrayAndNotEmpty($extra) && !is_null($recharge)) {
                foreach ($extra as $item) {
                    list($exp, $num) = explode("|", $item);
                    if (!in_array($exp, $this->exp) || intval($num) == 0) {
                        $this->output("充值活动无效规则:{$item}");
                        continue;
                    }
                    $this->output("充值活动规则判断:{$recharge->money}{$exp}{$num}");
                    switch ($exp) {
                        case '=':
                            $isActive2 = $isActive2 && $recharge->money == $num;
                            break;
                        case '>':
                            $isActive2 = $isActive2 && $recharge->money > $num;
                            break;
                        case '<':
                            $isActive2 = $isActive2 && $recharge->money < $num;
                            break;
                        case '>=':
                            $isActive2 = $isActive2 && $recharge->money >= $num;
                            break;
                        case '<=':
                            $isActive2 = $isActive2 && $recharge->money <= $num;
                            break;
                        default:
                            break;
                    }
                }
            }
        }

        if(in_array($activity->type, [6])) { //转盘抽奖
            if (!in_array($trigger_type, $activity->trigger_type)) {
                $this->output("不在触发规则内,{$trigger_type},--" . implode("|", $activity->trigger_type));
                return false;
            }

            if ($trigger_type == 7) { //首充
                $count = RechargeModel::where('user_id', $from_user_id)->where('status', 2)->count();
                $isActive = $count == 1;
                if (!$isActive) {
                    $this->output("不符合首充:「{$from_user_id}」{$user->id}");
                }
            }
            if ($trigger_type == 8 && $activity->extra != '') { //会员升级
                $extra = array_unique(array_filter(explode("\n", html_entity_decode($activity->extra))));
                if (isArrayAndNotEmpty($extra)) {
                    foreach ($extra as $item) {
                        list($exp, $vip_id_str) = explode("|", $item);
                        if (!in_array($exp, ['vip_id'])) {
                            continue;
                        }
                        $vip_id_arr = explode(",", $vip_id_str);
                        if (!in_array($vip_id, $vip_id_arr)) {
                            $this->output("VIP等级ID:{$vip_id}不在{$vip_id_str}内");
                            return false;
                        }
                    }
                }
            }
        }

        //邀请活动判断
        if(in_array($activity->type, [2,3])) {
            if ($activity->extra != '') { //直属下级用户数判断
                $extra = array_unique(array_filter(explode("\n", html_entity_decode($activity->extra))));
                if (isArrayAndNotEmpty($extra)) {
                    $userId = $user->id;
                    $child_user_vip_level = 0;
                    $child_user_type = 0;
                    $child_users = 1;
                    foreach ($extra as $item) {
                        list($exp, $val) = explode("|", $item);
                        if (!in_array($exp, ['child_users', 'child_user_type', 'child_user_vip_level'])) {
                            continue;
                        }
                        if ($exp == 'child_users') {
                            $child_users = intval($val);
                        }
                        if ($exp == 'child_user_type') {
                            $child_user_type = intval($val);
                        }
                        if ($exp == 'child_user_vip_level') {
                            $child_user_vip_level = intval($val);
                        }
                    }
                    $where = ['leader1' => $userId];
                    if ($child_user_vip_level > 0) {
                        $where[] = ['level_id', ">=", $child_user_vip_level];
                    }
                    if ($child_user_type == 1) { //直属下级充值用户
                        $users = Db::name('recharge')->where('user_id', "IN", function($query) use ($where) {
                            $query->name('home_user')->where($where)->field('id');
                        })->where("status", 2)->count("DISTINCT user_id");
                    }else{ //所有直属下级
                        $users = Db::name('home_user')->where($where)->count();
                    }
                    if ($users < $child_users) {
                        $this->output("直属下级用户数不够:{$child_users}");
                        return false;
                    }
                }
            }
        }

        $isActive3 = true;
        $where = [
            ['activity_id' , '=',  $activity->id],
            ['user_id' , '=',  $user->id],
        ];

        if ($from_user_id > 0) {
            $from_user_count = ActivityRecordModel::where($where)->where('from_user_id', $from_user_id)->count();
            if ($from_user_count > 0) {
                $this->output("同一用户只允许返利一次");
                return false;
            }
        }
        if ($activity->times > 0) { //判断总次数
            $count = ActivityRecordModel::where($where)->count();
            if ($count >= $activity->times) {
                $isActive3 = false;
            }
        }
        if ($activity->day_times_type == 1) {
            $where[] = ['trigger_type', '=', $trigger_type];
        }
        $day_count = ActivityRecordModel::where($where)->whereTime('create_time', 'today')->count();
        if ($day_count >= $activity->day_times) { //判断每天次数
            $isActive3 = false;
        }
        if (!$isActive) {
            $this->output("不符合用户类型规则");
        }
        if (!$isActive2) {
            $this->output("不符合充值条件规则");
        }
        if (!$isActive3) {
            $this->output("不符合赠送次数条件规则");
        }
        return $isActive && $isActive2 && $isActive3;
    }


    private function sendActive($params) {
        $activity = $params['activity'] ?? null;
        $user = $params['user'] ?? null;
        $recharge = $params['recharge'] ?? null;
        $from_user_id = isset($params['from_user_id']) ? intval($params['from_user_id']) : 0;
        $trigger_type = isset($params['trigger_type']) ? intval($params['trigger_type']) : 0;
        if (!$activity || !$user) {
            return false;
        }

        $reward = intval($activity->reward);
        if (in_array($activity->type, [1,3])) { //充值活动判断
            if (!$recharge) {
                return false;
            }
            if (preg_match('/(.+)%$/', $activity->reward, $match)) {
                $rate = isset($match[1]) ? intval($match[1]) : 0;
                $reward = sprintf4f($recharge->money * ($rate / 100));
            }
            $this->output("充值金额:{$recharge->money},活动奖励:{$activity->reward},奖励:{$reward}");
        }
        if ($reward <= 0) {
            return;
        }
        $field = '-';
        Db::startTrans();
        try {
            $userinfo = Db::name('home_user')->where('id', $user->id)->lock(true)->find();
            if (!empty($userinfo)) {
                if ($activity->reward_type == 3) {
                    Db::name('turntable_count')->insert([
                        "activity_id" => $activity->id,
                        "user_id" => $user->id,
                        "from_user_id" => $from_user_id,
                        "remark" => $activity->title,
                        "num" => $reward,
                        "total" => $reward,
                        "create_time" => time(),
                        "update_time" => time(),
                    ]);
                }elseif(in_array($activity->reward_type, [1,2])) {
                    $field = $activity->reward_type == 1 ? 'user_money' : 'points';
                    Db::name('home_user')->where('id', $user->id)->setInc($field, $reward);
                }
                Db::name('activity_record')->insert([
                    "activity_id" => $activity->id,
                    "user_id" => $user->id,
                    "from_user_id" => $from_user_id,
                    "befor_num" => isset($userinfo[$field]) ? $userinfo[$field] : 0,
                    'is_xuni' => $userinfo['is_xuni'],
                    "reward" => $reward,
                    "reward_type" => $activity->reward_type,
                    "trigger_type" => $trigger_type,
                    "status" => 1,
                    "create_time" => time(),
                    "update_time" => time(),
                ]);
                $this->output("用户ID:{$user->id},活动:{$activity->title},奖励:{$reward},来自用户ID:{$from_user_id}");
            }
            // 提交事务
            Db::commit();
        } catch (\think\Exception $exc) {
            $this->output($exc->getMessage());
            Log::write($exc->getMessage() . "\n" . $exc->getTraceAsString(), "error");
            // 回滚事务
            Db::rollback();
        }
    }

    private function getActivitys($type) {
        $list = ActivitynewModel::where(['type' => $type, 'status' => 1])
                ->whereTime('start_time', "<=", now())
                ->whereTime('end_time', ">=", now())
                ->select();
        return $list;
    }


    //充值活动
    public function type1(Job $job, $data){
        if (empty($data['user_id']) || empty($data['recharge_id'])) {
            $job->delete(); //删除
            return;
        }
        $user = UserModel::where('id', $data['user_id'])->find();
        if (empty($user)) {
            $this->output("{$data['user_id']}用户信息不存在");
            $job->delete(); //删除
            return;
        }
        $recharge = RechargeModel::where('id', $data['recharge_id'])->where('user_id', $user->id)->where('status', 2)->find();
        if (empty($recharge)) {
            $this->output("{$data['recharge_id']}充值记录不存在");
            $job->delete(); //删除
            return;
        }
        $list = $this->getActivitys(1);

        if (empty($list)) {
            $job->delete(); //删除
            return;
        }

        foreach ($list as $activity) {
            $params = ['activity' => $activity, 'user' => $user, 'recharge' => $recharge];
            if (!$this->isActive($params)) {
                $this->output("{$data['user_id']}不符合[{$activity->title}]规则");
                continue;
            }
            $this->sendActive($params);
        }
        $job->delete(); //删除
    }

    //邀请活动
    public function type2(Job $job, $data){
        if (empty($data['user_id'])) {
            $job->delete(); //删除
            return;
        }
        $user = UserModel::where('id', $data['user_id'])->find();
        if (empty($user)) {
            $this->output("{$data['user_id']}用户信息不存在");
            $job->delete(); //删除
            return;
        }
        $leader1_user = UserModel::where('id', $user->leader1)->find();
        if (empty($leader1_user)) {
            echo "{$data['user_id']}无上级用户\n";
            $job->delete(); //删除
            return;
        }
        $list = $this->getActivitys(2);

        if (empty($list)) {
            $job->delete(); //删除
            return;
        }
        foreach ($list as $activity) {
            $params = [
                'activity' => $activity,
                'user' => $leader1_user,
                'from_user_id' => $user->id
            ];
            if (!$this->isActive($params)) {
                $this->output("{$leader1_user->id}来自{$user->id}不符合[{$activity->title}] 规则");
                continue;
            }
            $this->sendActive($params);
        }
        $job->delete(); //删除
    }
    //邀请充值活动
    public function type3(Job $job, $data){
        if (empty($data['user_id']) || empty($data['recharge_id'])) {
            $job->delete(); //删除
            return;
        }
        $user = UserModel::where('id', $data['user_id'])->find();
        if (empty($user) || empty($user->leader1)) {
            $this->output("{$data['user_id']}用户信息不存在或无上级");
            $job->delete(); //删除
            return;
        }
        $leader1_user = UserModel::where('id', $user->leader1)->find();
        if (empty($leader1_user)) {
            $this->output("{$data['user_id']}无上级用户");
            $job->delete(); //删除
            return;
        }
        $recharge = RechargeModel::where('id', $data['recharge_id'])->where('user_id', $user->id)->where('status', 2)->find();
        if (empty($recharge)) {
            $this->output("{$data['recharge_id']}充值记录不存在");
            $job->delete(); //删除
            return;
        }
        $list = $this->getActivitys(3);

        if (empty($list)) {
            $job->delete(); //删除
            return;
        }
        foreach ($list as $activity) {
            $params = [
                'activity' => $activity,
                'user' => $leader1_user,
                'recharge' => $recharge,
                'from_user_id' => $user->id
            ];
            if (!$this->isActive($params)) {
                $this->output("{$leader1_user->id}来自{$user->id}充值不符合[{$activity->title}] 规则");
                continue;
            }
            $this->sendActive($params);
        }
        $job->delete(); //删除
    }

    //注册活动
    public function type4(Job $job, $data){
        if (empty($data['user_id'])) {
            $job->delete(); //删除
            return;
        }
        $user = UserModel::where('id', $data['user_id'])->find();
        if (empty($user)) {
            $this->output("{$data['user_id']}用户信息不存在");
            $job->delete(); //删除
            return;
        }
        $list = $this->getActivitys(4);

        if (empty($list)) {
            $job->delete(); //删除
            return;
        }
        foreach ($list as $activity) {
            $params = [
                'activity' => $activity,
                'user' => $user,
            ];
            if (!$this->isActive($params)) {
                $this->output("{$user->id}不符合[{$activity->title}] 规则");
                continue;
            }
            $this->sendActive($params);
        }
        $job->delete(); //删除
    }


    //转盘抽奖活动
    public function type6(Job $job, $data){
        if (empty($data['user_id'])) {
            $job->delete(); //删除
            return;
        }
        $user = UserModel::where('id', $data['user_id'])->find();
        if (empty($user)) {
            $this->output("{$data['user_id']}用户信息不存在");
            $job->delete(); //删除
            return;
        }
        $trigger_type = isset($data['trigger_type']) ? $data['trigger_type'] : 0;
        $from_user_id = isset($data['from_user_id']) ? $data['from_user_id'] : 0;
        $recharge_id = isset($data['recharge_id']) ? $data['recharge_id'] : 0;
        $vip_id = isset($data['vip_id']) ? $data['vip_id'] : 0;
        $recharge = null;
        if ($recharge_id > 0) {
            $recharge = RechargeModel::where('id', $data['recharge_id'])->where('status', 2)->find();
            if (empty($recharge)) {
                $this->output("{$data['recharge_id']}充值记录不存在");
                $job->delete(); //删除
                return;
            }
        }
        $list = $this->getActivitys(6);

        if (empty($list)) {
            $job->delete(); //删除
            return;
        }
        foreach ($list as $activity) {
            $params = [
                'activity' => $activity,
                'recharge' => $recharge,
                'vip_id' => $vip_id,
                'user' => $user,
                'trigger_type' => $trigger_type,
                'from_user_id' => $from_user_id
            ];
            if (!$this->isActive($params)) {
                $this->output("{$user->id}不符合[{$activity->title}] 规则");
                continue;
            }
            $this->sendActive($params);
        }
        $job->delete(); //删除
    }

    private function output($msg) {
        echo $msg . ";,时间:" . timeToStr() . "\n";
    }
    public function failed($data){
    }

}
