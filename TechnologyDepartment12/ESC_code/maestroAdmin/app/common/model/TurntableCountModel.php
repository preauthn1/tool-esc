<?php

namespace app\common\model;

use app\admin\model\MemberModel;
use app\admin\model\RechargeModel;
use app\common\repository\FinanceProductOrderRepository;
use Carbon\Carbon;
use think\facade\Cache;
use think\Model;

class TurntableCountModel extends Model
{
    // 确定链接表名
    protected $name = 'turntable_count';

    /**
     * 判断是否可以添加转盘次数
     * @param $user_id
     * @param int $turntable_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function isAddPCount($user_id, $turntable_id = 1)
    {
        // 获取充值金额
        $money = RechargeModel::getRecharge($user_id);
        $turntable_model = new TurntableModel();
        $turntable = $turntable_model->getTurntable($turntable_id);
        if ($money < floatval($turntable['invite_recharge'])) {
            return false;
        }
        $p_data = UserModel::where('id', $user_id)->find();
        if (isset($p_data->leader1) && $p_data->leader1 > 0) {
            //下级用户充值送一次抽奖次数
            return TurntableCountModel::addTurntableCount($p_data->leader1, 'num', $turntable_id, $user_id);
        }
        return false;
    }

    /**
     * 判断是否可以添加转盘次数
     * @param $user_id
     * @param int $turntable_id
     * @param int $money 单次充值金额
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function isAddOnePCount($user_id, $turntable_id = 1, $money = 0)
    {
        // 获取充值金额
        $turntable_model = new TurntableModel();
        $turntable = $turntable_model->getTurntable($turntable_id);
        if ($money < floatval($turntable['invite_recharge'])) {
            return false;
        }
        $p_data = UserModel::where('id', $user_id)->find();
        if (isset($p_data->leader1)) {
            //下级用户充值送一次抽奖次数
            return TurntableCountModel::addTurntableCount($p_data->leader1, 'num', $turntable_id, $user_id);
        }
        return false;
    }

    /**
     * 获取转盘次数
     * @param $user_id
     * @param $turntable_id
     * @return array|bool|int[]|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getTurntableCount($user_id, $turntable_id)
    {
        $map = [
            'user_id' => $user_id,
            'turntable_id' => $turntable_id
        ];
        $temp = self::where($map)->field('reg_num,num')->find();
        if (empty($temp)) {
            $data = [
                'user_id' => $user_id,
                'turntable_id' => $turntable_id,
                'from_user_id' => json_encode([]),
                'reg_num' => 0,
                'num' => 0,
                'create_time' => time(),
                'update_time' => time(),
            ];
            self::insert($data);
            return [
                'reg_num' => 0,
                'num' => 0,
                'count' => 0
            ];
        }
        $temp['count'] = intval($temp['reg_num'] + $temp['num']);
        return $temp;
    }

    /**
     * 添加转盘次数
     * @param $user_id
     * @param $type //reg 注册次数 num 邀请次数
     * @param int $turntable_id
     * @param string $from_id
     * @param string $is_rep 是否重复
     * @param string $remark 备注信息
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function addTurntableCount($user_id, $type, $turntable_id = 1, $from_id = '', $is_rep = true, $remark = '')
    {
        $map = [
            'user_id' => $user_id,
            'turntable_id' => $turntable_id
        ];
        $temp = self::where($map)->find();
        if (empty($temp)) {
            if (!empty($from_id)) {
                $from_user_id = json_encode([$from_id]);
            } else {
                $from_user_id = json_encode([]);
            }
            $turntable_model = new TurntableModel();
            $turntable = $turntable_model->getTurntable($turntable_id);
            if ($type == 'reg') {
                $data = [
                    'user_id' => $user_id,
                    'turntable_id' => $turntable_id,
                    'from_user_id' => $from_user_id,
                    'reg_num' => $turntable['register_num'],
                    'remark' => $remark,
                    'create_time' => time(),
                    'update_time' => time(),
                ];
            } else {
                $data = [
                    'user_id' => $user_id,
                    'turntable_id' => $turntable_id,
                    'from_user_id' => $from_user_id,
                    'num' => 1,
                    'remark' => $remark,
                    'create_time' => time(),
                    'update_time' => time(),
                ];
            }
            $ret = self::insert($data);
            if ($ret) {
                return true;
            }
            return false;
        } else {
            $from_user_arr = json_decode($temp['from_user_id'], true);
            if ($is_rep) {
                if (in_array($from_id, $from_user_arr)) {
                    return false;
                }
            }
            if (!empty($from_id)) {
                $from_user_arr[] = $from_id;
            }
            $from_user_arr = array_unique($from_user_arr);
            $from_user_id = json_encode($from_user_arr);
            if ($type == 'reg') {
                $data = [
                    'from_user_id' => $from_user_id,
                    'reg_num' => $temp['reg_num'] + 1,
                    'remark' => $remark,
                    'update_time' => time(),
                ];
            } else {
                $data = [
                    'from_user_id' => $from_user_id,
                    'num' => $temp['num'] + 1,
                    'remark' => $remark,
                    'update_time' => time(),
                ];
            }
            $ret = self::where($map)->update($data);
            if ($ret) {
                return true;
            }
            return false;
        }
    }

    /**
     * 减少转盘次数
     * @param $user_id
     * @param $type 1注册 2邀请
     * @param int $turntable_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function useTurntableCount($user_id, $type, $turntable_id = 1)
    {
        $map = [
            'user_id' => $user_id,
            'turntable_id' => $turntable_id
        ];
        $temp = self::where($map)->find();
        if ($type == 1) {
            if ($temp['reg_num'] <= 0) {
                return false;
            }
            $data = [
                'reg_num' => $temp['reg_num'] - 1,
                'update_time' => time(),
            ];
        } else {
            if ($temp['num'] <= 0) {
                return false;
            }
            $data = [
                'num' => $temp['num'] - 1,
                'update_time' => time(),
            ];
        }
        $ret = self::where($map)->update($data);
        if ($ret) {
            return true;
        }
        return false;
    }

    // 昨日下级活跃给上级增加抽奖次数
    public function activeAddTurntableCount($date = '')
    {
        if (!$date) {
            // 获取昨天的日期
            $date = date('Y-m-d', strtotime('-1 days'));
        }
        $date_arr = [strtotime($date.'00:00:00'), strtotime($date.'23:59:59')];
        $cache_prefix = config('cache.stores.redis.prefix');
        $redis_lock_key_prefix = $cache_prefix.'active_add_turntable_count_lock_'.$date.'##';

        $apiRedis = Cache::store("redis")->handler();
        $keys = $apiRedis->keys($redis_lock_key_prefix.'*');
        $user_ids = [0];
        foreach ($keys as $key) {
            $user_ids[] = explode('##', $key)[1];
        }
        $user_ids = implode(',', $user_ids);
        // 获取$date有leader1的注册用户
        $users = MemberModel::where('leader1', '>', 0)
            ->where('leader1', 'not in', $user_ids)
            ->field('leader1')
            ->group('leader1')
            ->select();
        foreach ($users as $user) {
            $leader_id = $user->leader1;
            $redisKey = $redis_lock_key_prefix.$leader_id;
            if ($apiRedis->get($redisKey) > 0) {
                dump('用户'.$leader_id.'，'.$date.'已经执行过了，跳过');
                continue;
            }
            // 获取今天剩余时间秒数
            $expire = Carbon::now()->diffInSeconds(Carbon::now()->endOfDay());
            $apiRedis->set($redisKey, 1, $expire);
            // 获取一级下级
            $user_ids = UserModel::getTeamAllIds($leader_id);
            // 获取昨日购买广告位人数
            $active_num = (new FinanceProductOrderRepository())->getYesterdayFinanceNum($user_ids ?: [0]);
            if ($active_num == 0) {
                continue;
            }

            // 增加抽奖次数
            $map = [
                'user_id'      => $leader_id,
                'turntable_id' => 1
            ];
            $temp = TurntableCountModel::where($map)->find();
            $remark = '下级 '.$date.' 购买广告位人数：'.$active_num.'，赠送 '.$active_num.' 次抽奖次数';
            if (empty($temp)) {
                $data = [
                    'user_id'      => $leader_id,
                    'turntable_id' => 1,
                    'from_user_id' => "[]",
                    'reg_num'      => 0,
                    'num'          => $active_num,
                    'remark'       => $remark,
                    'create_time'  => time(),
                    'update_time'  => time(),
                ];
                TurntableCountModel::insert($data);
            } else {
                $data = [
                    'num'         => ($active_num + $temp['num']) > 0 ? ($active_num + $temp['num']) : 0,
                    'remark'      => $temp['remark'].'_'.$remark,
                    'update_time' => time(),
                ];
                TurntableCountModel::where($map)->update($data);
            }
        }
    }
}
