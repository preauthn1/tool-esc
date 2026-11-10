<?php

namespace app\common\repository;

use app\common\model\PromotionRewardDayModel;
use app\common\model\PromotionRewardModel;
use app\common\model\PromotionRewardRecordModel;
use Carbon\Carbon;

/**
 * Class PromotionRewardDayRepository
 */
class PromotionRewardDayRepository extends Repository
{
    protected $model;

    public function __construct()
    {
        //绑定模型
        $this->model = new PromotionRewardDayModel();
    }

    public static $award_type_list = [
        '1' => '基础账户',
        '2' => '佣金账户'
    ];

    public function getDayList($sign_id, $user_id)
    {
        // 获取当前签到
        $sign_data = PromotionRewardModel::where('id', $sign_id)->where('status', 1)->find();
        if (empty($sign_data)) {
            $sign_data = PromotionRewardModel::where('status', 1)->find();
            if (empty($sign_data)) {
                return false;
            }
            $sign_id = $sign_data['id'];
        }
        // 获取签到天数
        $cycle_day = $sign_data['day'];
        if ($cycle_day) {
            $where[] = ['day', '<=', $cycle_day];
        }
        $where[] = ['sign_id', '=',  $sign_id];
        $list = PromotionRewardDayModel::where($where)->order(['day' => 'asc'])->select();

        $day_time_arr = [];
        $now = Carbon::now();
        for ($i = 0; $i <= $cycle_day; $i++) {
            $day_time_arr[] = $now->format('Y-m-d');
            $now->subDays();
        }

        // 获取最近 $cycle_day 条的数据
        $last_cycle_day_sign_record = PromotionRewardRecordModel::where('user_id', $user_id)
            ->where('sign_id', $sign_id)
            ->where('day_time', 'in', $day_time_arr)
            ->order('id', 'desc')
            ->limit($cycle_day)
            ->select();

        if(!$last_cycle_day_sign_record->isEmpty()) {
            $last_sign_record = $last_cycle_day_sign_record->first();
            if ($last_sign_record->day_time == date('Y-m-d')) {
                $latest_sign_day = $last_sign_record->day;
                $latest_sign_day_time = $last_sign_record->day_time;
            } else if ($last_sign_record && date('Y-m-d') == date("Y-m-d", strtotime($last_sign_record->day_time) + (3600 * 24))) {
                // 如果上一次签到 + 1 天等于今天，则可领取奖励
                $latest_sign_day = $last_sign_record->day;
                $latest_sign_day_time = $last_sign_record->day_time;
            } else {
                $latest_sign_day = 0;
                $latest_sign_day_time = date('Y-m-d');
                // 把未领取的都变成过期
                $this->setExpired([
                    ['user_id', '=', $user_id],
                    ['status', '=', 0],
                    ['sign_id', '=', $sign_id]
                ]);
            }

            // 循环，判断是否连续签到(日期是否连续)
            $now = Carbon::parse($latest_sign_day_time);
            foreach($last_cycle_day_sign_record as $record) {
                if ($record->day_time == $now->format('Y-m-d')) {
                    $now->subDays();
                } else {
                    // 把未领取的都变成过期
                    $this->setExpired([
                        ['user_id', '=', $user_id],
                        ['id', '<=', $record->id],
                        ['status', '=', 0],
                        ['sign_id', '=', $sign_id]
                    ]);
                }
            }
        } else {
            // 连续 $cycle_day 都没有签到
            // 把未领取的都变成过期
            $this->setExpired([
                ['user_id', '=', $user_id],
                ['status', '=', 0],
                ['sign_id', '=', $sign_id]
            ]);
            $latest_sign_day = 0;
        }
        // 重新获取，因为上面可能更新了状态
        $last_cycle_day_sign_record = PromotionRewardRecordModel::where('user_id', $user_id)
            ->where('sign_id', $sign_id)
            ->where('day_time', 'in', $day_time_arr)
            ->order('id', 'desc')
            ->limit($cycle_day)
            ->select();
        // 对结果进行过滤，提取每个分组中最大的 day_time
        $filteredRecords = $last_cycle_day_sign_record->reduce(function($carry, $item) {
            $day = $item->day;
            if (!isset($carry[$day]) || $item->day_time > $carry[$day]->day_time) {
                $carry[$day] = $item;
            }
            return $carry;
        }, []);
        $days = array_column($filteredRecords, 'status', 'day');
        foreach ($list as $day) {
            if (in_array($day['day'], array_keys($days))) {
                //0待领取，1已领取
                $day['status'] = $days[$day['day']];
                if ($day['day'] > $latest_sign_day && $day['status'] == 1) {
                    $day['status'] = -1;
                }
            } else {
                $day['status'] = -1;
            }
        }

        return $list;
    }

    public function setExpired($where): void
    {
        // 把未领取的都变成已领取
        PromotionRewardRecordModel::where($where)->update(['status' => 2]); // 已过期
    }
}
