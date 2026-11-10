<?php

namespace app\common\model;

use FacebookAds\Http\Util;
use think\Model;

class HomeUserBalanceModel extends Model
{

    protected $autoWriteTimestamp = true;
    // 确定链接表名
    protected $name = 'home_user_balance';


    /**
     * 获取余额宝信息
     * @param $user_id
     * @return array
     */
    public static function getBalance($user_id)
    {
        $money_log = new MoneyLogModel();
        $map = [
            'user_id' => $user_id,
            'type' => MoneyLogModel::TYPE_USER,
            'change' => 1,
            'detail_type' => 14,
        ];

        //收益率
        $ratio = ConfigModel::getConfigValue('balance_money_settle_ratio');
        //累计收益
        $total_balance = $money_log->where($map)->sum('money');

        $yesterday = date("Y-m-d") . " 02:00:00";
        //昨日收益
        $yesterday_balance = $money_log->where($map)->where('create_time', '>', strtotime($yesterday))->sum('money');

        //余额宝余额
        $balance_money = UserModel::where('id', $user_id)->value('balance_money');

        return [
            'ratio' => $ratio,
            'yesterday_balance' => round($yesterday_balance, 2),
            'total_balance' => round($total_balance, 2),
            'balance_money' => round($balance_money, 2)
        ];
    }

