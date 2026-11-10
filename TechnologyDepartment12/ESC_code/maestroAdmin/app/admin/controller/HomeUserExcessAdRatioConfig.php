<?php

namespace app\admin\controller;

use app\admin\model\LevelModel;
use app\common\model\HomeUserExcessAdRatioConfigModel;
use think\facade\View;

class HomeUserExcessAdRatioConfig extends Base
{
    /**
     * 列表与配置数据获取
     * Displays a list of configurations for a specific user.
     * If AJAX, returns level data with existing configs for the user.
     */
    public function index()
    {
        $user_id = $this->request->param('uid');
        if (!$user_id) {
            if ($this->request->isAjax()) {
                return json(['code' => 1, 'msg' => '用户ID不能为空', 'data' => []]);
            }
            $this->error('用户ID不能为空');
        }

        if ($this->request->isAjax()) {
            $levels = LevelModel::where('is_show', 1)->order(['sort' => 'asc'])->select();

            $levelsData = [];
            if ($levels) {
                foreach ($levels as $key => $level) {
                    $invest_num = isset($level->invest_num) ? (int) $level->invest_num : 0;
                    $config = HomeUserExcessAdRatioConfigModel::where('user_id', $user_id)
                        ->where('level_id', $level->level_id)
                        ->find();
                    $levelsData[$key] = [
                        'level_id'   => $level->level_id,
                        'level_name' => lang($level->level_name),
                        'invest_num' => $invest_num,
                    ];
                    if ($config) {
                        $levelsData[$key]['ratio'] = $config->ratio;
                        $levelsData[$key]['profit_rate'] = $config->profit_rate;
                        $levelsData[$key]['ad_cycle'] = $config->ad_cycle;
                        $levelsData[$key]['subordinate'] = $config->subordinate;
                    }
                }
            }
            return json(['code' => 0, 'msg' => '成功', 'data' => $levelsData]);
        }

        View::assign('uid', $user_id);
        return View::fetch();
    }

    /**
     * 编辑/保存配置 (AJAX POST from index.html)
     * Handles saving multiple ratio configurations for a user and level.
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();

            $user_id = $data['user_id'] ?? null;
            $level_id = $data['level_id'] ?? null;
            $subordinate = $data['subordinate'] ?? 0;
            $profit_rate_value = $data['profit_rate'] ?? '';
            $ad_cycle_value = $data['ad_cycle'] ?? '';

            if (empty($user_id) || !is_numeric($user_id)) {
                return json(['code' => 1, 'msg' => '用户ID无效']);
            }
            if (!isset($level_id) || !is_numeric($level_id)) {
                return json(['code' => 1, 'msg' => '等级ID无效']);
            }

            $level_info = LevelModel::where('level_id', $level_id)->find();
            if (!$level_info || !isset($level_info->invest_num)) {
                return json(['code' => 1, 'msg' => '无效的等级或等级缺少invest_num属性']);
            }

            try {
                HomeUserExcessAdRatioConfigModel::where('user_id', $user_id)
                    ->where('level_id', $level_id)
                    ->delete();

                $ratio_value = $data['ratio'] ?? '';
                if ($ratio_value !== '' && $ratio_value !== null) {
                    $ratios = json_decode($ratio_value, true);
                    if (!is_array($ratios)) {
                        return json(['code' => 1, 'msg' => "比例值格式无效"]);
                    }
                    foreach ($ratios as $ratio) {
                        if ($ratio !== '' && $ratio !== null && !is_numeric($ratio)) {
                            return json(['code' => 1, 'msg' => "存在无效的比例值"]);
                        }
                    }

                    // 新校验逻辑：要么全部为0，要么总和大于100
                    $totalRatio = 0;
                    $validRatioCount = 0;
                    $allZero = true;
                    foreach ($ratios as $v) {
                        if ($v !== '' && $v !== null && is_numeric($v)) {
                            $num = floatval($v);
                            $totalRatio += $num;
                            if ($num != 0) {
                                $allZero = false;
                            }
                            $validRatioCount++;
                        }
                    }
                    if ($validRatioCount > 0) {
                        if (!$allZero && $totalRatio <= 100) {
                            return json(['code' => 1, 'msg' => "超额购买的广告位比例要么全部为0，要么总和大于100%"]);
                        }
                    }

                    // 校验 profit_rate
                    if ($profit_rate_value === '' || $profit_rate_value === null) {
                        return json(['code' => 1, 'msg' => "广告位收益率不能为空"]);
                    }
                    $profit_rates = json_decode($profit_rate_value, true);
                    if (!is_array($profit_rates)) {
                        return json(['code' => 1, 'msg' => "收益率格式无效"]);
                    }
                    foreach ($profit_rates as $rate) {
                        if ($rate === '' || $rate === null) {
                            return json(['code' => 1, 'msg' => "广告位收益率不能为空"]);
                        }
                        if (!is_numeric($rate)) {
                            return json(['code' => 1, 'msg' => "存在无效的收益率"]);
                        }
                    }

                    // 校验 ad_cycle
                    if ($ad_cycle_value === '' || $ad_cycle_value === null) {
                        return json(['code' => 1, 'msg' => "广告位周期不能为空"]);
                    }
                    $ad_cycles = json_decode($ad_cycle_value, true);
                    if (!is_array($ad_cycles)) {
                        return json(['code' => 1, 'msg' => "广告位周期格式无效"]);
                    }
                    foreach ($ad_cycles as $cycle) {
                        if (is_array($cycle)) {
                            if (
                                !isset($cycle['value']) || $cycle['value'] === '' ||
                                !is_numeric($cycle['value']) ||
                                !isset($cycle['unit']) || !in_array($cycle['unit'], ['hour', 'day'])
                            ) {
                                return json(['code' => 1, 'msg' => "广告位周期不能为空或格式错误"]);
                            }
                        } else {
                            // 兼容旧格式
                            if ($cycle === '' || !is_numeric($cycle)) {
                                return json(['code' => 1, 'msg' => "广告位周期不能为空或格式错误"]);
                            }
                        }
                    }

                    $param = [
                        'user_id'     => $user_id,
                        'level_id'    => $level_id,
                        'ratio'       => $ratio_value,
                        'profit_rate' => $profit_rate_value,
                        'ad_cycle'    => $ad_cycle_value,
                        'subordinate' => $subordinate,
                    ];
                    HomeUserExcessAdRatioConfigModel::create($param);
                }
                insert_operation_log('超额单控广告位比例配置', $param);
                return json(['code' => 0, 'msg' => '配置保存成功']);

            } catch (\Exception $e) {
                return json(['code' => 2, 'msg' => '保存失败，发生服务器错误'.$e->getMessage()]);
            }
        }

        $id_param = $this->request->param('id');
        $uid_context = $this->request->param('uid');

        if ($id_param) {
            $info = HomeUserExcessAdRatioConfigModel::find($id_param);
            if (!$info) {
                return $this->error('记录不存在');
            }
            View::assign('info', $info);
            View::assign('uid', $info->user_id);
        } else {
            View::assign('uid', $uid_context);
            View::assign('info', (object) [
                'user_id'        => $uid_context,
                'level_id'       => '',
                'match_sequence' => 1,
                'ratio'          => ''
            ]);
        }
        return View::fetch();
    }

    public function clearCache($level_id, $user_id)
    {
        $redis = \think\facade\Cache::store('redis');
        $redis_key = config("redis_cache_key.user_excess_ad_ration") .'_'.$level_id.'_'.$user_id;
        $redis->delete($redis_key);
    }
}
