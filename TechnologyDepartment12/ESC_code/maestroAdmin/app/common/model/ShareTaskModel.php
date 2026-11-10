<?php

namespace app\common\model;

use think\Model;

class ShareTaskModel extends Model
{
    const ACCOUNT_TYPE_BASE = 1;//基础账号
    const ACCOUNT_TYPE_TYJ = 3;//彩金账号
    const ACCOUNT_TYPE_BROKERAGE = 5;//佣金账号

    static $ACCOUNT_TYPE_NAME = [
        self::ACCOUNT_TYPE_BASE => "基础账号",
        self::ACCOUNT_TYPE_TYJ => "彩金账号",
        self::ACCOUNT_TYPE_BROKERAGE => "佣金账号",
    ];
    const STATUS_NO_AUDIT = 1;//未审核
    const STATUS_AUDIT = 2;//审核成功
    const STATUS_REFUSE = 3;//拒绝

    static $STATUS__NAME = [
        self::STATUS_NO_AUDIT => "审核中",
        self::STATUS_AUDIT => "审核成功",
        self::STATUS_REFUSE => "拒绝",
    ];
    // 确定链接表名
    protected $name = 'share_task';

    /**
     * 获取最后一次任务
     * @param $user_id
     * @param $share_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getEndTask($user_id, $share_id)
    {
        $share_data = ShareModel::where('id', $share_id)->where('status', 1)->find();
        if (empty($share_data)) {
            return [
                'code' => 10,
                'msg' => '任务结束'
            ];
        }
        $map = [
            'user_id' => $user_id,
            'share_id' => $share_id,
        ];
        $award = $share_data['award'];
        if ($award == 1) {
            $task = $this->where($map)->order('id DESC')->find();
        } else {
            $task = $this->where($map)->whereBetween('create_time', [strtotime(date("Y-m-d")), time()])->order('id DESC')->find();
        }
        if (!empty($task)) {
            $task['check_time_str'] = "";
            if ($task['status'] > 1) {
                $task['check_time_str'] = date("d/m/Y H:i:s", $task['check_time']);
            }
            $task['status_str'] = self::$STATUS__NAME[$task['status']];
        }
        return [
            'code' => 1,
            'data' => $task
        ];
    }

    /**
     * 判断是否可以参与活动
     * @param $user_id
     * @param $share_id
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function isCondition($user_id, $share_id)
    {
        $share_data = ShareModel::where('id', $share_id)->where('status', 1)->find();
        if (empty($share_data)) {
            return [
                'code' => 10,
                'msg' => '任务结束'
            ];
        }
        $map = [
            'user_id' => $user_id,
            'share_id' => $share_id,
        ];
        $award = $share_data['award'];
        if ($award == 1) {
            $num = $this->where($map)->count();
        } else {
            $num = $this->where($map)->whereBetween('create_time', [strtotime(date("Y-m-d")), time()])->count();
        }
        if ($num > 0) {
            return [
                'code' => 11,
                'data' => $num,
                'msg' => '今日任务已做完'
            ];
        }
//        $rec_ids = UserModel::getTeamAllIds($user_id, 3, $award == 2);
//        $rec_num = count($rec_ids);
        $member_level = MemberLevelModel::where('user_id', $user_id)->order('id DESC')->find();
        $days = intval(($member_level['level_end_time'] - time()) / (24 * 3600));
        $level_id = $member_level['to_level'];
        if ($days < $share_data['days']) {
            return [
                'code' => 12,
                'msg' => '会员有效期不足'
            ];
        }
        if ($level_id < $share_data['level_id']) {
            return [
                'code' => 13,
                'msg' => '会员有等级不足'
            ];
        }
        return [
            'code' => 1,
            'msg' => '可以参与'
        ];
    }

    /**
     * 自动审核判断
     * @param $user_id
     * @param $share_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function isAutoCondition($user_id, $share_id)
    {
        $share_data = ShareModel::where('id', $share_id)->where('status', 1)->find();
        if (empty($share_data)) {
            return [
                'code' => 10,
                'msg' => '任务结束'
            ];
        }
        $member_level = MemberLevelModel::where('user_id', $user_id)->order('id DESC')->find();
        $days = intval(($member_level['level_end_time'] - time()) / (24 * 3600));
        $level_id = $member_level['to_level'];
        if ($days < $share_data['days']) {
            return [
                'code' => 12,
                'msg' => '会员有效期不足'
            ];
        }
        if ($level_id < $share_data['level_id']) {
            return [
                'code' => 13,
                'msg' => '会员有等级不足'
            ];
        }
        return [
            'code' => 1,
            'msg' => '可以参与'
        ];
    }

    /**
     * 创建任务
     * @param $share_id
     * @param $user_id
     * @param $explain
     * @param $img
     * @param $ip
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function addShareTask($share_id, $user_id, $explain, $img, $ip)
    {
        $out_data = $this->isCondition($user_id, $share_id);
        if ($out_data['code'] != 1) {
            return $out_data;
        }
        $share_data = ShareModel::where('id', $share_id)->where('status', 1)->find();
        if (empty($share_data)) {
            return [
                'code' => 10,
                'msg' => '任务结束'
            ];
        }
        $account_type = ShareModel::$ACCOUNT_TYPE_NAME;
        $account_type_str = $account_type[$share_data['account_type']];
        $in_data = [
            'share_id' => $share_id,
            'user_id' => $user_id,
            'explain' => $explain,
            'img' => $img,
            'ip' => $ip,
            'account_type' => $share_data['account_type'],
            'money' => $share_data['money'],
            'details' => "奖励{$account_type_str}{$share_data['money']}USDT【佣金{$share_data['money']}到{$account_type_str}】",
            'status' => 1,
            'create_time' => time(),
            'update_time' => time()
        ];
        $ret = $this->insertGetId($in_data);
        if ($ret == false) {
            return [
                'code' => 11,
                'msg' => '任务创建失败'
            ];
        }
        $is_auto_check = \app\common\model\ConfigModel::getConfigValue('is_auto_check');
        if ($is_auto_check == 1) {
            $out_data = $this->isAutoCondition($user_id, $share_id);
            if ($out_data['code'] == 1) {
                $this->check([$ret], '', 2);
            }
        }
        return [
            'code' => 1,
            'data' => $ret,
            'msg' => '任务创建成功'
        ];
    }

    /**
     * 手动审核操作
     * @param $ids
     * @param $remark
     * @param $status
     * @param int $admin_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function check($ids, $remark, $status = 2, $admin_id = 0)
    {
        if ($status == 2) {
            foreach ($ids as $value) {
                $this->endTask($value, $remark, $admin_id);
            }
        }
        if ($status == 3) {
            foreach ($ids as $value) {
                $this->refuseTask($value, $remark, $admin_id);
            }
        }
        return true;
    }

    /**
     * 审核完成任务
     * @param $id
     * @param $remark
     * @param int $admin_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function endTask($id, $remark, $admin_id = 0)
    {
        $map = [
            'status' => 1
        ];
        $item = $this->whereIn('id', $id)->where($map)->find();
        if (empty($item)) {
            return false;
        }
        $money = $item['money'];
        $user_id = $item['user_id'];
        if (floatval($money) <= 0) {
            return false;
        }
        $account_type = $item['account_type'];
        $money_log_model = new MoneyLogModel();
        switch ($account_type) {
            case ShareModel::ACCOUNT_TYPE_BASE:
                $money_log_model->changeMoney($user_id, $money, MoneyLogModel::TYPE_USER, 'add', '分享奖励', '分享奖励_' . $money, 8, $admin_id, '', $item['id']);
                break;
            case ShareModel::ACCOUNT_TYPE_TYJ:
                $money_log_model->changeMoney($user_id, $money, MoneyLogModel::TYPE_TYJ, 'add', '分享奖励', '分享奖励_' . $money, 8, $admin_id, '', $item['id']);
                break;
            case ShareModel::ACCOUNT_TYPE_BROKERAGE:
                $money_log_model->changeMoney($user_id, $money, MoneyLogModel::TYPE_BROKERAGE, 'add', '分享奖励', '分享奖励_' . $money, 8, $admin_id, '', $item['id']);
                break;
        }
        $up_data = [
            'remark' => $remark,
            'status' => 2,
            'check_admin' => $admin_id,
            'check_time' => time(),
        ];
        $ret = $this->whereIn('id', $id)->update($up_data);
        if ($ret == false) {
            return false;
        }
        $money_log_model->allotBrokerage($user_id, $money, 3, false);
        return true;
    }

    /**
     * 拒绝分享任务
     * @param $id
     * @param $remark
     * @param int $admin_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function refuseTask($id, $remark, $admin_id = 0)
    {
        $map = [
            'status' => 1
        ];
        $item = $this->whereIn('id', $id)->where($map)->find();
        if (empty($item)) {
            return false;
        }
        $up_data = [
            'remark' => $remark,
            'status' => 3,
            'check_admin' => $admin_id,
            'check_time' => time(),
        ];
        $ret = $this->whereIn('id', $id)->update($up_data);
        if ($ret == false) {
            return false;
        }
        return true;
    }
}
