<?php

namespace app\common\repository;

use app\common\model\ConfigModel;
use app\common\model\HomeUserBrokeragePoolModel;
use app\common\model\UserModel;

class HomeUserBrokeragePoolRepository extends Repository
{
    protected $model;

    public function __construct()
    {
        //绑定模型
        $this->model = new HomeUserBrokeragePoolModel();
    }

    public function settlement()
    {
        $brokerage_pool_config = ConfigModel::getConfigValue('brokerage_pool_config');
        $brokerage_pool_config = json_decode($brokerage_pool_config, true);
        $home_user_brokerage_pool_repository = new HomeUserBrokeragePoolRepository();
        // 获取所佣金池有余额的用户
        $users = (new HomeUserRepository)->selectWhere([
            ['brokerage_pool', '>', 0]
        ], ['id', 'brokerage_pool']);
        foreach ($users as $user) {
            // 获取今日活跃用户
//            $active_num_count = TaskSummaryRepository::getUserActiveByDate($user->id, 3, date('Ymd'));
            $team_ids = UserModel::where('tree_id', 'like', "%/{$user->id}/%")
                ->where('tree_id', 'not like', '%/' . $user->id . '/')
                ->column('id');
            $active_num_count = (new HomeUserRepository())->getTeamTodayTaskUserCount($team_ids);// 获取团队今日任务用户数

            // 循环 $brokerage_pool_config ，获取 active_num 小于等于 $team_count 的最大项
            $sum_amount = 0;
            foreach ($brokerage_pool_config as $config) {
                if ($config['active_num'] <= $active_num_count) {
                    $sum_amount += $config['amount'];
                }
            }
            $home_user_brokerage_pool = $home_user_brokerage_pool_repository->findWhere([
                ['user_id', '=', $user->id]
            ]);
            if (!$home_user_brokerage_pool) {
                $home_user_brokerage_pool_repository->model()->create([
                    'user_id' => $user->id,
                    'receivable' => $sum_amount
                ]);
            } else {
                if ($home_user_brokerage_pool->receivable < $sum_amount) {
                    $home_user_brokerage_pool->receivable = $sum_amount;
                    $home_user_brokerage_pool->save();
                }
            }
        }
    }
}
