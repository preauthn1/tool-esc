<?php

namespace app\common\model;

use Carbon\Carbon;
use think\facade\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Model;

class AdPlanModel extends Model
{
    protected $table = 'qd_ad_plan';
    protected $autoWriteTimestamp = true;

    // Define relation to next plan
    public function nextPlan()
    {
        return $this->belongsTo('AdPlanModel', 'next_id', 'id');
    }

    // Define relation to period config
    public function periodConfigs()
    {
        return $this->hasMany('AdPlanDaysConfig', 'ad_plan_id', 'id');
    }

    /**
     * 获取计划任务名称
     * @param $id
     * @return mixed|string
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public static function getPlanName($id)
    {
        $plan = self::where('id', $id)->field('name')->find();
        if (empty($plan)) {
            return "";
        }
        return $plan['name'];
    }

    // 获取当前用户是广告位的第几天
    public static function getDayCount($user_id, $plan_id, $is_real = false)
    {
        // 通过与加入时间的差值获取天数
        // 使用carbon计算天数差
        $userAdPlan = AdPlanUserModel::with('adPlan')
            ->where('user_id', $user_id)
            ->where('ad_plan_id', $plan_id)
            ->order('id desc')
            ->find();
        if (!$userAdPlan) {
            return 0;
        }
        $create_time = $userAdPlan->create_time;
        $day = Carbon::parse($create_time)->diffInDays() + 1;
        if ($is_real) {
            return floor($day);
        }
        return floor(min($day, $userAdPlan->ad_plan->period));
    }

    /**
     * 更新用户的广告计划等级
     * 根据用户加入时间计算应当处于的等级
     *
     * @param  int  $user_id  用户ID
     * @return int|bool 返回更新后的计划ID，失败返回false
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     * @throws PDOException
     */
    public static function updateUserPlanLevel(int $user_id)
    {
        // 获取用户当前的广告计划
        $userAdPlan = AdPlanUserModel::where('user_id', $user_id)
            ->order('id desc')
            ->find();

        // 如果用户没有广告计划，分配默认计划
        if (!$userAdPlan) {
            // 检查用户是否存在
            $user = Db::name('home_user')->where('id', $user_id)->find();
            if (!$user) {
                return false;
            }

            // 获取第一个可用的广告计划（默认计划）
            $defaultPlan = self::order('sort asc')->where('status', 1)->find();
            if (!$defaultPlan) {
                return false;
            }

            // 创建新的用户广告计划记录
            $newUserAdPlan = new AdPlanUserModel();
            $newUserAdPlan->user_id = $user_id;
            $newUserAdPlan->ad_plan_id = $defaultPlan->id;
            $newUserAdPlan->save();

            // 更新home_user表的ad_plan_id
            Db::name('home_user')->where('id', $user_id)->update(['ad_plan_id' => $defaultPlan->id]);

            return $defaultPlan->id;
        }

        // 获取当前计划
        $currentPlanId = $userAdPlan->ad_plan_id;
        $currentPlan = self::where('status', 1)->find($currentPlanId);
        if (!$currentPlan) {
            return false;
        }

        // 计算用户加入的总天数
        $create_time = $userAdPlan->create_time;
        $totalDays = ceil(Carbon::parse($create_time)->diffInDays());

        // 如果当前计划已停止，将用户的计划更新为下一个可用计划
        if ($currentPlan->status == 0) {
            $nextPlan = self::where('status', 1)->find($currentPlan->next_id);
            if ($nextPlan) {
                // 创建新的用户广告计划记录
                $newUserAdPlan = new AdPlanUserModel();
                $newUserAdPlan->user_id = $user_id;
                $newUserAdPlan->ad_plan_id = $nextPlan->id;
                $newUserAdPlan->previous_plan_id = $currentPlanId;
                $newUserAdPlan->create_time = Carbon::parse($currentPlan->update_time)->addDay()->timestamp;;
                $newUserAdPlan->save();

                // 更新home_user表的ad_plan_id
                Db::name('home_user')->where('id', $user_id)->update(['ad_plan_id' => $nextPlan->id]);

                return $nextPlan->id;
            }
        }

        // 如果天数未超过当前计划周期，无需更新
        if ($totalDays <= $currentPlan->period) {
            return $currentPlanId;
        }

        // 计算剩余天数
        $remainingDays = $totalDays;
        $planToCheck = $currentPlan;

        // 遍历计划等级，直到消耗完所有天数或没有下一级
        while ($remainingDays > $planToCheck->period) {
            // 减去当前等级的周期天数
            $remainingDays -= $planToCheck->period;

            // 如果没有下一级计划，创建一条特殊记录，ad_plan_id = 0
            if (!$planToCheck->next_id) {
                // 创建新的用户广告计划记录，ad_plan_id设为0
                $newUserAdPlan = new AdPlanUserModel();
                $newUserAdPlan->user_id = $user_id;
                $newUserAdPlan->ad_plan_id = 0;
                $newUserAdPlan->previous_plan_id = $planToCheck->id;
                if ($remainingDays > 0) {
                    $newUserAdPlan->create_time = Carbon::now()->subDays($remainingDays - 1)->timestamp;
                }
                $newUserAdPlan->save();

                // 更新home_user表的ad_plan_id
                Db::name('home_user')->where('id', $user_id)->update(['ad_plan_id' => 0]);

                return 0; // 返回特殊计划ID 0
            }

            // 获取下一级计划
            $nextPlan = self::find($planToCheck->next_id);
            if (!$nextPlan) {
                break;
            }

            // 进入下一级计划
            $planToCheck = $nextPlan;
        }

        // 如果计划等级发生变化，创建新记录并更新home_user表
        if ($planToCheck->id != $currentPlanId) {
            // 创建新的用户广告计划记录
            $newUserAdPlan = new AdPlanUserModel();
            $newUserAdPlan->user_id = $user_id;
            $newUserAdPlan->ad_plan_id = $planToCheck->id;
            $newUserAdPlan->previous_plan_id = $currentPlanId;
            if ($remainingDays > 0) {
                $newUserAdPlan->create_time = Carbon::now()->subDays($remainingDays - 1)->timestamp;
            }
            $newUserAdPlan->save();

            // 更新home_user表的ad_plan_id
            Db::name('home_user')->where('id', $user_id)->update(['ad_plan_id' => $planToCheck->id]);
        }

        return $planToCheck->id;
    }

    public static function getPlanList()
    {
        $plan = self::field('id,name')->select();
        if (empty($plan)) {
            return [];
        }
        return $plan;
    }
}
