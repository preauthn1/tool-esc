<?php

namespace app\admin\controller;

use app\admin\model\LevelModel;
use app\common\model\AdPlanModel;
use app\common\model\AdPlanDaysConfig;
use app\common\model\TaskSessionModel;
use app\common\model\AdPlanUserModel;
use app\common\model\AdPlanUserDaysConfigModel;
use app\common\model\UserModel;
use app\common\repository\LevelRepository;
use think\facade\View;
use think\response\Json;

class AdPlan extends Base
{
    public function index()
    {
        // Now just render the view without passing data
        return view('index');
    }

    public function getList(): Json
    {
        // Get all plans with their related next plan, ordered by sort field
        $adPlans = AdPlanModel::with(['nextPlan'])->order(['sort' => 'asc'])->select();

        // Transform data to include next plan name
        $formattedData = [];
        foreach ($adPlans as $plan) {
            $item = $plan->toArray();
            // Replace next_id with the name of the next plan
            $item['next_plan'] = $plan->nextPlan ? $plan->nextPlan->name : '-';
            $formattedData[] = $item;
        }

        return json([
            'code' => 0, // Layui expects 0 for success
            'msg' => '',
            'count' => count($formattedData),
            'data' => $formattedData
        ]);
    }

    public function edit()
    {
        $id = $this->request->param('id/d', 0);
        $userId = $this->request->param('user_id/d', 0);

        // 获取计划信息
        if ($id) {
            // 编辑现有计划
            $adPlan = AdPlanModel::find($id);
        } else {
            // 新建计划 - 仅初始化模型实例，不保存到数据库
            $adPlan = new AdPlanModel([
                'id' => 0, // 设置一个临时ID值，避免模板中访问不存在的属性
                'name' => '',
                'period' => 1,
                'sort' => 100,
                'status' => 0,
                'day_count' => 1,
                'next_id' => 1,
                'days_config' => json_encode([])
            ]);
        }

        // 如果传入了用户ID，获取用户信息
        $user = null;
        if ($userId) {
            $user = UserModel::find($userId);
            // 如果用户存在且没有指定广告计划ID，则使用用户当前的广告计划
            if ($user && !$id) {
                $id = $user->ad_plan_id;
                if ($id) {
                    $adPlan = AdPlanModel::find($id);
                }
            }

            // 检查用户是否有特定配置
            $userConfig = AdPlanUserDaysConfigModel::getUserConfig($userId, $id);
            if (!empty($userConfig)) {
                // 用户有特定配置，使用用户配置
                // 但是需要确保配置格式正确
                if (is_string($userConfig)) {
                    // 如果是JSON字符串，需要解码
                    $userConfig = json_decode($userConfig, true);
                }

                // 清空原有的days_config属性再赋值
                $adPlan->days_config = $userConfig;

                // 更新天数计数，确保UI能正确显示所有配置天数
                $dayCount = !empty($userConfig) ? max(array_keys($userConfig)) : 0;
                $adPlan->day_count = $dayCount + 1; // 因为天数从1开始，所以+1

                // 标记已加载用户配置，避免被通用配置覆盖
                $hasUserConfig = true;
            } else {
                $hasUserConfig = false;
            }
        }

        if ($this->request->isPost()) {
            $params = $this->request->post();

            // 默认转换为通用配置
            if (isset($params['days_config']) && is_array($params['days_config'])) {
                $params['day_count'] = count($params['days_config']);
            }

            // 如果是用户特定配置，保存到用户配置表
            if ($userId && isset($params['days_config'])) {
                $applyScope = isset($params['apply_scope']) ? $params['apply_scope'] : 'personal';

                $result = AdPlanUserDaysConfigModel::saveUserConfig(
                    $userId,
                    $id,
                    $params['days_config'],
                    $applyScope // 传递生效范围参数
                );

                if ($result) {
                    $message = $applyScope == 'team' ? '保存用户及代理线配置成功' : '保存用户特定配置成功';
                    $this->success($message);
                } else {
                    $this->error('保存用户特定配置失败');
                }
            } else {
                // 保存通用广告计划配置
                if (!isset($params['status'])) {
                    $params['status'] = 0;
                }

                if ($id) {
                    // 编辑现有记录
                    $adPlan->save($params);
                } else {
                    // 新增记录
                    $adPlan->save($params);
                    $id = $adPlan->id; // 获取新插入记录的ID
                }

                // 删除并重新创建天数配置
                if ($id) {
                    AdPlanDaysConfig::startTrans();
                    try {
                        AdPlanDaysConfig::where('ad_plan_id', $id)->delete();
                        if (isset($params['days_config'])) {
                            foreach ($params['days_config'] as $day => $timeSlots) {
                                // 这里不需要修改，因为表单中的day索引已经是从1开始
                                // day值直接来自表单，不需要加1或减1
                                foreach ($timeSlots as $timeSlotId => $slotTypes) {
                                    foreach ($slotTypes as $slotTypeId => $config) {
                                        $periodConfig = [
                                            'ad_plan_id' => $id,
                                            'day' => $day, // day已经是从1开始的
                                            'task_session_id' => $timeSlotId,
                                            'level_id' => $slotTypeId, // 将 type 改为 level_id
                                            'invest_rate' => $config['invest_rate'],
                                            'invest_profit' => $config['invest_profit'],
                                            'invest_period_type' => $config['invest_period_type'],
                                            'invest_day' => $config['invest_day'],
                                        ];
                                        AdPlanDaysConfig::create($periodConfig);
                                    }
                                }
                            }
                        }
                        AdPlanDaysConfig::commit();
                    } catch (\Exception $e) {
                        AdPlanDaysConfig::rollback();
                    }

                }

                return json(['code' => 1, 'msg' => $id ? '编辑成功' : '添加成功']);
            }
        }

        // 只有在没有用户特定配置的情况下，才加载通用计划配置
        if (empty($hasUserConfig)) {
            // 获取计划配置
            $periodConfigs = AdPlanDaysConfig::where('ad_plan_id', $id)->select();

            // 获取所有配置的天数
            $allDays = [];
            foreach ($periodConfigs as $config) {
                $allDays[$config['day']] = true;
            }
            $dayCount = !empty($allDays) ? max(array_keys($allDays)) + 1 : 0;

            // 重组天数配置数据
            $days_config = [];
            foreach ($periodConfigs as $config) {
                $days_config[$config['day']][$config['task_session_id']][$config['level_id']] = [
                    'invest_rate' => $config['invest_rate'],
                    'invest_profit' => $config['invest_profit'],
                    'invest_period_type' => $config['invest_period_type'],
                    'invest_day' => $config['invest_day'],
                ];
            }

            $adPlan->days_config = $days_config;
            $adPlan->day_count = $dayCount; // 添加天数计数，用于模板循环
        }

        $levels = LevelModel::where('type', 'in', [LevelRepository::TYPE_NORMAL, LevelRepository::TYPE_PROMOTION])
            ->order(['level_id' => 'desc'])
            ->select(); // Fetch ad slot types
        $timeSlots = TaskSessionModel::where('status', 1)
            ->order('start_time')
            ->select(); // Fetch active time slots

        // 获取所有计划作为下拉选项，但排除当前正在编辑的计划
        if ($id) {
            $plans = AdPlanModel::where('id', '<>', $id)->select();
        } else {
            $plans = AdPlanModel::select();
        }

        // 添加用户信息到视图
        if ($user) {
            View::assign('user', $user);
        }
        return view('edit', [
            'userId' => $userId,
            'adPlan' => $adPlan,
            'levels' => $levels,
            'timeSlots' => $timeSlots,
            'plans' => $plans // Pass plans to view
        ]);
    }

