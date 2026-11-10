<?php

namespace app\api\controller;

use app\admin\model\UserModel;
use app\command\NewTeamActiveReward;
use app\common\model\HomeUserVirtualModel;
use app\common\model\MoneyLogModel;
use app\common\model\RecommendModel;
use app\common\model\TaskSummaryModel;
use app\common\model\TaskSummaryV2Model;
use app\common\model\TeamActiveRewardModel;
use app\common\model\TeamActiveRewardV2Model;
use think\Model;
use think\response\Json;
use util\Locker;

class TeamActiveReward extends Base
{
    public function index(): Json
    {
        $user_id = $this->userid;
        // 获取团队活跃奖励配置和完成人数
        $recommendModel = new RecommendModel();
        $recommend_config = $recommendModel->field('level1, level2, level3, level4')->where('id', 1)->find();
        $recommend_level_config = $recommend_config->toArray();
        $data = [];

        if ($this->userInfo['is_xuni'] == 1 && $this->userInfo['show_virtual_data'] == 1) {
            $virtual_data = HomeUserVirtualModel::getUserVirtual($user_id);
            $team_active_reward = $virtual_data['team_active_reward'];
            foreach ($recommend_level_config as $key => $config) {
                $config_arr = explode('_', $config);
                $data[$key] = [
                    'total_reward' => $config_arr[3], // 可以领取次数
                    'reward_count' => $team_active_reward[$key]['reward_count'], // 已领取次数
                    'can_reward' => $team_active_reward[$key]['can_reward'],
                    'ratio' => sprintf4f($team_active_reward[$key]['reward_count'] / $config_arr[3] * 100, 2, true)
                ];
            }
        } else {
            foreach ($recommend_level_config as $key => $config) {
                $config_arr = explode('_', $config);
                // 获取进度
                $reward_count = TeamActiveRewardModel::field('user_id, status')
                    ->where('reward_level', $key)
                    ->where('user_id', $user_id)
                    ->count();
                // 获取未领取记录
                $reward = TeamActiveRewardModel::field('user_id, status')
                    ->where('reward_level', $key)
                    ->where('user_id', $user_id)
                    ->where('status', 0)
                    ->count();
                $data[$key] = [
                    'total_reward' => $config_arr[3], // 可以领取次数
                    'reward_count' => $reward_count, // 已领取次数
                    'can_reward' => $reward,
                    'ratio' => sprintf4f($reward_count / $config_arr[3] * 100, 2, true)
                ];
            }
        }
        return json(msg(0, $data, ''));
    }

