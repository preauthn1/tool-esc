<?php
namespace app\job;

use app\common\model\UserModel;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Queue;
use think\queue\Job;

class CalculateTeamCount {
    public function fire(Job $job, $id){
        $redis = Cache::store('redis');
        $redis_key = "calculate_team_count_{$id}";
        if (!$redis->has($redis_key)) {
            $job->delete();
            return;
        }
        echo ("统计用户ID {$id} 的下级数量\n");
        // 获取下级用户id
        $team_count = UserModel::where('tree_id', 'like', "%/{$id}/%")->where('tree_id', 'not like', '%/'.$id.'/')->count();
        Db::name('home_user')->where("id", $id)->update(['team_count' => $team_count]);
        echo ("用户ID {$id} 的下级数量 {$team_count}\n");
        $redis->delete($redis_key);

        // 如果这个用户有上级，继续该用户的上级
        $user = Db::name('home_user')->where("id", $id)->find();
        if ($user['leader1']) {
            Queue::push('CalculateTeamCount', $user['leader1']);
            $redis->set("calculate_team_count_{$user['leader1']}", 1);
        }
        $job->delete(); //删除
    }
}
