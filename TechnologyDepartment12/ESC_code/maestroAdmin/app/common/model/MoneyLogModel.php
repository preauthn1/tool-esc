<?php

namespace app\common\model;

use app\admin\model\LevelModel;
use app\admin\model\MemberModel;
use app\common\repository\FinanceProductOrderRepository;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * Class MoneyLogModel
 * @package app\common\model
 */
class MoneyLogModel extends Model
{

    const TYPE_USER = 1;//基础钱包(可转换到收益)
    const TYPE_RECHARGE = 2;//充值钱包（用于记录系统操作的充值金额）
    const TYPE_TYJ = 3;//彩金钱包
    const TYPE_FROZEN = 4;//提现冻结金额钱包
    const TYPE_BROKERAGE = 1;//佣金金额钱包（可直接提现）合并到 基础钱包
    const TYPE_EARNINGS = 6;//总收益(佣金累计）
    const TYPE_BALANCE = 7;//余额宝钱包
    const TYPE_BROKERAGE_POOL = 8;//佣金池
    const TYPE_WITHDRAWAL = 9;//提现钱包（用于记录累计提现金额）

    static $TYPE_FIELDS = [
        self::TYPE_USER => "user_money",
        self::TYPE_RECHARGE => "recharge_money",
        self::TYPE_TYJ => "tyj_money",
        self::TYPE_FROZEN => "frozen_money",
//        self::TYPE_BROKERAGE => "brokerage_money",
        self::TYPE_EARNINGS => "earnings_money",
        self::TYPE_BALANCE => "balance_money",
        self::TYPE_BROKERAGE_POOL => "brokerage_pool",
        self::TYPE_WITHDRAWAL => "withdrawal_amount",
    ];

    static $TYPE_NAMES = [
        self::TYPE_USER => "基础钱包",
        self::TYPE_RECHARGE => "充值钱包",
        self::TYPE_TYJ => "彩金钱包",
        self::TYPE_FROZEN => "冻结钱包",
//        self::TYPE_BROKERAGE => "佣金钱包",
        self::TYPE_EARNINGS => "总收益",
        self::TYPE_BALANCE => "余额宝钱包",
        self::TYPE_BROKERAGE_POOL => "佣金池",
        self::TYPE_WITHDRAWAL => "提现钱包",
    ];

//明细类型 0未定义 1用户充值 2用户提现 3任务返利 4储值返利(充值送款） 5抽奖活动 6签到活动 7升级返利 8分享奖励 9一级返利 10二级返利 11三级返利 12广告位理财
// 13AI智能托管理财 14AI智能托管收益 15直推奖励 16广告位收益 17 解锁vip 18 解锁vip失败 19团队活跃奖 20系统自动修正 21 奖励发放错误 22-转账给其他用户
// 23-收到其他用户转账 24-连续推广奖励 25-领取佣金池佣金 26-充值送金，27-领取团队活跃奖励 28-领取体验金 29-扣除体验金 30-交易费率
    static $DETAIL_TYPE_NAMES = [
        0 => '未定义',
        1 => '用户充值',
        2 => '提现申请',
        3 => '任务返佣',
        4 => '首充送款',
        5 => '抽奖活动',
        6 => '签到活动',
        7 => '升级返佣',
        8 => '分享奖励',
        9 => '任务返利-1级别',
        10 => '任务返利-2级别',
        11 => '任务返利-3级别',
        12 => '库存买入',
        13 => 'AI智能托管',
        14 => 'AI智能托管收益',
        15 => "当日直推奖励",
        16 => '收益结算',
        17 => '升级会员',
        19 => '团队活跃奖',
        20 => '系统自动修正',
        21 => '奖励发放错误',
        22 => '转账给其他用户',
        23 => '收到其他用户转账',
        24 => '连续推广奖励',
        25 => '领取佣金池佣金',
        26 => '充值送金',
        27 => '领取团队活跃奖励',
        28 => '体验券领取',
        29 => '体验券到期回收',
        30 => '交易费率'
    ];

    // 确定链接表名
    protected $name = 'money_log';

    /**
     * 状态常量
     */
    const STATUS_SHOW = 0; // 隐藏
    const STATUS_HIDDEN = 1;  // 显示

    /**
     * 状态映射
     */
    public static $statusMap = [
        self::STATUS_HIDDEN => '隐藏',
        self::STATUS_SHOW => '显示',
    ];

