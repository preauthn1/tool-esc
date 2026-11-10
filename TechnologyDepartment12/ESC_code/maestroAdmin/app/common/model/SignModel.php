<?php

namespace app\common\model;

use think\exception\DbException;
use think\Model;

class SignModel extends Model
{

    protected $autoWriteTimestamp = true;
    // 确定链接表名
    protected $name = 'sign';


    /**
     * 签到列表
     * @param string $sign_id
     * @param string $user_id
     * @param string $field
     * @param string $order
     */
    public static function getSignList($sign_id = '', $user_id = '', $field = '*', $order = 'day asc')
    {
        // 获取当前签到
        $sign_data = SignModel::where('id', $sign_id)->where('status', 1)->find();
        if (empty($sign_data)) {
            $sign_data = SignModel::where('status', 1)->find();
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
        $where[] = ['sign_id', '=', $sign_id];
        $list = SignDayModel::field($field)->where($where)->order($order)->select();
        // 获取当前用户签到天数
        $last_day = SignRecordModel::where('user_id', $user_id)->where('day_time', date("Y-m-d"))->value('day');
        if (empty($last_day)) {
            // 获取上一次签到
            $last_sign_record = SignRecordModel::where('user_id', $user_id)->order('id desc')->find();
            if ($last_sign_record && date("Y-m-d") == date("Y-m-d", strtotime($last_sign_record['day_time']) + (3600 * 24))) {
                $day = $last_sign_record['day'] + 1;
                if ($day > $cycle_day) {
                    $day = $cycle_day;
                }
            } else {
                $day = 1;
            }
        } else {
            $day = $last_day;
        }
        foreach ($list as $k => $v) {
            $list[$k]['award'] = floatval($v['award']);//签到奖励
            $list[$k]['status'] = 0;//0未签到 1可以签到 2已签到
            if ($day > $v['day']) {
                $list[$k]['status'] = 2;
            }
            if ($day == $v['day']) {
                //当天可以签到
                $list[$k]['status'] = 1;
                if ($day == $last_day) {
                    $list[$k]['status'] = 2;
                }
            }
        }
        return $list;
    }

    /**
     * 用户签到
     * @param string $user_id
     * @param string $sign_id
     * @return bool
     * @throws DbException
     */
    public static function addSignRecord($user_id = '', $sign_id = '')
    {
        // 获取当前签到
        $sign_data = SignModel::where('id', $sign_id)->where('status', 1)->find();
        if (empty($sign_data)) {
            $sign_data = SignModel::where('status', 1)->find();
            if (empty($sign_data)) {
                return false;
            }
            $sign_id = $sign_data['id'];
        }
        $cycle_day = $sign_data['day'];
        // 获取奖励
        $award_data = SignDayModel::where('sign_id', $sign_id)->column('award,award_type', 'day');
        // 获取上一次签到
        $last_sign_record = SignRecordModel::where('user_id', $user_id)->order('id desc')->find();
        if ($last_sign_record && date("Y-m-d") == $last_sign_record['day_time']) {
            return false;
        }
        if ($last_sign_record && date("Y-m-d") == date("Y-m-d", strtotime($last_sign_record['day_time']) + (3600 * 24))) {
            $day = $last_sign_record['day'] + 1;
            if ($day > $cycle_day) {
                $day = $cycle_day;
            }
        } else {
            $day = 1;
        }
        if (isset($award_data[$day])) {
            $award = $award_data[$day];
        } else {
            $award = $award_data[1];
        }
        $data = [
            'user_id' => $user_id,
            'sign_id' => $sign_id,
            'day' => $day,
            'award' => $award['award'],
            'award_type' => $award['award_type'],
            'day_time' => date("Y-m-d"),
            'create_time' => time(),
            'update_time' => time()
        ];
        SignRecordModel::startTrans();
        $result = SignRecordModel::insert($data);
        if ($result == false) {
            return false;
        }
        $title = "签到奖励";
        $remarks = "完成签到获得奖励:{$award['award']} ";
        $money_log = new MoneyLogModel();
        $change = "add";
        $type = $award['award_type'] == 1 ? MoneyLogModel::TYPE_USER : MoneyLogModel::TYPE_BROKERAGE;
        $data = $money_log->changeMoney($user_id, $award['award'], $type, $change, $title, $remarks, 6);
        if ($data['code'] == 1) {
            SignRecordModel::commit();
            return true;
        }
        SignRecordModel::rollback();
        return false;
    }

}
