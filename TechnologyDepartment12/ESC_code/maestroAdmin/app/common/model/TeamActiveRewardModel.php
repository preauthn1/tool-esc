<?php

namespace app\common\model;

use think\console\output\Ask;
use think\facade\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Model;

class TeamActiveRewardModel extends Model
{

    protected $autoWriteTimestamp = true;
    // 确定链接表名
    protected $name = 'team_active_reward';

    // 统计活跃用户
    public function summaryActive()
    {
        $date = date('Ymd');
        print_r($date."\n");
        print_r("统计团队活跃数据\n");
        $user_task = TaskSummaryModel::field('user_id, task_level1 level1_active, task_level2 level2_active, task_level3 level3_active, sum(task_level1 + task_level2 + task_level3) sum_task')
            ->where('date', $date)
            ->group('user_id')
            ->select();
        // 获取奖励配置
        $recommendModel = new RecommendModel();
        $recommend_config = $recommendModel->field('level1, level2, level3, level4')->where('id', 1)->find();
        $recommend_level_config = $recommend_config->toArray();
        foreach ($user_task as $task) {
            foreach ($recommend_level_config as $level => $config) {
                // 获取用户该等级的最新奖励
                $team_active_reward = TeamActiveRewardModel::where('user_id', $task['user_id'])
                    ->where('reward_level', $level)
                    ->order('reward_times desc')
                    ->find();

                // dd($reward_times);
                $config_arr = explode('_', $config);
                // 0 - register 1-sum_task 2-
                if (!$team_active_reward) {
                    $this->firstTimes($level, $task, $config_arr);
                } else {
                    if ($level == 'level1') {
                        $this->otherTimesLevel1($level, $task, $config_arr, $team_active_reward);
                    } else {
                        $this->otherTimes($level, $task, $config_arr, $team_active_reward);
                    }
                }
            }
        }
    }