    /**
     * 钱包转化
     * @param $user_id
     * @param int $type
     * @param int $to_type
     * @param float $money 转行金额
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function transformMoney($user_id, $type = self::TYPE_USER, $to_type = self::TYPE_BROKERAGE, $money = 0)
    {
        $home_user = new UserModel();
        $fields = implode(',', self::$TYPE_FIELDS);
        $user_data = $home_user->where('id', $user_id)->field($fields)->find();
//        $money = $user_data[self::$TYPE_FIELDS[$type]];
        $to_money = $user_data[self::$TYPE_FIELDS[$to_type]];
        if (floatval($money) == 0) {
            $data['code'] = 1;
            $data['msg'] = "转化成功";
            $data['data'] = [
                'money' => $money,
                'to_money' => $to_money + $money,
            ];
            return $data;
        }
        $remarks = self::$TYPE_NAMES[$type] . "转" . self::$TYPE_NAMES[$to_type];
        $data = $this->changeMoney($user_id, $money, $to_type, 'add', '钱包金额转化', $remarks);
        if ($data['code'] == 1) {
            $this->changeMoney($user_id, $money, $type, 'sub', '钱包金额转化', $remarks);
        }
        $data['data'] = [
            'money' => $money,
            'to_money' => $to_money + $money,
        ];
        return $data;
    }

    /**
     * 操作钱包数据
     * @param $user_id //用户id
     * @param $money //变动金额
     * @param int $type //钱包类型
     * @param string $change //改变方式 add 增加, sub扣除
     * @param string $title //标题
     * @param string $remarks //备注信息
     * @param int $detail_type //明细类型 0未定义 1用户充值 2用户提现 3任务返利 4储值返利(充值送款） 5抽奖活动 6签到活动
     * 7升级返利 8分享奖励 9一级返利 10二级返利 11三级返利 12库存买入 13AI智能托管理财 14AI智能托管收益
     * 15直推奖励 16广告位收益 17 解锁vip 18 解锁vip失败 19团队活跃奖 20系统自动修正 21 奖励发放错误 22-转账给其他用户
     * 23-收到其他用户转账 24 连续推广奖励 25领取佣金池佣金 26充值送金奖励 27-领取团队活跃奖励 28-领取体验金 29-扣除体验金 30-交易费率
     * @param string $admin_id //操作管理员
     * @param string $from_user_id //用户来源
     * @param string $order_id //订单来源
     * @param boolean $is_log //是否需要写入记录
     * @param int $is_xuni //是否虚拟用户
     * @param int $is_negative //是否校验余额足够
     * @return array
     */
    public function changeMoney($user_id, $money, $type = self::TYPE_USER, $change = 'add', $title = '', $remarks = '', $detail_type = 0, $admin_id = '', $from_user_id = '', $order_id = '', $is_log = true, $is_xuni = 0, $is_negative = false)
    {
        if (floatval($money) == 0) {
            return [
                'code' => 9,
                'msg' => '金额不能为空'
            ];
        }
        $data = [
            'is_xuni' => $is_xuni,
            'user_id' => $user_id,
            'title' => $title,
            'remarks' => $remarks,
            'admin_id' => $admin_id,
            'change' => $change == 'add' ? 1 : 2,
            'from_user_id' => $from_user_id,
            'order_id' => $order_id,
            'detail_type' => $detail_type,
        ];
        $money = abs($money);
        if ($change == 'add') {
            $change_money = $money;
        } else {
            $change_money = -$money;
        }
        $data['money'] = $change_money;
        $data = $this->addMoneyLog($data, $type, $is_log, $is_negative);
        return $data;
    }

    /**
     * 获取用户各钱包数据
     * @param $user_id
     * @return array|bool|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserMoney($user_id)
    {
        $home_user = new UserModel();
        $fields = implode(',', self::$TYPE_FIELDS);
        $user_data = $home_user->where('id', $user_id)->field($fields)->find();
        if (empty($user_data)) {
            $user_data = [];
            foreach (self::$TYPE_FIELDS as $v) {
                $user_data[$v] = 0;
            }
            return $user_data;
        }
        return $user_data;
    }

    /**
     * 分配佣金
     * @param $user_id 客户
     * @param $level 级别
     * @param $money 佣金
     * @param $recharge 是否充值返利
     */
    public function allotBrokerage($user_id, $money, $level = 3, $recharge = true)
    {
        $parent = [];
        $temp_id = $user_id;
        $money = floatval($money);
        //获取
        for ($i = 0; $i < $level; $i++) {
            $temp_id = UserModel::getParentId($temp_id);
            if (!empty($temp_id)) {
                $parent[$i] = $temp_id;
            }
        }
        if (empty($parent)) {
            return true;
        }
        $level_id = UserModel::getLevelId($user_id);
        $level_data = LevelModel::where('level_id', $level_id)->field('recharge_rebate,task_rebate')->find();

        $recharge_rebate = str_replace('[', '', $level_data['recharge_rebate']);
        $recharge_rebate = str_replace(']', '', $recharge_rebate);
        $recharge_rebate_arr = explode(',', $recharge_rebate);

        $task_rebate = str_replace('[', '', $level_data['task_rebate']);
        $task_rebate = str_replace(']', '', $task_rebate);
        $task_rebate_arr = explode(',', $task_rebate);
        $money_log_model = new MoneyLogModel();
        if ($recharge) {
            //充值返利
            //团队充值奖励账户，1.基础，2.佣金。
            $give_team_brokerage_account = ConfigModel::getConfigValue('give_team_brokerage_account');
            //返利
            for ($i = 0; $i < $level; $i++) {
                if (isset($parent[$i])) {
                    $user_id = $parent[$i];
                    $temp_rebate = isset($recharge_rebate_arr[$i]) ? $recharge_rebate_arr[$i] : 0;
                    $temp_rebate = floatval($temp_rebate);
                    $money_temp = round($money * ($temp_rebate / 100), 2);//获得返利
                    $type = MoneyLogModel::TYPE_USER;
                    if ($give_team_brokerage_account == 2) {
                        $type = MoneyLogModel::TYPE_USER;
                    }
                    $title = "充值返利-" . ($i + 1) . "级别";
                    $money_log_model->changeMoney($user_id, $money_temp, $type, 'add', $title, $title . '_' . $money_temp, $i + 9);
                }
            }
        } else {
            for ($i = 0; $i < $level; $i++) {
                if (isset($parent[$i])) {
                    $user_id = $parent[$i];
                    $temp_rebate = isset($recharge_rebate_arr[$i]) ? $recharge_rebate_arr[$i] : 0;
                    $temp_rebate = floatval($temp_rebate);
                    $money_temp = round($money * ($temp_rebate / 100));//获得返利
                    $type = MoneyLogModel::TYPE_USER;
                    $title = "任务返利-" . ($i + 1) . "级别";
                    $money_log_model->changeMoney($user_id, $money_temp, $type, 'add', $title, $title . '_' . $money_temp, $i + 9);
                }
            }
        }
        return true;
    }

