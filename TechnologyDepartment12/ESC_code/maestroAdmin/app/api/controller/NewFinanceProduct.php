<?php

namespace app\api\controller;

use app\admin\controller\TaskSession;
use app\admin\model\LevelModel;
use app\common\model\UserModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\LevelRepository;
use app\common\repository\TaskSessionRepository;
use app\job\MatchFinanceProduct;
use app\lib\exception\FinanceProductPasswordException;
use app\lib\exception\MatchFinanceProductException;
use app\lib\exception\SignException;
use app\lib\exception\TokenException;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\SimpleCache\InvalidArgumentException;
use think\App;
use think\db\exception\DbException;
use think\facade\Cache;
use app\BaseController;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\facade\Log;
use think\facade\Queue;
use think\facade\Request;
use think\response\Json;
use util\RedisPconnectPool;

class NewFinanceProduct extends BaseController
{
    private int $userid;
    private $userInfo;
    private $redis;

    /**
     * @throws TokenException
     */
    public function __construct(App $app = null)
    {
        parent::__construct($app);
        // 校验token
        $this->checkToken();
        $this->redis = Cache::store('redis');
    }

    /**
     * 超额匹配广告位
     * @param LevelRepository $level_repository
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     * @throws SignException
     * @throws \Throwable
     */
    public function overdue_match(LevelRepository $level_repository): Json
    {
        $password = input('password', '');
        $level_id = input('level_id', '');
        $timestamp = input('timestamp', '');
        $sign = input('sign', '');
        $type = input('type', '');

        $user_id = $this->userid;
        // 校验编码
        try {
            (new LevelRepository())->checkPassword($password, $level_id, $type, -2);
        } catch (FinanceProductPasswordException $e) {
            $redis = Cache::store('redis');
            $redis_key = $user_id . '_' . $level_id . '_match_status';
            $redis_lock_key = $user_id . '_' . $level_id . 'match_finance_product_lock';
            $data = msg(-2, [], lang('编码不正确'));
            $result = json_encode(msg(0, $data, lang('success')));
            $redis->set($redis_key, $result, 300);
            $redis->delete($redis_lock_key);
            return json(msg(0, [], lang('success')));
        }

        $this->checkSign([
            'password' => $password,
            'level_id' => $level_id,
            'timestamp' => $timestamp,
            'type' => $type,
        ], $sign);

        // 超额匹配，判断是否有超额匹配配置
        $level_data = $level_repository->getLevelByLevelID($level_id);
        // 判断当前时间是否允许解锁
        $can_unlock_current_time = (new LevelModel())->canUnlockAtCurrentTime($level_id);
        $redis = Cache::store('redis');
        $redis_key = $user_id . '_' . $level_id . '_match_status';
        $redis_lock_key = $user_id . '_' . $level_id . 'match_finance_product_lock';
        if (!$can_unlock_current_time) {
            $data = msg(-1, [], lang($level_data['unlock_time_disabled_tips']));
            $result = json_encode(msg(0, $data, lang('success')));
            $redis->set($redis_key, $result, 300);
            $redis->delete($redis_lock_key);
            return json(msg(0, [], lang('success')));
        }

        list($orders, $task_session) = (new FinanceProductOrderRepository)->getLevelSessionOrders($level_id,
            $this->userid);
        if ($orders->isEmpty()) {
            list($current_inventory, $total_inventory, $can_over_match, $stock_range,$user_money_interval) = FinanceProductOrderRepository::isUserMoneyInRangeAndStockEnough($level_data,
                $this->userInfo);
            if (!$can_over_match && !$total_inventory) {
                $data = msg(-1, [], lang('广告库存已售空'));
                $result = json_encode(msg(0, $data, lang('success')));
                $redis->set($redis_key, $result, 300);
                $redis->delete($redis_lock_key);
                return json(msg(0, [], lang('success')));
            }
        }
        $user_id = $this->userid;
        $queue = $user_id % 5 + 1; // 分配到不同的队列
        $queue_data = [
            'password' => $password,
            'level_id' => $level_id,
            'user_id' => $user_id,
            'match_type' => FinanceProductOrderRepository::MATCH_TYPE_OVERDUE
        ];
//         (new MatchFinanceProduct)->match($user_id, $level_id, $password, 2);
        Queue::push("MatchFinanceProduct@queue" . $queue, $queue_data, 'match_finance_product' . $queue);

        return json(msg(0, [], lang('success')));
    }