    private function otherTimesLevel1($level, $task, $config_arr, $team_active_reward)
    {
        if ($team_active_reward->reward_times >= $config_arr[3]) { // 已经领取完了
            return;
        }

        $active_user = UserModel::getLevel1ActiveUserNum($task['user_id']);
        {
            $last_active_number = $team_active_reward->sum_active;
            if (!$last_active_number) {
                $last_active_number = $team_active_reward->level1_active + $team_active_reward->level2_active + $team_active_reward->level3_active;
            }
            // 先判断新增人数奖励
            $new_active_user = $task['sum_task'] - $last_active_number; // 新增活跃人数
            $new_user = $active_user - $team_active_reward->level1_user; // 新增直推人数
            if ($new_user >= 1) {
                $times = min($new_user, $new_active_user);
                for ($i = 1; $i <= $times; $i++) {
                    $new_reward_times = $team_active_reward->reward_times + $i;
                    if ($new_reward_times > $config_arr[3]) {
                        break;
                    }
                    $reward_amount = $config_arr[2] + ($config_arr[6] * ($new_reward_times - 1));
                    $sum_active = $last_active_number + $i;
                    TeamActiveRewardModel::create([
                        'user_id'       => $task['user_id'],
                        'level1_user'   => $active_user,
                        'level1_active' => $task['level1_active'],
                        'level2_active' => $task['level2_active'],
                        'level3_active' => $task['level3_active'],
                        'sum_active'    => $sum_active,
                        'from_type'     => 1,
                        'reward_level'  => $level,
                        'reward_amount' => $reward_amount,
                        'status'        => 0,
                        'reward_times'  => $new_reward_times,
                    ]);
                }
            }
            // 获取用户该等级的最新奖励
            $team_active_reward = TeamActiveRewardModel::where('user_id', $task['user_id'])
                ->where('reward_level', $level)
                ->order('reward_times desc')
                ->find();

            // 获取 from_type = 2 的最新数据
            $type_2_team_active_reward = TeamActiveRewardModel::where('user_id', $task['user_id'])
                ->where('reward_level', $level)
                ->where('from_type', 'in', [2,3])
                ->order('reward_times desc')
                ->find();
            if ($type_2_team_active_reward) {
                $last_active_number = $type_2_team_active_reward->sum_active;
                if (!$last_active_number) {
                    $last_active_number = $type_2_team_active_reward->level1_active + $type_2_team_active_reward->level2_active + $type_2_team_active_reward->level3_active;
                    // 如果是奇数，-1
                    if ($last_active_number % 2 == 1) {
                        $last_active_number = $last_active_number - 1;
                    }
                }
            } else {
                $last_active_number = $team_active_reward->level1_active + $team_active_reward->level2_active + $team_active_reward->level3_active;
                // 如果是奇数，-1
                if ($last_active_number % 2 == 1) {
                    $last_active_number = $last_active_number - 1;
                }
            }

            if ($task['sum_task'] - $last_active_number >= $config_arr[5]) {
                $times = floor((($task['sum_task'] - $last_active_number)) / $config_arr[5]);
                for ($i = 1; $i <= $times; $i++) {
                    $new_reward_times = $team_active_reward->reward_times + $i;
                    if ($new_reward_times > $config_arr[3]) {
                        break;
                    }
                    $reward_amount = $config_arr[2] + ($config_arr[6] * ($new_reward_times - 1));
                    $sum_active = $last_active_number + $i * $config_arr[5];
                    TeamActiveRewardModel::create([
                        'user_id'       => $task['user_id'],
                        'level1_user'   => $active_user,
                        'level1_active' => $task['level1_active'],
                        'level2_active' => $task['level2_active'],
                        'level3_active' => $task['level3_active'],
                        'sum_active'    => $sum_active,
                        'from_type'     => 2,
                        'reward_level'  => $level,
                        'reward_amount' => $reward_amount,
                        'status'        => 0,
                        'reward_times'  => $new_reward_times,
                    ]);
                }
            }
        }
    }

    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     */
    private function firstTimes($level, $task, $config_arr)
    {
        $active_user = UserModel::getLevel1ActiveUserNum($task['user_id']);
        // 一级活跃小于需要的直推数，没有奖励
        if ($task['level1_active'] < $config_arr[0]) {
            return;
        }
        if ($active_user >= $config_arr[0] && $task['sum_task'] >= $config_arr[1]) {
            TeamActiveRewardModel::create([
                'user_id'       => $task['user_id'],
                'level1_user'   => $active_user,
                'level1_active' => $task['level1_active'],
                'level2_active' => $task['level2_active'],
                'level3_active' => $task['level3_active'],
                'reward_level'  => $level,
                'from_type'     => 3,
                'sum_active'    => $config_arr[1],
                'reward_amount' => $config_arr[2],
                'status'        => 0,
                'reward_times'  => 1,
            ]);
        }
    }

    /**
     * @param $level  // 奖励等级
     * @param $task  //活跃数据
     * @param $config_arr  //奖励配置
     * @param $team_active_reward  //最新奖励
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    private function otherTimes($level, $task, $config_arr, $team_active_reward)
    {
        if ($team_active_reward->reward_times >= $config_arr[3]) { // 已经领取完了
            return;
        }

        $active_user = $task['level1_active'];
        $times1 = floor(($active_user - $config_arr[0]) / $config_arr[4]); // 邀请
        $times2 = floor(($task['sum_task'] - $config_arr[1]) / $config_arr[5]); // 活跃


        // 一级活跃小于下次奖励需要的直推数，没有奖励
        if ($task['level1_active'] < $config_arr[0] + $team_active_reward->reward_times * $config_arr[4]) {
            return;
        }
        $times = min($times1, $times2);


        if ($times >= $config_arr[3]) {
            $times = $config_arr[3] - 1;
        }

        if ($team_active_reward->reward_times > 1) {
            $times = $times - ($team_active_reward->reward_times - 1);
        }

        for ($i = 1; $i <= $times; $i++) {
            $new_reward_times = $team_active_reward->reward_times + $i;
            if ($new_reward_times > $config_arr[3]) {
                break;
            }
            $reward_amount = $config_arr[2] + ($config_arr[6] * ($new_reward_times - 1));
            TeamActiveRewardModel::create([
                'user_id'       => $task['user_id'],
                'level1_user'   => $active_user,
                'level1_active' => $task['level1_active'],
                'level2_active' => $task['level2_active'],
                'level3_active' => $task['level3_active'],
                'reward_level'  => $level,
                'reward_amount' => $reward_amount,
                'status'        => 0,
                'reward_times'  => $new_reward_times,
            ]);
        }
    }
}