    /**
     * 增加钱包日志
     * @param $data
     * @param int $type
     * @param boolean $is_log 是否需要写入记录
     * @return array
     */
    private function addMoneyLog($data, $type = self::TYPE_USER, $is_log = true, $is_negative = false)
    {
        $home_user = new UserModel();
        $fields = implode(',', self::$TYPE_FIELDS);
        $user_data = $home_user->where('id', $data['user_id'])->field($fields)->find();
        if (empty($user_data)) {
            return [
                'code' => 23,
                'msg' => '操作失败'
            ];
        }
        $field = self::$TYPE_FIELDS[$type];
        $user_money = $user_data[self::$TYPE_FIELDS[$type]];
        $now_money = floatval($data['money'] + $user_money);
        if ($type != MoneyLogModel::TYPE_USER || $is_negative == true) {
            if ($now_money < 0) {
                return [
                    'code' => 21,
                    'msg' => '操作失败，金额不够'
                ];
            }
        }

        $home_user->startTrans();
        $up_data = [
            $field => $now_money,
        ];
        //fields not exists:[user_money]
        try {
            $ret = $home_user->where('id', $data['user_id'])->update($up_data);
            if ($ret) {
                $in_data = [
                    'user_id' => $data['user_id'], // 用户ID
                    'is_xuni' => $data['is_xuni'] == 1 ? 1 : 0,
                    'user_money' => $user_money,//操作前余额
                    'last_money' => $now_money,//当前余额
                    'money' => abs($data['money']), // 变动金额
                    'change' => $data['change'], // 金额
                    'remarks' => $data['remarks'], // 备注
                    'title' => $data['title'], // 标题
                    'type' => $type, // 类型
                    'detail_type' => $data['detail_type'], // 明细类型
                    'from_user_id' => $data['from_user_id'], // 来自用户
                    'order_id' => $data['order_id'], // 订单ID
                    'admin_id' => $data['admin_id'], // 操作人员ID
                    'create_time' => time()    // 创建时间
                ];
                if ($is_log) {
                    $ret = $this->insertGetId($in_data);
                    if ($ret == false) {
                        $home_user->rollback();
                        return [
                            'code' => 22,
                            'msg' => '操作失败'
                        ];
                    }
                }
                $home_user->commit();
                // 恢复已匹配未支付的广告位订单
                (new FinanceProductOrderRepository())->recoverOrder($data['user_id']);
                return [
                    'code' => 1,
                    'msg' => '操作成功'
                ];
            }
        } catch (\Exception $e) {
            $home_user->rollback();
            return [
                'code' => 22,
                'msg' => '操作失败'
            ];
        }
        return [
            'code' => 22,
            'msg' => '操作失败'
        ];
    }

    /**
     * 获取储值返利
     * @param $user_id
     * @return float|int|string
     */
    public static function getRechargeRebate($user_id)
    {
        $map = [
            'user_id' => $user_id,
            'detail_type' => 4,
            'change' => 'add',
        ];
        return self::where($map)->sum('money');
    }

    /**
     * 获取任务返利
     * @param $user_id
     * @return float|int|string
     */
    public static function getTaskRebate($user_id)
    {
        $map = [
            'user_id' => $user_id,
            'detail_type' => 3,
            'change' => 'add',
        ];
        return self::where($map)->sum('money');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'user_id', 'id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'from_user_id', 'id');
    }

    public function toggleStatus($id)
    {
        $info = $this->where('id', $id)->find();
        if (!$info) {
            return false;
        }

        return $this->where('id', $id)->update([
            'status' => $info['status'] == self::STATUS_SHOW ? self::STATUS_HIDDEN : self::STATUS_SHOW,
        ]);
    }
}