    public function indexV2(): Json
    {
        $user_id = $this->userid;
//        (new TeamActiveRewardV2Model())->summaryActiveNow($user_id);
        // 获取团队活跃奖励配置和完成人数
        $teamActiveRewardV2Model = new TeamActiveRewardV2Model();
        $config1 = $teamActiveRewardV2Model->getConfig(1);
        $config2 = $teamActiveRewardV2Model->getConfig(2);
        $config2Map = [];
        if (empty($config1) || empty($config2)) {
            return json(msg(0, '', lang('团队活跃配置不全，请检查。')));
        }
        $dataTypeOne = [];
        if ($this->userInfo['is_xuni'] == 1 && $this->userInfo['show_virtual_data'] == 1) {
            $virtual_data = HomeUserVirtualModel::getUserVirtual($user_id);
            $dataTypeTwo['is_achieve'] = false;
            $dataTypeTwo['sum_task'] = 2;
            $dataTypeTwo['leader1_user'] = 0;// 直推用户数
            $dataTypeTwo['team_active_reward'] = [];
            $dataTypeTwo['config'] = $config1[0];
            $dataTypeTwo['status'] = -1;//默认待完成
        } else {
            // 得到总的佣金
            $receive_count1 = TeamActiveRewardV2Model::where('user_id', $user_id)
                ->where('status', 1)
                ->where("reward_level", 1)
                ->count();

            foreach ($config2 as $config) {
                $config2Map[$config['phase']] = $config;
            }
            $sum_task = TaskSummaryV2Model::field('user_id,  sum(task_level1 + task_level2 ) sum_task') //得到活跃用户数据
            ->where('user_id', $user_id)
                ->where("date", date('Ymd'))
                ->find();
            // 得到最新的一条最新的未领取的奖励

            $last_reward_data = TeamActiveRewardV2Model::where("reward_level", 1)
                ->where('user_id', $user_id)
                ->where('status', 0)
                ->order('id asc')
                ->find();
            if (empty($last_reward_data)) {
                $last_reward_data = TeamActiveRewardV2Model::where("reward_level", 1)
                    ->where('user_id', $user_id)
                    ->order('id desc')
                    ->find();
            }

            // 构建配置
            $config = [
                'phase' => $last_reward_data ? $last_reward_data['phase'] : 1,
                'direct_referral_num' => $last_reward_data ? $last_reward_data['trigger_level1_num'] : $config1['base_level1_user'],
                'active_num' => $last_reward_data ? $last_reward_data['trigger_active_num'] : $config1['base_active_user'],
                'amount' => 0,
                'recharge_money' => $config1['level1_recharge_money']
            ];
            $reward_amount_step = $config1['reward_amount_step'];
            $reward_amount_step_arr = [];
            if (strpos($reward_amount_step, '-')) {
                $reward_amount_step_arr = explode('-', $reward_amount_step);
            } else {
                for ($i = 0; $i < $config1['phase_count']; $i++) {
                    $reward_amount_step_arr[] = intval($reward_amount_step);
                }
            }
            $reward_amount_step_amount = isset($reward_amount_step_arr[$config['phase']-1]) ? $reward_amount_step_arr[$config['phase']-1] : 0;
            //得到奖励金额
            $reward_amount = $config1['reward_amount'] + $reward_amount_step_amount;
            $config['amount'] = $reward_amount;

            $leader1_user = \app\common\model\UserModel::getLevel1ActiveUserNumForRecharge($user_id, $config['recharge_money']);//得到当前阶段的直推用户
//            $today_leader1_user = \app\common\model\UserModel::getLevel1ActiveUserNumForRecharge($user_id, $config['recharge_money'], true);
            if (empty($sum_task)) {
                $sum_task_new = 0;//得到活跃用户数(需要排除当天的有效直推）
            } else {
                $sum_task_new = $sum_task['sum_task'] ? $sum_task['sum_task'] : 0;//得到活跃用户数(需要排除当天的有效直推）
            }

            $dataTypeOne['is_achieve'] = false;
            $dataTypeOne['active_num'] = $sum_task_new;// 活跃用户数
            $dataTypeOne['direct_referral_num'] = $leader1_user;// 直推用户数
            $dataTypeOne['team_active_reward'] = [];
            $dataTypeOne['status'] = -1;//默认待完成
            $dataTypeOne['config'] = $config;
            $dataTypeOne['receive_count'] = $receive_count1;// 领取次数
            $dataTypeOne['total_phase'] = $config1['phase_count'];// 总阶段数

            if ($last_reward_data && $last_reward_data['phase'] <= 10) {
                $dataTypeOne['active_num'] = $sum_task_new == 0 ? $last_reward_data['sum_active'] : $sum_task_new;
                if ($last_reward_data['status'] == 0) { //如果存在未领取的记录则返回当前记录
                    $dataTypeOne['team_active_reward'] = $last_reward_data;
                    $dataTypeOne['is_achieve'] = true;
                    $dataTypeOne['status'] = $last_reward_data['status'];//0待领取，1已领取，-1待完成

                    if ($last_reward_data['trigger_type'] == TeamActiveRewardV2Model::TRIGGER_LEVEL1) {
                        $config['direct_referral_num'] = $last_reward_data['trigger_level1_num'] - $config1['level1_user_up_step']; //未领取的情况
                        $config['active_num'] = $last_reward_data['trigger_active_num'];
                    } else {
                        $config['active_num'] = $last_reward_data['trigger_active_num'] - $config1['active_user_up_step'];
                        $config['direct_referral_num'] = $last_reward_data['trigger_level1_num'];
                    }

                } else if ($last_reward_data['status'] == 1) {
                    $config['phase'] = $last_reward_data['phase'] + 1;

                    $reward_amount_step_amount = isset($reward_amount_step_arr[$config['phase']-1]) ? $reward_amount_step_arr[$config['phase']-1] : 0;
                    //得到奖励金额
                    $reward_amount = $config1['reward_amount'] + $reward_amount_step_amount;
                    $config['amount'] = $reward_amount;

                    $config['active_num'] = $last_reward_data['trigger_active_num'];
                    $config['direct_referral_num'] = $last_reward_data['trigger_level1_num'];
                    // 已完成

                    if ($config1['phase_count'] <= $last_reward_data['phase']) {
                        if ($last_reward_data['trigger_type'] == TeamActiveRewardV2Model::TRIGGER_LEVEL1) {
                            $config['active_num'] -= $config1['level1_user_up_step'];
                        } else {
                            $config['direct_referral_num'] -= $config1['active_user_up_step'];
                        }
                    }
                }
                $dataTypeOne['config'] = $config;
            }

            $dataTypeTwo = [];
            // 第一阶段领取数据
            $type1Count = TeamActiveRewardV2Model::where("user_id", $user_id)
                ->where("status", 1)
                ->where("reward_level", 1)
                ->count();
            // 排查每一个阶段
            $leader1_user = $sum_task = 0;
            if ($type1Count == intval($config1['phase_count'])) {
                $sum_task = TaskSummaryV2Model::field('user_id,  sum(task_level1 + task_level2 ) sum_task') //得到活跃用户数据
                ->where('user_id', $user_id)
                    ->where("date", date('Ymd'))
                    ->find();
            }
            $sum_task_new = 0;
            foreach ($config2 as $key => $config) {
                //初始活跃已经完成
                if ($type1Count == intval($config1['phase_count'])) {
                    $leader1_user = \app\common\model\UserModel::getLevel1ActiveUserNumForRecharge($user_id, intval($config['recharge_money']));//得到当前阶段的直推用户
//                    $today_leader_user = \app\common\model\UserModel::getLevel1ActiveUserNumForRecharge($user_id, intval($config['recharge_money']), true);
                    $sum_task_new = $sum_task['sum_task'] ? $sum_task['sum_task'] : 0;//得到活跃用户数(需要排除当天的有效直推）
                    // var_dump($leader1_user,$today_leader_user);
                }
                $reward_data = TeamActiveRewardV2Model::where("reward_level", 2)
                    ->where('user_id', $user_id)
                    ->where('phase', $config['phase'])
                    ->find();
                $dataTypeTwo['active_num'] = $sum_task_new;
                $dataTypeTwo['direct_referral_num'] = $leader1_user;// 直推用户数
                $dataTypeTwo['is_achieve'] = false;
                $dataTypeTwo['status'] = -1;//默认待完成
                if (empty($reward_data)) {
                    //没有领取的记录
                    $dataTypeTwo['config'] = $config;
                    $dataTypeTwo['is_achieve'] = false;
                    $dataTypeTwo['status'] = -1;//默认待完成
                    break;
                } else {
                    if ($reward_data['status'] == 0) { //如果存在未领取的记录则返回当前记录
                        $dataTypeTwo['team_active_reward'] = $reward_data;
                        $dataTypeTwo['is_achieve'] = true;
                        $dataTypeTwo['status'] = $reward_data['status'];//0待领取，1已领取，-1待完成
                        $index_temp = $reward_data['phase'];
                        if (isset($config2Map[$index_temp])) {
                            $dataTypeTwo['config'] = $config2Map[$index_temp];
                        }
                        break;
                    } else if ($reward_data['status'] == 1) {
                        $phase = $reward_data['phase'];
                        $index_temp = $phase + 1;
                        if (isset($config2Map[$index_temp])) {
                            $dataTypeTwo['config'] = $config2Map[$index_temp];
                        } else {
                            $dataTypeTwo['config'] = $config2Map[$phase];
                        }
                        $configIndex = $config['phase'] + 1;
                        if (isset($config2Map[$configIndex])) {
                            $dataTypeTwo['config'] = $config2Map[$configIndex];
                        }
                    }
                }
//                $dataTypeTwo['status'] = -1;//默认待完成
//                $dataTypeTwo['receive_count'] = $receive_count2;
//                $dataTypeTwo['total_phase'] = $config['step_length'];// 总阶段数
//                if (!empty($reward_data)) {
//                    if ($reward_data['status'] == 0) { //如果存在未领取的记录则返回当前记录
//                        $dataTypeTwo['team_active_reward'] = $reward_data;
//                        $dataTypeTwo['is_achieve'] = true;
//                        $dataTypeTwo['status'] = $reward_data['status'];//0待领取，1已领取，-1待完成
//                        break;
//                    } else if ($reward_data['status'] == 1) {
//                        $configIndex = $config['phase'] + 1;
//                        if (isset($config2Map[$configIndex])) {
//                            $dataTypeTwo['config'] = $config2Map[$configIndex];
//                        }
//                    }
//
//                } else { //如果不存在未领取的记录则判断是否完成
//                    break;
//                }
            }
        }
        $configList = $teamActiveRewardV2Model->getConfig(2, false);
        foreach ($configList as $key => &$config_temp) {
            if ($dataTypeTwo['config']['index'] > $key) {
                $step_temp = end($config_temp['step']);
                $configList[$key]['step'] = $step_temp;
            }
            if ($dataTypeTwo['config']['index'] == $key) {
                $configList[$key]['step'] = $dataTypeTwo['config'];
            }
            if ($dataTypeTwo['config']['index'] < $key) {
                $step_temp = reset($config_temp['step']);
                $configList[$key]['step'] = $step_temp;
            }
            //当前阶段待领取
            $reward_data = TeamActiveRewardV2Model::where("reward_level", 2)
                ->where('user_id', $user_id)
                ->where('status', 0)
                ->where('index', $key)
                ->select();
            if (!empty($reward_data)) {
                $reward_data = $reward_data->toArray();
            }
            //当前阶段已领取
            $receive_count2 = TeamActiveRewardV2Model::where('user_id', $user_id)
                ->where('status', 1)
                ->where("reward_level", 2)
                ->where("index", $key)
                ->count();
            $configList[$key]['team_active_reward'] = $reward_data;
            $configList[$key]['receive_count'] = $receive_count2;
            $configList[$key]['total_phase'] = $config_temp['step_length'];
        }
        return json(msg(0, [
            'initialTeam' => $dataTypeOne,
            'growthTeam' => $dataTypeTwo,
            'configList' => $configList,
            'config' => config('app.activity_start_time')
        ], ''));
    }

