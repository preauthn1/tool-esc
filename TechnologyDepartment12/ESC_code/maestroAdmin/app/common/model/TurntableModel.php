<?php

namespace app\common\model;

use think\Model;

class TurntableModel extends Model
{
    const CAPITAL_TYPE_BASE = 1;//基础账号
    const CAPITAL_TYPE_BROKERAGE = 5;//佣金账号

    static $CAPITAL_TYPE_NAME = [
        self::CAPITAL_TYPE_BASE => "基础账号",
        self::CAPITAL_TYPE_BROKERAGE => "佣金账号",
    ];

    const STATUS_TYPE_OPEN = 1;//开启
    const STATUS_TYPE_CLOSE = 2;//关闭

    static $STATUS_TYPE_NAME = [
        self::STATUS_TYPE_OPEN => "开启",
        self::STATUS_TYPE_CLOSE => "关闭",
    ];

    const TYPE_TYPE_TURNTABLE = 1;//转盘抽奖
    const TYPE_TYPE_PACKET = 2;//红包抽奖

    static $TYPE_TYPE_NAME = [
        self::TYPE_TYPE_TURNTABLE => "转盘抽奖",
        self::TYPE_TYPE_PACKET => "红包抽奖",
    ];

    const PRIZE_TYPE_ALL = 1;//全局
    const PRIZE_TYPE_VIP = 2;//指定VIP

    static $PRIZE_TYPE_NAME = [
        self::PRIZE_TYPE_ALL => "全局",
        self::PRIZE_TYPE_VIP => "局部",
    ];

    // 确定链接表名
    protected $name = 'turntable';


    /**
     * 获取转盘数据
     * @param string $turntable_id
     * @return array|bool|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTurntable($turntable_id = '')
    {
        if (empty($turntable_id)) {
            $temp = $this->where([])->order('id DESC')->find();
        } else {
            $temp = $this->where('id', $turntable_id)->find();
        }
        if (empty($temp)) {
            return [];
        }
        return $temp;
    }

    /**
     * 用户抽奖
     * @param $user_id
     * @param $turntable_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function startTurntable($user_id, $turntable_id = 1)
    {
        $map = [
            'id' => $turntable_id,
            'status' => 1,
        ];
        $turntable = $this->where($map)->find();
        if (empty($turntable)) {
            return [
                'code' => 10,
                'msg' => '活动已结束'
            ];
        }
        $turntable_count = TurntableCountModel::getTurntableCount($user_id, $turntable_id);
        if (empty($turntable_count)) {
            return [
                'code' => 11,
                'msg' => '抽奖次数不足'
            ];
        }
        $type = 1;
        if ($turntable_count['reg_num'] > 0) {
            $type = 1;

        } elseif ($turntable_count['num'] > 0) {
            $type = 2;
        }
        $level_id = $turntable['prize'] == 1 ? -1 : UserModel::getLevelId($user_id);
        if (TurntableCountModel::useTurntableCount($user_id, $type, $turntable_id)) {
            $prize = TurntablePrizeModel::getPrize($turntable_id, $type, $level_id);
            if (empty($prize)) {
                return [
                    'code' => 12,
                    'msg' => '奖品已抽完'
                ];
            }
            if (TurntableLogModel::addTurntableLog($user_id, $prize['id'], $prize['value'], $prize['name'], $type)) {
                if (floatval($prize['value']) > 0) {
                    $capital_type = $turntable['capital_type'];
                    $money_log_model = new MoneyLogModel();
                    $title = '转盘抽奖';
                    $remarks = '转盘抽奖 ' . $prize['value'];
                    $money_log_model->changeMoney($user_id, floatval($prize['value']), $capital_type, 'add', $title, $remarks, 5);
                }
                $turntable_count = TurntableCountModel::getTurntableCount($user_id, $turntable['id']);
                $prize['count'] = $turntable_count['reg_num'] + $turntable_count['num'];
                return [
                    'code' => 0,
                    'msg' => '抽奖成功',
                    'data' => $prize
                ];
            }
            return [
                'code' => 12,
                'msg' => '抽奖失败'
            ];
        }
        return [
            'code' => 11,
            'msg' => '抽奖次数不足'
        ];
    }

}
