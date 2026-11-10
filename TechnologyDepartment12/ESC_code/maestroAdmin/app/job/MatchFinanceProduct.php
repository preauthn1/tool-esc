<?php

namespace app\job;

use app\common\model\ConfigModel;
use app\common\model\UserModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\HomeUserExcessAdRatioConfigRepository;
use app\common\repository\LevelRepository;
use app\common\repository\TaskSessionRepository;
use app\lib\exception\LockException;
use app\lib\exception\MatchFinanceProductException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\queue\Job;
use think\facade\Cache;

/**
 * 次功能已被弃用 要查看逻辑  请移至 command/WorkerTimer.php
 * Class MatchFinanceProduct
 * @package app\job
 */
class MatchFinanceProduct
{
    public function queue1(Job $job, $data)
    {
        $this->queue($job, $data, 1);
    }

    public function queue2(Job $job, $data)
    {
        $this->queue($job, $data, 2);
    }

    public function queue3(Job $job, $data)
    {
        $this->queue($job, $data, 3);
    }

    public function queue4(Job $job, $data)
    {
        $this->queue($job, $data, 4);
    }

    public function queue5(Job $job, $data)
    {
        $this->queue($job, $data, 5);
    }

    public function queue6(Job $job, $data)
    {
        $this->queue($job, $data, 6);
    }

    public function queue7(Job $job, $data)
    {
        $this->queue($job, $data, 7);
    }

    public function queue8(Job $job, $data)
    {
        $this->queue($job, $data, 8);
    }

    public function queue9(Job $job, $data)
    {
        $this->queue($job, $data, 9);
    }

    public function queue10(Job $job, $data)
    {
        $this->queue($job, $data, 10);
    }

    public function queue($job, $data, $queue)
    {
        try {
            $start_time = microtime_float();
            echo 'start_time:' . $start_time . "\n";
            $user_id = $data['user_id'];
            $level_id = $data['level_id'];
            $password = $data['password'];
            $match_type = $data['match_type'];
            $this->match($user_id, $level_id, $password, $match_type);
            $end_time = microtime_float();
            echo 'end_time:' . $end_time . "\n";
            echo $end_time - $start_time . "\n";;
            echo('queue ' . $queue . ' ___________' . date('Y-m-d H:i:s') . '_' . json_encode($data));
        } catch (Exception $e) {
            echo $e->getMessage();
        } finally {
            $job->delete();
        }


    }

