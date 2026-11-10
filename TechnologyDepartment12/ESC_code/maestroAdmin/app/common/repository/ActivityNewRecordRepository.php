<?php

namespace app\common\repository;

use app\common\model\ActivitynewRecordModel;
use app\common\model\MoneyLogModel;

class ActivityNewRecordRepository extends Repository
{
    /**
     * @var ActivitynewRecordModel
     */
    protected $model;

    public function __construct()
    {
        //绑定模型
        $this->model = new ActivitynewRecordModel();
    }

    // 获取用户活动记录
    public function getUserRecords($user_id, $activity_id, $recharge_bonus_id = null, $status = -1)
    {
        $where = [
            ['user_id', '=', $user_id],
            ['activity_id', '=', $activity_id],
        ];
        if (in_array($status, [0, 1])) {
            $where[] = ['status', '=', $status];
        }
        if ($recharge_bonus_id) {
            $where[] = ['recharge_bonus_id', '=', $recharge_bonus_id];
            return self::findWhere($where);
        }
        return self::order(['id' => 'desc'])->selectWhere($where);
    }

    /**
     * 体验金到期扣除
     */
    public function trialBonusReturn()
    {
        $where = [
            ['reward_type', '=', 3],
            ['status', '=', 1],
            ['return_time', '<', time()],
        ];
        $list = self::selectWhere($where);
        $money_log_model = new MoneyLogModel();
        foreach ($list as $item) {
            $user_id = $item->user_id;
            $activity_id = $item->activity_id;
            $money = $item->reward;
            $detail_type = 29;
            $remark = '体验金到期退回';
            $from_user_id = $item->from_user_id;
            $data = $money_log_model->changeMoney($user_id, $money, MoneyLogModel::TYPE_USER, 'sub', $remark,
                $remark . '_' . $money, $detail_type, 0, $from_user_id, $activity_id, true, 0, true);
            if ($data['code'] == 1) {
                $item->status = 2;
                $item->save();
                //解锁用户提现状态
                $user_model = new HomeUserRepository();
                $user_model->withdrawStatus($user_id, 1);
            }
        }
    }
}
