<?php
namespace app\job;

use app\common\model\ActivitynewModel;
use app\common\repository\ActivityNewRechargeBonusRepository;
use app\common\repository\ActivityNewRecordRepository;
use think\queue\Job;

class Activity {
    protected ActivitynewModel $activity;
    public function fire(Job $job, $order){
        // 这里获取到的 $order 是个数组
        // 判断活动状态
        $this->activity = $this->checkActivity($order['activity_id']);
        if (!$this->activity) {
            $job->delete();
        }
        if ($this->activity->type == 8) {
            $this->type8($job, $order);
        }

    }

    public function type8(Job $job, $order): void
    {
        $activity_record_repository = new ActivityNewRecordRepository();
        // 获取活动记录
        $record = $activity_record_repository->getUserRecords($order['user_id'], $order['activity_id'], $order['recharge_bonus_id'], 0);
        if (!$record) {
            dump('没有活动记录');
            $job->delete();
            return;
        }
        // 记录活动充值金额
        $recharge_amount = $record->recharge_amount + $order['money'];
        $activity_record_repository->update(['id' => $record->id], ['recharge_amount' => $recharge_amount]);
        dump($record->user_id . ' 累计充值金额：'.$recharge_amount);
        // 判断是否满足活动条件
        // 获取活动金额门槛
        $recharge_bonus = (new ActivityNewRechargeBonusRepository())->find($order['recharge_bonus_id']);
        if ($recharge_amount < $recharge_bonus->amount) {
            dump('不满足发放奖励条件');
            $job->delete();
            return;
        }
        // 计算活动奖励
        $reward = $recharge_amount * $recharge_bonus->rate / 100;
        $activity_record_repository->update(['id' => $record->id], ['reward' => $reward]);
        dump('活动记录成功_' . $record->reward);
        $job->delete();
    }

    public function checkActivity($id)
    {
        if (!$id) {
            dump('活动不存在');
            return null;
        }
        $activity = ActivitynewModel::find($id);
        if (!$activity || $activity->status != 1) {
            dump('活动不存在');
            return null;
        }
        if ($activity->end_time < time()) {
            dump('活动已结束');
            return null;
        }
        if ($activity->status != 1) {
            dump('活动已停止');
            return null;
        }

        return $activity;
    }
}
