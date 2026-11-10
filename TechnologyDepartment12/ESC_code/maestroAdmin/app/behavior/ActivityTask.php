<?php

namespace app\behavior;
use think\facade\Queue;
class ActivityTask {

    public function run($params) {
        if ($params['type'] == 1 || $params['type'] == 3) { //充值活动 | 邀请充值活动
//            Queue::later(3, "ActivityTask@type1", $params, 'activity');
//            Queue::later(3, "ActivityTask@type3", $params, 'activity');
        }elseif($params['type'] == 2 || $params['type'] == 4) { //邀请活动 | 注册赠送活动
//            Queue::later(3, "ActivityTask@type2", $params, 'activity');
//            Queue::later(3, "ActivityTask@type4", $params, 'activity');
        }elseif(in_array($params['type'], [6])) { //转盘抽奖活动
//            Queue::later(3, "ActivityTask@type6", $params, 'activity');
        }
    }

}