    public function match($user_id, $level_id, $password, $match_type)
    {
        $redis = Cache::store('redis');
        $redis_key = $user_id . '_' . $level_id . '_match_status';
        $redis_lock_key = $user_id . '_' . $level_id . 'match_finance_product_lock';
        $redis->set($redis_lock_key, 1, 60);// 设置锁，防止重复匹配

        $user = UserModel::where('id', $user_id)->find();
        $level_data = (new LevelRepository())->getLevelByLevelID($level_id);
        if (!$level_data) {
            $result = json_encode(msg(0, [
                'code' => -1,
                'msg' => '不存在的广告位'
            ], 'success'));
            $redis->set($redis_key, $result, 300);
            $redis->delete($redis_lock_key);
            return;
        }

        try {
            list($orders, $task_session) = (new FinanceProductOrderRepository)->getLevelSessionOrders($level_id,
                $user_id);// 如果有未匹配的订单，直接匹配
            $orderRepository = new FinanceProductOrderRepository;
            $orders = json_decode(json_encode($orders), true);
            if (empty($orders)) {
                //不是体验区需要判断时段
                if (!$task_session) {  // 判断时段
                    $result = json_encode(msg(0, [
                        'code' => -1,
                        'msg' => $level_data['unlock_time_disabled_tips'] ?: '目前无法购买'
                    ], 'success'));
                    $redis->set($redis_key, $result, 300);
                    $redis->delete($redis_lock_key);
                    return;
                }
                // 获取用户余额，判断是否可以解锁
                $user_info = UserModel::getOneUser($user_id, 'id', 'user_money');
                if (empty($user_info)) {
                    throw new MatchFinanceProductException('用户不存在');
                }
                if ($user_info['user_money'] < $level_data['price']) {
                    $result = json_encode(msg(0, [
                        'code' => -1,
                        'msg' => '当前账户余额未满足该交易区最低竞价区间要求'
                    ], 'success'));
                    $redis->set($redis_key, $result, 300);
                    $redis->delete($redis_lock_key);
                    return;
                }
                if ($match_type == FinanceProductOrderRepository::MATCH_TYPE_NORMAL) {
                    // 超额匹配弹窗
                    $tree_ids = explode('/', $user->tree_id);
                    // 倒序
                    $tree_ids = array_reverse(array_filter($tree_ids));
                    foreach ($tree_ids as $tree_id) {
                        $user_excess_ad_ratio_config = (new HomeUserExcessAdRatioConfigRepository())->getUserExcessAdRatioConfig($tree_id, $level_id);
                        if ($user_excess_ad_ratio_config) {
                            $ratio = array_filter(json_decode($user_excess_ad_ratio_config->ratio, true));
                            if (count($ratio) == 0) {
                                continue;
                            }
                            if ($tree_id == $user_id || $user_excess_ad_ratio_config->subordinate == 1) {
                                $result = json_encode(msg(0, [
                                    'code' => -3,
                                    'msg' => '广告库存已售空,执行超额匹配'
                                ], 'success'));
                                $redis->set($redis_key, $result, 300);
                                $redis->delete($redis_lock_key);
                                return;
                            }
                        }
                    }
                }

                list($current_inventory, $total_inventory, $can_over_match, $stock_range,$user_money_interval) = FinanceProductOrderRepository::isUserMoneyInRangeAndStockEnough($level_data,
                    $user);
                if (!$current_inventory && !$total_inventory && $match_type == FinanceProductOrderRepository::MATCH_TYPE_NORMAL) {
                    if ($can_over_match) { // 为了执行超额匹配方法
                        $result = json_encode(msg(0, [
                            'code' => -3,
                            'msg' => '广告库存已售空,执行超额匹配'
                        ], 'success'));
                    } else {
                        $result = json_encode(msg(0, [
                            'code' => -1,
                            'msg' => '当前账户余额已超出该交易区的竞价区间'
                        ], 'success'));
                    }
                    $redis->set($redis_key, $result, 300);
                    $redis->delete($redis_lock_key);
                    return;
                }
                // 升级
                $this->upLevel($user_id, $level_id);
            } else {
                if ($orders[0]['level_id'] != $level_id && $orders[0]['session_no'] != '') {
                    $result = json_encode(msg(0, [
                        'code' => -1,
                        'msg' => '无法进入当前广告位'
                    ], 'success'));
                    $redis->set($redis_key, $result, 300);
                    $redis->delete($redis_lock_key);
                    return;
                }

                if (!$orderRepository->isNeedToast($orders, $user, $level_id, $task_session)) {
                    // 不需要弹窗，走超额匹配
                    $res = $orderRepository->match($user_id, $level_id, $password,
                        FinanceProductOrderRepository::MATCH_TYPE_OVERDUE);
                    $result = json_encode(msg(0, $res, 'success'));
                    $redis->set($redis_key, $result, 300);
                    $redis->delete($redis_lock_key);
                    return;
                }
            }
            $res = $orderRepository->match($user_id, $level_id, $password, $match_type);
            $result = json_encode(msg(0, $res, 'success'));
            $redis->set($redis_key, $result, 300);
            $redis->delete($redis_lock_key);
        } catch (MatchFinanceProductException $e) {
            $result = json_encode(msg($e->errorCode, $e->extendedData, 'success'));
            $redis->set($redis_key, $result, 300);
            $redis->delete($redis_lock_key);
        } catch (LockException $e) {
            $result = json_encode(msg(0, ['code' => $e->errorCode, 'msg' => $e->msg], 'success'));
            $redis->set($redis_key, $result, 300);
            $redis->delete($redis_lock_key);
        }
    }

    /**
     * @throws DataNotFoundException
     * @throws MatchFinanceProductException
     * @throws LockException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws PDOException
     * @throws Exception
     */
    private function upLevel($user_id, $level_id)
    {
        $userModel = new UserModel();
        $mode = ConfigModel::getConfigValue('level_upgrade_type');

        $out_data = $userModel->vipUpgradeStatus($user_id, $level_id, $mode);
        if ($out_data['code'] != 1 && $out_data['code'] != 13) {
            // 失败
            throw new MatchFinanceProductException([
                'msg' => lang($out_data['msg']),
                'errorCode' => 0,
                'extendedData' => [
                    'msg' => lang($out_data['msg']),
                    'code' => -1
                ]
            ]);
        }
    }
}