    /**
     * 匹配广告位
     * @return Json
     * @throws InvalidArgumentException
     */
    public function match(): Json
    {
        $password = input('password', '');
        $level_id = input('level_id', '');
        $timestamp = input('timestamp', '');
        $sign = input('sign', '');
        $type = input('type', '');

        $user_id = $this->userid;
        // 校验编码
        try {
            (new LevelRepository())->checkPassword($password, $level_id, $type, -2);

            $this->checkSign([
                'password' => $password,
                'level_id' => $level_id,
                'timestamp' => $timestamp,
                'type' => $type,
            ], $sign);
        } catch (FinanceProductPasswordException | SignException $e) {
            $redis_key = $user_id . '_' . $level_id . '_match_status';
            $redis_lock_key = $user_id . '_' . $level_id . 'match_finance_product_lock';
            $data = $e->extendedData;
            $result = json_encode(msg(0, $data, lang('success')));
            $this->redis->set($redis_key, $result, 300);
            $this->redis->delete($redis_lock_key);
            return json(msg(0, [], lang('success')));
        }

        $redis_lock_key = $user_id . '_' . $level_id . 'match_finance_product_lock';
        if ($this->redis->get($redis_lock_key)) {
            return json(msg(0, ['code' => -1, 'msg' => lang('匹配中')], lang('success')));
        }
        $queue = $user_id % 5 + 1; // 分配到不同的队列
        $queue_data = [
            'password' => $password,
            'level_id' => $level_id,
            'user_id' => $user_id,
            'match_type' => FinanceProductOrderRepository::MATCH_TYPE_NORMAL
        ];
//        (new MatchFinanceProduct)->match($user_id, $level_id, $password, FinanceProductOrderRepository::MATCH_TYPE_NORMAL);
        Queue::push("MatchFinanceProduct@queue" . $queue, $queue_data, 'match_finance_product' . $queue);
        $this->redis->set($redis_lock_key, 1, 60);// 设置锁，防止重复匹配
        return json(msg(0, [], lang('success')));
    }

    // 定时轮训获取匹配结果
    public function get_finance_product_order(): Json
    {
        $level_id = input('level_id', '');
        $user_id = $this->userid;
        $redisConfig = config('cache.stores.redis');
        $redisPool = new RedisPconnectPool($redisConfig, 1);
        $redis = $redisPool->connect();
        $redis_key = "maestro:" . $user_id . '_' . $level_id . '_match_status';
        try {
            $result = $redis->get($redis_key);
        } catch (\Exception $e) {
            $redis = $redisPool->connect();
            $result = $redis->get($redis_key);
        }
        if (!$result) {
            $redis->close();
            return json(msg(0, [], lang('success')));
        }
        $data = json_decode($result, true);
        // 翻译data里的msg
        if (isset($data['msg']) && is_string($data['msg'])) {
            $data['msg'] = lang($data['msg']);
        }
        if (isset($data['data']['msg']) && is_string($data['data']['msg'])) {
            $data['data']['msg'] = lang($data['data']['msg']);
        }
        if (isset($data['data']['data']['msg']) && is_string($data['data']['data']['msg'])) {
            $data['data']['data']['msg'] = lang($data['data']['data']['msg']);
        }
        if (isset($data['data']['data']['data']['msg']) && is_string($data['data']['data']['data']['msg'])) {
            $data['data']['data']['data']['msg'] = lang($data['data']['data']['data']['msg']);
        }
        $redis->del($redis_key);
        $redis->close();
        return json($data);
    }

    /**
     * @throws TokenException
     */
    private function checkToken(): void
    {
        $accessToken = Request::instance()->header('authorization');
        if (!$accessToken) {
            throw new TokenException();
        }
        try {
            // TODO
            // FIXME 判断token是否被加入了黑名单
            $tokenInfo = (array)JWT::decode($accessToken, new Key(config('app.jwt_key'), 'HS256'));
            if ($tokenInfo['create_time'] + $tokenInfo['expires_in'] < time()) {
                throw new TokenException();
            }
            $userModel = new UserModel();
            $this->userInfo = $userModel->where('id', $tokenInfo['user_id'])->find();
            if (!$this->userInfo) {
                throw new TokenException();
            }

            // 验证login_key是否匹配
            if (!isset($tokenInfo['login_key']) || $tokenInfo['login_key'] !== $this->userInfo['login_key']) {
                throw new TokenException();
            }

            $this->userid = $tokenInfo['user_id'];
        } catch (\Exception $e) {
            throw new TokenException();
        }
    }

    /**
     * @throws SignException
     */
    private function checkSign($data, $sign): void
    {
        $secret = config('app.api_secret');
        ksort($data); // 按键名排序
        $str = http_build_query($data); // 拼接成 key=value&key2=value2...
        $str .= $secret; // 拼接 secret
        if (md5($str) !== $sign) {
            throw new SignException([
                'msg' => lang('编码不正确'),
                'errorCode' => 0,
                'extendedData' => [
                    'msg' => lang('编码不正确'),
                    'code' => -1,
                ]
            ]);
        }

//        // 如果 $data['timestamp'] 不在 +- 10秒内，则抛出异常
//        $timestamp = $data['timestamp'] ?? 0;
//        if (abs($timestamp - time()) > 10) {
//            throw new SignException();
//        }
    }
}
