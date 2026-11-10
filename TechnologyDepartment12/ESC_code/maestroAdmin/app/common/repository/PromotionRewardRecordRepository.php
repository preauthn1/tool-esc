<?php

namespace app\common\repository;

use app\common\model\MoneyLogModel;
use app\common\model\PromotionRewardRecordModel;
use think\App;

/**
 * Class PromotionRewardRecordRepository
 */
class PromotionRewardRecordRepository extends Repository
{
    protected $model;

    public function __construct()
    {

        //绑定模型
        $this->model = new PromotionRewardRecordModel();
    }

    // 状态 0-待领取 1-已领取
    const STATUS_WAIT = 0;
    const STATUS_RECEIVED = 1;

    public static array $statusMap = [
        self::STATUS_WAIT     => '待领取',
        self::STATUS_RECEIVED => '已领取',
    ];

    public function getTodayRewardRecordByUserId($user_id, $date)
    {
        $where[] = ['user_id', '=', $user_id];
        $where[] = ['day_time', '=', $date];
        return $this->findWhere($where);
    }

    public function receiveReward($user_id, $day): bool
    {
        $where[] = ['user_id', '=', $user_id];
        $where[] = ['day', '=', $day];
        $where[] = ['status', '=', PromotionRewardRecordRepository::STATUS_WAIT];
        $waiting_receive_record = $this->model->where($where)->select();
        $return_flag = false;
        foreach ($waiting_receive_record as $record) {
            $award = $record->award;
            $title = "连续推广奖励";
            $remarks = i18n_date2str(strtotime($record->day_time)) ." 连续推广奖励：{$award} ";
            $money_log = new MoneyLogModel();
            $change = "add";
            $type = $record->award_type == 1 ? MoneyLogModel::TYPE_USER : MoneyLogModel::TYPE_BROKERAGE;
            $money_log->changeMoney($user_id, $award, $type, $change, $title, $remarks, 24);

            $record->status = PromotionRewardRecordRepository::STATUS_RECEIVED;
            $record->reward_times = time();
            $record->update_time = time();
            $record->save();
            $return_flag = true;
        }
        return $return_flag;
    }
}