    /**
     * 获取余额宝收益记录
     * @param $user_id
     * @param $page
     * @param $limit
     * @param $start_time
     * @param $end_time
     * @return array|bool|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getBalanceLog($user_id, $page, $limit, $start_time = '', $end_time = '')
    {
        $map = [
            'user_id' => $user_id,
            'type' => MoneyLogModel::TYPE_USER,
            'change' => 1,
            'detail_type' => 14,
        ];
        if (!empty($start_time) && !empty($end_time)) {
            $start_time = strtotime($start_time);
            $end_time = strtotime($end_time);
            $map['create_time'] = ['between', [$start_time, $end_time]];
        } else {
            $start_time = strtotime("-7 days", time());
            $end_time = time();
            $map['create_time'] = ['between', [$start_time, $end_time]];
        }
        $day = [
            'start_time' => date("Y-m-d", $start_time),
            'end_time' => date("Y-m-d", $end_time),
        ];
        $list_data = MoneyLogModel::where($map)->order('id desc')->field('id,money,create_time')->paginate(['page' => $page, 'list_rows' => $limit]);
        $list = $list_data->items();
        if (empty($list)) {
            return [
                'day' => $day,
                'total_money' => 0,
                'list' => []
            ];
        } else {
            $total_money = 0;
            foreach ($list as $k => $v) {
                $total_money += $v['money'];
                $list[$k]['money'] = round($v['money'], 2);
                $list[$k]['day'] = date('m-d', strtotime($v['create_time']));
            }
            return [
                'day' => $day,
                'total_money' => round($total_money, 2),
                'list' => $list
            ];
        }
    }

    /**
     * 充值余额宝
     * @param $user_id
     * @param $money
     * @return \think\response\Json
     */
    public static function addBalance($user_id, $money)
    {
        $is_open = ConfigModel::getConfigValue('is_open_balance_money');
        if ($is_open !== '1') {
            //余额宝关闭
            return json(msg(-1, '', lang('余额宝已关闭')));
        }
        // `id` int NOT NULL AUTO_INCREMENT,
        //  `user_id` int DEFAULT '0' COMMENT '用户id',
        //  `money` decimal(20,8) DEFAULT '0.00000000' COMMENT '存储金额',
        //  `time` int DEFAULT '0' COMMENT '时间格式 2024121115  年月日时',
        //  `status` tinyint(1) DEFAULT '1' COMMENT '状态  1未提款 2已提款',
        //  `create_time` int DEFAULT '0' COMMENT '充值时间',
        //  `update_time` int DEFAULT '0' COMMENT '更新时间',
        if (empty($money)) {
            return json(msg(-1, '', lang('充值金额不能为空')));
        }

        $data_money = UserModel::where('id', $user_id)->field('user_money')->find();

        if (($data_money['user_money']) < $money) {
            return json(msg(-1, '', lang('余额不足')));
        }
        $money_log = new MoneyLogModel();
        $res = $money_log->changeMoney($user_id, -1 * $money, MoneyLogModel::TYPE_USER, 'sub', '余额宝转入', '', 13);
        if ($res['code'] !== 1) {
            return json(msg(-1, '', lang($res['msg'])));
        }
        $res = $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_BALANCE, 'add', '余额宝转入', '', 13);
        if ($res['code'] !== 1) {
            $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_USER, 'add', '余额宝转出', '', 13);
            return json(msg(-1, '', lang($res['msg'])));
        }
        $data = [
            'user_id' => $user_id,
            'money' => $money,
            'add_money' => $money,
            'hour' => date('H'),
            'time' => date('YmdH'),
            'settle_time' => date('Ymd'),
            'status' => 1,
            'create_time' => time(),
            'update_time' => time(),
        ];
        $res = self::create($data);
        if ($res) {
            return json(msg(1, '', lang('充值成功')));
        } else {
            return json(msg(-1, '', lang('充值失败')));
        }
    }

    /**
     * 提取余额宝存款
     * @param $user_id
     * @param $money
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function subBalance($user_id, $money)
    {
        $is_open = ConfigModel::getConfigValue('is_open_balance_money');
        if ($is_open !== '1') {
            //余额宝关闭
            return json(msg(-1, '', lang('余额宝已关闭')));
        }
        $money_log = new MoneyLogModel();
        $res = $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_BALANCE, 'sub', '余额宝转出', '', 13);
        if ($res['code'] !== 1) {
            return json(msg(-1, '', lang($res['msg'])));
        }
        $res = $money_log->changeMoney($user_id, -1 * $money, MoneyLogModel::TYPE_USER, 'add', '余额宝转出', '', 13);
        if ($res['code'] !== 1) {
            $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_BALANCE, 'add', '余额宝转入', '', 13);
            return json(msg(-1, '', lang($res['msg'])));
        }
        $list = self::where('user_id', $user_id)->where('status', 1)->order('id asc')->select();
        $balance = 0;
        $ids = [];
        $end_id = 0;
        $surplus = 0;
        foreach ($list as $k => $v) {
            $balance += $v['money'];
            if ($balance >= $money) {
                $surplus = $balance - $money;
                $end_id = $v['id'];
                break;
            }
            $ids[] = $v['id'];
        }
        if (empty($ids)) {
            if (!empty($end_id)) {
                $update = [
                    'money' => $surplus
                ];
                if (empty($surplus)) {
                    $update['status'] = 2;
                }
                self::where('id', $end_id)->update($update);
                return json(msg(1, '', lang('提款成功')));
            }
        }
        $ret = self::where('id', 'in', $ids)->update(['status' => 2]);
        if ($ret) {
            if (!empty($end_id)) {
                $update = [
                    'money' => $surplus
                ];
                if (empty($surplus)) {
                    $update['status'] = 2;
                }
                self::where('id', $end_id)->update($update);
            }
            return json(msg(1, '', lang('提款成功')));
        } else {
            return json(msg(-1, '', lang('提款失败')));
        }
    }

    /**
     * 结算余额宝收益
     * @param int $time 时间 24小时至0点
     * @param float $rate 收益率
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function settleBalance()
    {
        $is_open = ConfigModel::getConfigValue('is_open_balance_money');
        if ($is_open !== '1') {
            //余额宝关闭不予结算
            return true;
        }
        $time = ConfigModel::getConfigValue('balance_money_settle_time');
        //收益率
        $rate = ConfigModel::getConfigValue('balance_money_settle_ratio') / 100;
//        $map = [
//            'status' => 1,
//            'settle_time' => ['<>', date('Ymd')]
//        ];
        $day = date('Ymd');
        $sql = "(status=1 AND settle_time <> $day AND hour <= $time) OR (status=1 AND settle_time <> $day AND hour > $time AND time < " . date('YmdH', strtotime('-1 day')) . ")";
        $list = self::where($sql)->group('user_id')->field('user_id,sum(money) as sum_money')->order('id asc')->limit(1000)->select();
        foreach ($list as $k => $v) {
            // 判断用户余额是否为空
            $balance_money = UserModel::where('id', $v['user_id'])->value('balance_money');
            if (empty($balance_money)) {
                //余额宝为空 记录有误 清空记录不予结算
                self::where($sql)->where('user_id', $v['user_id'])->update(['status' => 2]);
                continue;
            }
            $money = $v['sum_money'] * $rate;
            $money_log = new MoneyLogModel();
            $res = $money_log->changeMoney($v['user_id'], $money, MoneyLogModel::TYPE_USER, 'add', '余额宝收益', '', 14);
            if ($res['code'] == 1) {
                self::where($sql)->where('user_id', $v['user_id'])->update(['settle_time' => date('Ymd')]);
            }
        }
        return true;
    }

    /**
     * 结算余额宝收益
     * @param int $time 时间 24小时至0点
     * @param float $rate 收益率
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function settleBalanceTime()
    {
        $is_open = ConfigModel::getConfigValue('is_open_balance_money');
        if ($is_open !== '1') {
            //余额宝关闭不予结算
            return true;
        }
        //结算周期
        $cycle = ConfigModel::getConfigValue('balance_money_settle_cycle');
        //结算次数
        $count = ConfigModel::getConfigValue('balance_money_settle_count');
        $count = intval($count) - 1;
        //收益率
        $rate = ConfigModel::getConfigValue('balance_money_settle_ratio') / 100;
        $map = [
            'status' => 1,
            'money' => 0
        ];
        //刷新无效记录
        self::where($map)->update(['status' => 2]);

        $day_hour = date('YmdH');
        //两小时之前的
        $day_hour_settle = $day_hour - $cycle;
        $sql = "(m.status=1 AND (m.settle_time_hour = 0 OR m.settle_time_hour <= $day_hour_settle) AND m.time <= $day_hour_settle AND m.settle_count <= $count)";
        $list = self::where($sql)->alias('m')->group('user_id')->join('home_user u', 'u.id = m.user_id')->field('m.user_id,m.settle_count,sum(m.money) as sum_money,u.balance_money')->order('m.id asc')->limit(1000)->select();
        echo "处理余额宝收益记录数:" . self::getLastSql();
        echo "处理余额宝收益记录数:" . (count($list));
        foreach ($list as $k => $v) {
            // 判断用户余额是否为空
            if (floatval($v['balance_money']) == 0) {
                //余额宝为空 记录有误 清空记录不予结算
                self::where('user_id', $v['user_id'])->update(['status' => 2]);
                continue;
            }
            $money = $v['sum_money'] * $rate;
            $money_log = new MoneyLogModel();
            $res = $money_log->changeMoney($v['user_id'], $money, MoneyLogModel::TYPE_USER, 'add', '余额宝收益', '', 14);
            if ($res['code'] == 1) {
                $settle_count = $v['settle_count'] + 1;
                $where_sql = "(user_id = ".$v['user_id']." AND status = 1 AND (settle_time_hour = 0 OR settle_time_hour <= $day_hour_settle) AND time <= $day_hour_settle AND settle_count <= $count)";
                if ($settle_count > $count) {
                    $settle_count = 0;
                    self::where($where_sql)->update(['settle_time' => date('Ymd'), 'settle_time_hour' => date("Ymd", strtotime('+1 day')) . "00", 'settle_count' => $settle_count]);
                } else {
                    self::where($where_sql)->update(['settle_time' => date('Ymd'), 'settle_time_hour' => date("YmdH"), 'settle_count' => $settle_count]);
                }
            }
        }
        return true;
    }
}
