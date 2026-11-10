<?php

namespace app\admin\controller;

use app\admin\model\MemberModel;
use app\common\model\TaskSummaryModel as Model;
use app\common\model\UserModel;
use app\common\model\RecommendModel;
use think\facade\View;

class TaskSummary extends Base
{
    public function index()
    {
        if (request()->isAjax() || request()->isPost()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $page = $param['pageNumber'];
            $where = [];

            // 用户名搜索
            if (!empty($param['user_name'])) {
                $user_ids = UserModel::whereLike('user_name', "%{$param['user_name']}%")->column('id');
                if (!empty($user_ids)) {
                    $where[] = ['user_id', 'in', $user_ids];
                }
            }

            // 日期搜索
            if (!empty($param['date'])) {
                $where[] = ['date', '=', str_replace('-', '', $param['date'])];
            }

            // 结算日期
            if (!empty($param['rec_settle'])) {
                $where[] = ['rec_settle', '=', str_replace('-', '', $param['rec_settle'])];
            }

            // 结算状态
            if (isset($param['settle_status']) && $param['settle_status'] !== '') {
                if ($param['settle_status'] == 1) {
                    $where[] = ['rec_settle', '<>', ''];
                } else {
                    $where[] = ['rec_settle', '=', ''];
                }
            }

            if (!empty($param['leader1'])) {
                $where[] = ['user_model.tree_id', 'like', '%/'.$param['leader1'].'/%'];
                $where[] = ['user_model.tree_id', 'not like', '%/'.$param['leader1'].'/'];
            }
            // 获取推荐配置
            $recommendModel = new RecommendModel();
            $recommendConfig = $recommendModel->where('id', 1)->find();

            $model = new Model();
            $query = $model->hasWhere('user');
            // 达标状态
            if (isset($param['task_status']) && $param['task_status'] !== '') {
                $configVip1Arr = explode('_', $recommendConfig['vip1']);
                if ($param['task_status'] == 1) {
                    $where[] = ['task_level1', '>=', $configVip1Arr[0]];
                    $query = $query->where('(task_level1 + task_level2 + task_level3) >= :config',
                        ['config' => $configVip1Arr[1]]);
                } else {
//                    $where[] = ['task_level1', '<', $configVip1Arr[0]];
                    $query = $query->where('task_level1 < :config1 or (task_level1 + task_level2 + task_level3) < :config2',
                        ['config1' => $configVip1Arr[0], 'config2' => $configVip1Arr[1]]);
                }
            }
            $export = $this->request->param("export/d", 0);
            if ($export) {
                $selectResult = $query->where($where)
                    ->order(['date' => 'DESC', 'id' => 'desc'])
                    ->select();
            } else {
                $selectResult = $query->where($where)
                    ->order(['date' => 'DESC', 'id' => 'desc'])
                    ->paginate(['page' => $page, 'list_rows' => $limit]);
            }


            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                $user = UserModel::where('id', $vo['user_id'])->find();
                $selectResult[$key]['user_info'] = $user ? ($user['id'].'/'.$user['user_name']) : '-';
                $task_count = $vo['task_level1'] + $vo['task_level2'] + $vo['task_level3'];
                $selectResult[$key]['task_count'] = $task_count;

                // 初始化达标等级
                $matchLevel = 0;
                $currentLevelConfig = '';

                // 遍历VIP等级配置，找出用户可以达到的最高等级
                for ($i = 8; $i >= 1; $i--) {
                    $vipField = 'vip'.$i;
                    $vipConfig = $recommendConfig[$vipField];

                    if (!empty($vipConfig)) {
                        $configArr = explode('_', $vipConfig);
                        if (count($configArr) >= 2) {
                            $directRequired = intval($configArr[0]);
                            $taskRequired = intval($configArr[1]);
                            $reward = isset($configArr[2]) ? floatval($configArr[2]) : 0;

                            if ($vo['task_level1'] >= $directRequired && $task_count >= $taskRequired) {
                                $matchLevel = $i;
                                $selectResult[$key]['direct_required'] = $directRequired;
                                $selectResult[$key]['task_required'] = $taskRequired;
                                $selectResult[$key]['task_status'] = getStatusText(1, '已达标');
                                $selectResult[$key]['match_reward'] = $reward;
                                break;
                            }
                        }
                    }
                }

                if ($matchLevel == 0) {
                    $selectResult[$key]['direct_required'] = '-';
                    $selectResult[$key]['task_required'] = '-';
                    $selectResult[$key]['task_status'] = getStatusText(0, '未达标');
                    $selectResult[$key]['match_reward'] = '-';
                }

                $selectResult[$key]['match_level'] = $matchLevel ? 'VIP'.$matchLevel : '-';

                // 添加结算状态
                $selectResult[$key]['settle_status'] = !empty($vo['rec_settle']) ? getStatusText(1,
                    '已结算') : getStatusText(0, '未结算');

                $top_leader = UserModel::getTopLeader($vo['user_id']);
                $selectResult[$key]['top_leader_remark'] = '';
                if ($top_leader) {
                    $selectResult[$key]['top_leader_remark'] = $top_leader['remark'];
                }
            }

            if ($export) {
                $header = [
                    "id"                => "ID",
                    "user_info"         => "用户名",
                    "top_leader_remark" => "所属代理",
                    "register"          => "注册人数",
                    "recharge"          => "有效充值人数",
                    "register_recharge" => "当天注册并充值人数",
//                    "direct_required"   => "任务要求一级下级有效人数",
                    "task_level1"       => "一级有效用户完成任务人数",
                    "task_level2"       => "二级有效用户完成任务人数",
                    "task_level3"       => "三级有效用户完成任务人数",
                    "task_count"        => "完成任务人数",
                    "task_required"     => "任务要求完成任务人数",
                    "task_status"       => "任务达标状态",
                    "match_level"       => "可达到等级",
                    "match_reward"      => "可达到等级奖励",
                    "date"              => "日期",
                    "rec_settle"        => "直推奖励日期",
                    "settle_status"     => "结算状态",
                ];
                return genXlsx($header, $selectResult, "直推任务结算统计");
            }


            return json($selectResult);
        }
        $agent = MemberModel::where('is_proxy', 1)->select();
        View::assign('agent', $agent);
        return View::fetch();
    }
}
