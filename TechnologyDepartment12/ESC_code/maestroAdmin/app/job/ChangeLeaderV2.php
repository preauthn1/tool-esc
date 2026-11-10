<?php
namespace app\job;

use app\admin\model\MemberModel;
use think\facade\Cache;
use think\facade\Db;
use think\queue\Job;

class ChangeLeaderV2 {

    public function fire(Job $job, $data){
        $user_id = $data['user_id'];
        $tree_id_old = $data['tree_id'];
        $redis = Cache::store('redis');
        $redis_key = "change_leader1_{$user_id}";
        if (!$redis->has($redis_key)) {
            $job->delete();
            return;
        }

        // 刷新当前用户的代理关系
        $this->refreshUserLevel($user_id, $tree_id_old);

        // 修改自己所有下级的代理关系
        $lists = Db::name('home_user')->where("id", "<>", $user_id)->whereLike("tree_id", "%/{$user_id}/%")->field('id,tree_id')->select();
        foreach ($lists as $list) {
            $tree_id = substr($list['tree_id'], strrpos($list['tree_id'],"/".$user_id."/")+strlen("/".$user_id."/"), strlen($list['tree_id']));
            $tree_id_temp = $tree_id_old.$tree_id;
            $this->refreshUserLevel($list['id'], $tree_id_temp);
        }

        $redis->delete($redis_key);
        $job->delete(); //删除
    }

    public function refreshUserLevel($user_id, $tree_id) {

        $tree_arr = explode('/', $tree_id);
        // 数组去空 $tree_arr
        $tree_arr = array_filter($tree_arr);
        // 倒序
        $tree_arr = array_reverse($tree_arr);
        $userModel = MemberModel::where("id", $user_id)->find();
        for ($i = 1; $i <= 7; $i++){
            if (isset($tree_arr[$i])) {
                $userModel["leader{$i}"] = $tree_arr[$i];
            } else {
                $userModel["leader{$i}"] = 0;
            }
        }
        $userModel->tree_id = $tree_id;
        $userModel->save();
    }

}
