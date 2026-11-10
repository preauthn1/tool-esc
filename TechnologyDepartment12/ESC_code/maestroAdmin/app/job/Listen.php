<?php
namespace app\job;

use think\queue\Job;
use think\facade\Queue;
use think\facade\Db;
use think\facade\Cache;
class Listen {

    public function fire(Job $job, $data){
        $cache = Cache::store("redis");
        if ($cache->has("deleteQueue:Listen")) {
            $job->delete();
            return;
        }
        $later = 4;
        $list = Db::name("withdrawals")->where("status", 4)->order("id ASC")->select();
        foreach ($list as $item) {
            if ($cache->has("agentpay_{$item['id']}")) {
                continue;
            }
            $cache->set("agentpay_{$item['id']}", 1, 86400);
            Queue::later($later, "Agentpay", $item['id'], 'agentpay');
            $later+=4;
        }
        echo timeToStr() . "\n";
        // 也可以重新发布这个任务
        $job->release(10); //$delay为延迟时间

    }

    public function failed($data){

        // ...任务达到最大重试次数后，失败了
    }

}