    /**
     * 领取奖励
     */
    public function getRewardV2(): Json
    {
        $user_id = $this->userid;
        $reward_level = $this->request->param('reward_level');
        $reward_id = $this->request->param('reward_id');

        (new Locker('get_new_team_active_reward_lock_' . $user_id))->lock();

        // 获取未领取记录
        $reward = TeamActiveRewardV2Model::where('user_id', $user_id)
            ->where('status', 0)
            ->where('reward_level', $reward_level)
            ->where('id', $reward_id)
            ->find();
        if (empty($reward)) {
            return json(msg(-1, '', lang('暂无奖励可领取')));
        }
        $money_log_model = new MoneyLogModel();
        $reward_amount = $reward->reward_amount;
        //当日直推奖励
        $title = self::$level_name_arr[$reward->reward_level];
        $remarks = self::$level_name_arr[$reward->reward_level] . ':' . $reward_amount;
        $change = "add";
        $type = MoneyLogModel::TYPE_BROKERAGE;
        $money_log_model->changeMoney($user_id, floatval($reward_amount), $type, $change, $title, $remarks, 27);
        $reward->status = 1;
        $reward->receive_time = time();
        $reward->save();
        return json(msg(0, '', lang('领取成功')));
    }

    /**
     * 领取奖励
     */
    public function getReward(): Json
    {
        $user_id = $this->userid;
        $reward_level = $this->request->param('reward_level');
        (new Locker('get_team_active_reward_lock_' . $user_id))->lock();

        // 获取未领取记录
        $reward = TeamActiveRewardModel::where('user_id', $user_id)
            ->where('status', 0)
            ->where('reward_level', $reward_level)
            ->select();
        if (!$reward) {
            return json(msg(-1, '', lang('暂无奖励可领取')));
        }
        $money_log_model = new MoneyLogModel();
        foreach ($reward as $item) {
            $reward_amount = $item->reward_amount;
            //当日直推奖励
            $title = self::$level_name_arr[$item->reward_level] . $item['reward_times'];
            $remarks = self::$level_name_arr[$item->reward_level] . $item['reward_times'] . ':' . $reward_amount;
            $change = "add";
            $type = MoneyLogModel::TYPE_BROKERAGE;
            $money_log_model->changeMoney($user_id, floatval($reward_amount), $type, $change, $title, $remarks, 19);
            $item->status = 1;
            $item->save();
        }
        return json(msg(0, '', lang('领取成功')));
    }

    static array $level_name_arr = [
        '1' => '初始团队活跃奖',
        '2' => '成长团队活跃奖',
        '3' => '杰出团队活跃奖',
        '4' => '精英团队活跃奖',
    ];
}
