<?php


namespace app\admin\controller;

use app\admin\model\ConfigModel;
use app\admin\model\MemberModel;
use app\admin\transform\BrokeragePoolTransform;
use app\common\model\HomeUserVirtualModel;
use app\common\model\MoneyLogModel;
use app\common\model\TaskSummaryModel;
use app\common\model\TeamActiveRewardV2Model;
use app\common\model\UserModel;
use think\facade\Cache;
use think\facade\View;
use think\Model;
use think\response\Json;

/**
 * Class TeamReward
 * @package app\admin\controller
 * 团队奖励模块
 */
class TeamReward extends Base
{

    public function index(){
        $trigger_type_map = [
            1=>"【直推】",
            2=>"【活跃】",
            3=>"【直推+活跃】"
        ];

        if (request()->isAjax()) {
            //数据请求
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $page = $param['pageNumber'];
            $reward_level = $param['reward_level'];// 类型
            $leader1 = $param['leader1'];// 所属代理

            $where = [];
            if (array_key_exists('user_id', $param) && $param['user_id']) {
                $where[] = ['user_id', '=', $param['user_id']];
            }

            if (array_key_exists('reward_level', $param) && $param['reward_level']) {
                $where[] = ['reward_level', '=', $param['reward_level']];
            }

            if (array_key_exists('leader1', $param) && $param['leader1']) {
                $where[] = ['user.tree_id', 'like', "/".$param['leader1']."/%"];
            }

            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime']);
                $end_time   = strtotime($param['endTime']);
                $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
            }

            // 获取数据
            $result = TeamActiveRewardV2Model::withJoin("user")
                ->where($where)
                ->order(['id' => 'desc'])
                ->paginate(['page' => $page, 'list_rows' => $limit]);
            $data = $result->toArray();
            foreach ($data['data'] as &$item) {
                $top_leader = UserModel::getLeaderByTree($item['user']['tree_id'], 1);
                $item['user_info'] = "账号:".$item['user']['user_name']."/".$item['user']['id'].PHP_EOL;
                $item['top_leader'] = $top_leader['user_name']."/".$top_leader['remark'];
                $item['reward_type'] = $item['reward_level']==1?"初始团队奖励":"成长团队奖励";
                $item['status_txt'] = $item['status']==1?"已领取":"未领取";
                $item['trigger_type_txt'] = $trigger_type_map[$item['trigger_type']];
                $item['receive_time'] = $item['receive_time']>1000?date("Y-m-d H:i:s", $item['receive_time']):"--";
            }
            return json($data);
        }
        $agent = MemberModel::where('is_proxy', 1)->select();
        View::assign('agent', $agent);
        return View::fetch();
    }

    /**
     * 中奖配置修改
     */
    public function config(){
        $type = $this->request->param('type', 1);

        $team_reward_config_json = ConfigModel::getConfigValue('team_reward_config_'.$type);
        $team_reward_config = $team_reward_config_json ? json_decode($team_reward_config_json, true) : [];
        View::assign([
            'team_reward_config' => $team_reward_config,
            'team_reward_config_json' => $team_reward_config_json,
            'title'=>$type==1?"初始团队活跃奖配置":"成长团队活跃奖配置",
            'config_key'=>"team_reward_config_".$type,
            'condition_txt'=>$type==1?"或":"且"
        ]);
        return View::fetch();
    }

    /**
     * 中奖配置修改
     */
    public function config1(){
        $type = $this->request->param('type', 1);

        $key = "team_reward_config_".$type;
        $team_reward_config = ConfigModel::getConfigValue($key);
        $team_reward_config = $team_reward_config ? json_decode($team_reward_config, true) : [];
        if ($this->request->isAjax()) {
            $config = $this->request->post();
            ConfigModel::update(['value'=>json_encode($config)], ['name' => 'team_reward_config_'.$type]);
            Cache::set("config_field_".$key, serialize(json_encode($config)));//刷新缓存
            $this->success(lang('配置修改成功'), '', 'close');
        }
        View::assign([
            'config' => $team_reward_config,
            'title'=>$type==1?"初始团队活跃奖配置":"成长团队活跃奖配置",
            'config_key'=>"team_reward_config_".$type,
            'condition_txt'=>$type==1?"或":"且"
        ]);
        return View::fetch();
    }

    protected $userInfo = [
        'is_xuni' => 0,
        'show_virtual_data' => 0,
    ];

    public function indexV2(): Json
    {
        $user_id = $this->userid;
        // 获取团队活跃奖励配置和完成人数
        $teamActiveRewardV2Model = new TeamActiveRewardV2Model();
        $config1 = $teamActiveRewardV2Model->getConfig(1);
        $config2 = $teamActiveRewardV2Model->getConfig(2);

        if (empty($config1) || empty($config2)) {
            return $this->json(msg(-1, [], '团队活跃配置不全，请检查。'));
        }
        if ($this->userInfo['is_xuni'] == 1 && $this->userInfo['show_virtual_data'] == 1) {
            $virtual_data = HomeUserVirtualModel::getUserVirtual($user_id);
            $team_active_reward = $virtual_data['team_active_reward'];
            $dataTypeTwo['is_achieve'] = false;
            $dataTypeTwo['sum_task'] = 2;
            $dataTypeTwo['leader1_user'] = 0;// 直推用户数
            $dataTypeTwo['team_active_reward'] = [];
            $dataTypeTwo['config'] = $config1[0];
            $dataTypeTwo['status'] = -1;//默认待完成

        } else {
            // 得到总的佣金
            $reward_money = TeamActiveRewardV2Model::where('user_id', $user_id)
                ->sum("reward_amount");
            //待领取额度
            $wait_money = TeamActiveRewardV2Model::where('user_id', $user_id)
                ->where('status', 0)
                ->sum("reward_amount");
            $dataTypeOne = [];
            foreach ($config1 as $config) {
                $config1Map[$config['phase']] = $config;
            }
            foreach ($config2 as $config) {
                $config2Map[$config['phase']] = $config;
            }
            foreach ($config1 as $key => $config) {
                $leader1_user = \app\common\model\UserModel::getLevel1ActiveUserNumForRecharge($user_id, $config['recharge_money']);//得到当前阶段的直推用户
                $sum_task = TaskSummaryModel::field('user_id,  sum(task_level1 + task_level2 ) sum_task') //得到活跃用户数据
                ->where('user_id', $user_id)
                    ->where("date", "20251010")
                    ->group('user_id')
                    ->find()->toArray();
                $reward_data = TeamActiveRewardV2Model::where("reward_level", 1)
                    ->where('user_id', $user_id)
                    ->where('phase', $config['phase'])
                    ->find()->toArray();
                $dataTypeOne['is_achieve'] = false;
                $dataTypeOne['active_num'] = $sum_task?$sum_task['sum_task']:0;
                $dataTypeOne['direct_referral_num'] = $leader1_user;// 直推用户数
                $dataTypeOne['team_active_reward'] = [];
                $dataTypeOne['config'] = $config;
                $dataTypeOne['status'] = -1;//默认待完成
                if (!empty($reward_data)) {
                    if ($reward_data['status'] == 0) { //如果存在未领取的记录则返回当前记录
                        $dataTypeOne['team_active_reward'] = $reward_data;
                        $dataTypeOne['is_achieve'] = true;
                        $dataTypeOne['status'] = $reward_data['status'];//0待领取，1已领取，-1待完成
                        break;
                    } else if ($reward_data['status'] == 1) {
                        $configIndex = $config['phase'] + 1;
                        if (isset($config2Map[$configIndex])) {
                            $dataTypeOne['config'] = $config1Map[$configIndex];
                        }
                    }

                } else { //如果不存在未领取的记录则判断是否完成
                    break;
                }
            }

            $dataTypeTwo = [];
            // 排查每一个阶段
            foreach ($config2 as $key => $config) {
                $leader1_user = \app\common\model\UserModel::getLevel1ActiveUserNumForRecharge($user_id, $config['recharge_money']);//得到当前阶段的直推用户
                $sum_task = TaskSummaryModel::field('user_id,  sum(task_level1 + task_level2 ) sum_task') //得到活跃用户数据
                ->where('user_id', $user_id)
                    ->where("date", date('Ymd'))
                    ->group('user_id')
                    ->find();
                $reward_data = TeamActiveRewardV2Model::where("reward_level", 2)
                    ->where('user_id', $user_id)
                    ->where('phase', $config['phase'])
                    ->find();
                $dataTypeTwo['is_achieve'] = false;
                $dataTypeTwo['active_num'] = $sum_task?$sum_task['sum_task']:0;
                $dataTypeTwo['direct_referral_num'] = $leader1_user;// 直推用户数
                $dataTypeTwo['team_active_reward'] = [];
                $dataTypeTwo['config'] = $config;
                $dataTypeTwo['status'] = -1;//默认待完成

                if (!empty($reward_data)) {
                    if ($reward_data['status'] == 0) { //如果存在未领取的记录则返回当前记录
                        $dataTypeTwo['team_active_reward'] = $reward_data;
                        $dataTypeTwo['is_achieve'] = true;
                        $dataTypeTwo['status'] = $reward_data['status'];//0待领取，1已领取，-1待完成
                        break;
                    } else if ($reward_data['status'] == 1) {
                        $configIndex = $config['phase'] + 1;
                        if (isset($config2Map[$configIndex])) {
                            $dataTypeTwo['config'] = $config2Map[$configIndex];
                        }
                    }

                } else { //如果不存在未领取的记录则判断是否完成
                    break;
                }
            }
        }
        return json(msg(0, [
            'initialTeam' => $dataTypeOne,
            'growthTeam' => $dataTypeTwo,
            'rewardAmount'=>$reward_money,
            'waitAmount' => $wait_money
        ], ''));
    }
}