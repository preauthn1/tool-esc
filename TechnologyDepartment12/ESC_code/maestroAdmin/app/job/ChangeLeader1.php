<?php
namespace app\job;

use think\facade\Cache;
use think\facade\Db;
use think\queue\Job;

class ChangeLeader1 {
    private $level = [];
    public function fire(Job $job, $id){
        $redis = Cache::store('redis');
        $redis_key = "change_leader1_{$id}";
        if (!$redis->has($redis_key)) {
            $job->delete();
            return;
        }
        // 获取该用户的上级id
        $lists = Db::name('home_user')->whereLike("tree_id", "%/{$id}/%")->column('id');
        if (!empty($lists)) {
            foreach ($lists as $userid) {
                $this->level = [];
                $this->getLeader1_1($userid);
//                dump($this->level);
                unset($this->level['leader0']);
                if ($this->level) {
                    $tree = "/".implode('/', array_reverse(array_values($this->level)))."/{$userid}/";
                }else{
                    $tree = "/{$userid}/";
                }
                $trees = array_reverse(explode("/", trim($tree, '/')));

                $update = [
                    'tree_id' => $tree,
                    'leader2' => isset($trees[2]) && $trees[2] != $userid ? $trees[2] : 0,
                    'leader3' => isset($trees[3]) && $trees[3] != $userid ? $trees[3] : 0,
                    'leader4' => isset($trees[4]) && $trees[4] != $userid ? $trees[4] : 0,
                    'leader5' => isset($trees[5]) && $trees[5] != $userid ? $trees[5] : 0,
                    'leader6' => isset($trees[6]) && $trees[6] != $userid ? $trees[6] : 0,
                    'leader7' => isset($trees[7]) && $trees[7] != $userid ? $trees[7] : 0,
                ];
//                dump($update);
//                die;
                Db::name('home_user')->where("id", $userid)->update($update);
                echo ("{$userid}===" . date("Y-m-d H:i:s") . "=={$tree}== " . floor(umicrotime() * 1000) ."---" . umicrotime() . "\n");
//                usleep(500*1000);
            }
        }
        $redis->delete($redis_key);
        $job->delete(); //删除
    }

    /**
     * 获取用户上级ID
     * @param type $id
     */
    private function getLeader1_1($id, $level = 0) {
        $leader1 = Db::name('home_user')->where('id', $id)->find();
        $this->level["leader{$level}"] = $leader1['id'];
        if ($leader1['leader1'] > 0) {
            $level++;
            return $this->getLeader1_1($leader1['leader1'], $level);
        }
    }

    public function failed($data){
    }

}
