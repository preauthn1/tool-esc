<?php

namespace app\common\model;

use think\Model;
use think\db\Query;

/**
 * 用户广告计划天数配置模型
 */
class AdPlanUserDaysConfigModel extends Model
{
    protected $table = 'qd_ad_plan_user_days_config';

    // 开启自动写入时间戳
    protected $autoWriteTimestamp = false;

    /**
     * 关联用户模型
     */
    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    /**
     * 关联广告计划模型
     */
    public function adPlan()
    {
        return $this->belongsTo(AdPlanModel::class, 'ad_plan_id');
    }

    /**
     * 获取用户的广告计划配置
     *
     * @param  int  $userId  用户ID
     * @param  int  $adPlanId  广告计划ID
     * @return array 配置数据
     */
    public static function getUserConfig($userId, $adPlanId)
    {
        $configs = self::where('user_id', $userId)
            ->where('ad_plan_id', $adPlanId)
            ->select();

        // 如果没有用户特定配置，则返回空数组
        if (empty($configs)) {
            return [];
        }

        // 将数据组织成与AdPlanModel相同的结构，便于在表单中显示
        $daysConfig = [];
        foreach ($configs as $config) {
            $daysConfig[$config['day']][$config['task_session_id']][$config['level_id']] = [
                'invest_rate'        => $config['invest_rate'],
                'invest_profit'      => $config['invest_profit'],
                'invest_period_type' => $config['invest_period_type'],
                'invest_day'         => $config['invest_day'],
            ];
        }

        return $daysConfig;
    }

    /**
     * 保存用户的广告计划配置
     *
     * @param  int  $userId  用户ID
     * @param  int  $adPlanId  广告计划ID
     * @param  array  $daysConfig  天数配置数据
     * @param  string  $applyScope  应用范围：'personal'(仅个人) 或 'team'(个人及代理线)
     * @return bool 是否保存成功
     */
    public static function saveUserConfig($userId, $adPlanId, $daysConfig, $applyScope = 'personal')
    {
        // 开始事务
        self::startTrans();

        try {
            $userIds = [$userId]; // 默认只包含当前用户

            // 如果是团队应用范围，则查询所有代理线用户
            if ($applyScope === 'team') {
                // 使用单一条件可能会导致漏掉一些情况，分开处理更准确
                // 例如: 单一条件 "%/$userId/%" 无法匹配开头为userId的 "123/456/789" 或结尾为userId的 "456/789/123"
                $teamUsers = UserModel::where('tree_id', 'like', "%/{$userId}/%")
                    ->where('tree_id', 'not like', "%/$userId/")
                    ->column('id');

                if (!empty($teamUsers)) {
                    $userIds = array_merge($userIds, $teamUsers);
                }
            }

            // 为每个用户保存配置
            foreach ($userIds as $uid) {
                // 先删除该用户该计划的所有现有配置
                self::where('user_id', $uid)
                    ->where('ad_plan_id', $adPlanId)
                    ->delete();

                // 插入新的配置
                $insertData = [];
                foreach ($daysConfig as $day => $timeSlots) {
                    foreach ($timeSlots as $timeSlotId => $levels) {
                        foreach ($levels as $levelId => $config) {
                            // 检查是否存在投资率和收益率值
                            $investRate = isset($config['invest_rate']) && $config['invest_rate'] !== '' ?
                                $config['invest_rate'] : 0;
                            $investProfit = isset($config['invest_profit']) && $config['invest_profit'] !== '' ?
                                $config['invest_profit'] : 0;
                            $invest_period_type = isset($config['invest_period_type']) && $config['invest_period_type'] !== '' ?
                                $config['invest_period_type'] : 'day';
                            $invest_day = isset($config['invest_day']) && $config['invest_day'] !== '' ?
                                $config['invest_day'] : 0;

                            // 只有当投资率和收益率不为空时才添加记录
                            if ($investRate > 0 || $investProfit > 0) {
                                $insertData[] = [
                                    'user_id'            => $uid,
                                    'ad_plan_id'         => $adPlanId,
                                    'day'                => $day,
                                    'task_session_id'    => $timeSlotId,
                                    'level_id'           => $levelId,
                                    'invest_rate'        => $investRate,
                                    'invest_profit'      => $investProfit,
                                    'invest_period_type' => $invest_period_type,
                                    'invest_day'         => $invest_day,
                                ];
                            }
                        }
                    }
                }

                // 批量插入数据
                if (!empty($insertData)) {
                    self::insertAll($insertData);
                }
            }

            // 提交事务
            self::commit();
            return true;
        } catch (\Exception $e) {
            // 回滚事务
            self::rollback();
            return false;
        }
    }

    /**
     * 检查用户是否有特定配置
     *
     * @param  int  $userId  用户ID
     * @param  int  $adPlanId  广告计划ID
     * @return bool 是否有特定配置
     */
    public static function hasUserConfig($userId, $adPlanId)
    {
        return self::where('user_id', $userId)
                ->where('ad_plan_id', $adPlanId)
                ->count() > 0;
    }
}
