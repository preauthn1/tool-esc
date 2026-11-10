<?php

namespace app\common\model;

use think\Model;

/**
 * 虚拟用户钱包日志
 * Class HomeUserVirtualLogModel
 * @package app\common\model
 */
class HomeUserVirtualLogModel extends Model
{

    const TYPE_USER = 1;//基础钱包(可转换到收益)
    const TYPE_RECHARGE = 2;//充值钱包（用于记录系统操作的充值金额）
    const TYPE_TYJ = 3;//彩金钱包
    const TYPE_FROZEN = 4;//提现冻结金额钱包
    const TYPE_BROKERAGE = 5;//佣金金额钱包（可直接提现）合并到 基础钱包
    const TYPE_EARNINGS = 6;//总收益(佣金累计）
    const TYPE_BALANCE = 7;//余额宝钱包

    static $TYPE_FIELDS = [
        self::TYPE_USER => "user_money",
        self::TYPE_RECHARGE => "recharge_money",
        self::TYPE_TYJ => "tyj_money",
        self::TYPE_FROZEN => "frozen_money",
        self::TYPE_BROKERAGE => "brokerage_money",
        self::TYPE_EARNINGS => "earnings_money",
        self::TYPE_BALANCE => "balance_money",
    ];

    static $TYPE_NAMES = [
        self::TYPE_USER => "基础钱包",
        self::TYPE_RECHARGE => "充值钱包",
        self::TYPE_TYJ => "彩金钱包",
        self::TYPE_FROZEN => "冻结钱包",
        self::TYPE_BROKERAGE => "佣金钱包",
        self::TYPE_EARNINGS => "总收益",
        self::TYPE_BALANCE => "余额宝钱包",
    ];
    // 确定链接表名
    protected $name = 'home_user_virtual_log';

    /**
     * 操作钱包数据
     * @param $user_id //用户id
     * @param $money //变动金额
     * @param int $type //钱包类型
     * @param string $change //改变方式 add 增加, sub扣除
     * @param string $title //标题
     * @param string $remarks //备注信息
     * @param int $detail_type //明细类型 0未定义 1用户充值 2用户提现 3任务返利 4储值返利(充值送款） 5抽奖活动 6签到活动 7升级返利 8分享奖励 9一级返利 10二级返利 11三级返利 12广告位理财 13AI智能托管理财 14AI智能托管收益 15直推奖励 16广告位收益 17 解锁vip 18 解锁vip失败
     * @param string $admin_id //管理员id
     * @param boolean $is_log //是否需要写入记录
     * @return array
     */
    public function changeMoney($user_id, $money, $type = self::TYPE_USER, $change = 'add', $title = '', $remarks = '', $detail_type = 0, $admin_id = 0, $add_time = '', $is_log = true)
    {
        if (floatval($money) == 0) {
            return [
                'code' => 9,
                'msg' => '金额不能为空'
            ];
        }
        $data = [
            'user_id' => $user_id,
            'admin_id' => $admin_id,
            'title' => $title,
            'remarks' => $remarks,
            'change' => $change == 'add' ? 1 : 2,
            'detail_type' => $detail_type,
            'create_time' => empty($add_time) ? time() : strtotime($add_time),
        ];
        $money = abs($money);
        if ($change == 'add') {
            $change_money = $money;
        } else {
            $change_money = -$money;
        }
        $data['money'] = $change_money;
        $data = $this->addMoneyLog($data, $type, $is_log);
        return $data;
    }

    /**
     * 增加钱包日志
     * @param $data
     * @param int $type
     * @param boolean $is_log 是否需要写入记录
     * @return array
     */
    private function addMoneyLog($data, $type = self::TYPE_USER, $is_log = true)
    {
        $temp = $this->where('id', $data['user_id'])->order('id desc')->find();
        if (empty($temp)) {
            $home_virtual_user = new HomeUserVirtualModel();
            $user_money = $home_virtual_user->where('user_id', $data['user_id'])->value('user_money');

        } else {
            $user_money = $temp['last_money'];
        }
        $now_money = floatval($user_money + $data['money']);
        if ($data['change'] == 2) {
            if ($now_money < 0) {
                $now_money = 0;
            }
        }
        $in_data = [
            'user_id' => $data['user_id'], // 用户ID
            'admin_id' => $data['admin_id'], // 管理员ID
            'user_money' => $user_money,//操作前余额
            'last_money' => $now_money,//当前余额
            'money' => abs($data['money']), // 变动金额
            'change' => $data['change'], // 金额
            'remarks' => $data['remarks'], // 备注
            'title' => $data['title'], // 标题
            'type' => $type, // 类型
            'detail_type' => $data['detail_type'], // 明细类型
            'create_time' => $data['create_time']    // 创建时间
        ];
        if ($is_log) {
            $ret = $this->insertGetId($in_data);
            if ($ret == false) {
                return [
                    'code' => 22,
                    'msg' => '操作失败'
                ];
            }
        }
        return [
            'code' => 1,
            'msg' => '操作成功'
        ];
    }
}
