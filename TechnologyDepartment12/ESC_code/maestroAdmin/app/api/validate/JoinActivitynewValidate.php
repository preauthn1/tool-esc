<?php

namespace app\api\validate;

use app\common\repository\ActivityNewPasswordRecordRepository;
use app\common\repository\ActivityNewPasswordRepository;
use app\common\repository\ActivityNewRepository;

class JoinActivitynewValidate extends BaseValidate
{
    protected $rule = [
        'activity_id' => 'require|checkActivity',
        'password'    => 'require|checkPassword',
    ];

    protected $message = [
        'activity_id.require' => '活动不存在',
        'password.require'    => '请输入密码',
    ];


    protected $scene = [

    ];

    public function checkActivity($activity_id, $rule, $data): bool|string
    {
        $type = $data['type'] ?? '';
        if (!$activity_id) {
            return '活动不存在';
        }
        $activity = (new ActivityNewRepository)->getActivityById($activity_id);
        if (!$activity || $activity->status != 1) {
            return '活动不存在';
        }
        if ($activity->end_time < time()) {
            return '活动已结束';
        }
        if ($activity->start_time > time()) {
            return '活动未开始';
        }
        if ($activity->status != 1) {
            return '活动已停止';
        }

        if ($type && $type != $activity->type) {
            return '活动不存在';
        }

        return true;
    }

    public function checkPassword($password, $rule, $data): bool|string
    {
        $activity_id = $data['activity_id'];
        // 获取活动可用密码活动名额
        $activity_password = (new ActivitynewPasswordRepository)->getPasswordByActivityId($activity_id, $password);
        if (!$activity_password) {
            return '活动编码错误';
        }

        // 获取已参加活动的人数
        $joined_user_count = (new ActivityNewPasswordRecordRepository)->model()
            ->where('user_id', '<>', $data['user_id'])
            ->where('activity_id', $activity_id)
            ->where('password', $password)
            ->group('user_id')
            ->count();
        if ($joined_user_count >= $activity_password->num) {
            return '活动名额已满，无法参与';
        }
        return true;
    }
}