    public function delete($id)
    {
        // Check if the plan is enabled (status = 1)
        $adPlan = AdPlanModel::find($id);
        if (!$adPlan) {
            return json(['code' => 0, 'msg' => '计划不存在']);
        }

        if ($adPlan->status == 1) {
            return json(['code' => 0, 'msg' => '启用状态的计划不允许删除']);
        }

        // If status is not 1, proceed with deletion
        AdPlanModel::destroy($id);
        return json(['code' => 1, 'msg' => '删除成功']);
    }

    public function changeStatus()
    {
        if (request()->isPost()) {
            $id = input('post.id');
            $status = input('post.status');

            $adPlan = AdPlanModel::find($id);
            if ($adPlan) {
                $adPlan->status = $status;
                $adPlan->save();
                return json(['code' => 1, 'msg' => '状态更新成功']);
            }
            return json(['code' => 0, 'msg' => '计划不存在']);
        }
    }

    /**
     * Record a user joining or switching ad plans
     */
    public function recordUserPlan()
    {
        if (request()->isPost()) {
            $userId = input('post.user_id');
            $adPlanId = input('post.ad_plan_id');
            $previousPlanId = input('post.previous_plan_id', null);

            if (!$userId || !$adPlanId) {
                return json(['code' => 0, 'msg' => '参数不完整']);
            }

            // Check if ad plan exists
            $adPlan = AdPlanModel::find($adPlanId);
            if (!$adPlan) {
                return json(['code' => 0, 'msg' => '广告计划不存在']);
            }

            // Record the plan change
            $result = AdPlanUserModel::recordPlanChange($userId, $adPlanId, $previousPlanId);

            if ($result) {
                return json(['code' => 1, 'msg' => $previousPlanId ? '计划切换记录成功' : '计划加入记录成功']);
            } else {
                return json(['code' => 0, 'msg' => '记录失败']);
            }
        }

        return json(['code' => 0, 'msg' => '请求方式错误']);
    }

    /**
     * Get user ad plan history
     */
    public function getUserPlanHistory($userId)
    {
        if (!$userId) {
            return json(['code' => 0, 'msg' => '用户ID不能为空']);
        }

        $history = AdPlanUserModel::with(['adPlan', 'previousPlan'])
            ->where('user_id', $userId)
            ->order('id', 'desc')
            ->select();

        return json([
            'code' => 1,
            'msg' => '',
            'data' => $history
        ]);
    }
}
