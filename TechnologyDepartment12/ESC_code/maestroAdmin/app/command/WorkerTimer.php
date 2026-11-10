<?php

namespace app\command;

use app\common\model\ConfigModel;
use app\common\model\MemberLevelModel;
use app\common\model\UserModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\HomeUserExcessAdRatioConfigRepository;
use app\common\repository\LevelRepository;
use app\common\repository\TaskSessionRepository;
use app\lib\exception\FinanceProductPasswordException;
use app\lib\exception\LockException;
use app\lib\exception\MatchFinanceProductException;
use app\lib\exception\SignException;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\Cache;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\db\exception\DataNotFoundException;
use think\facade\Config;
use util\RedisSyncPool;
use Workerman\Protocols\Http\Request;
use Workerman\Timer;
use Workerman\Worker;

// 引入 Request 类

// 队列匹配命令
// 使用方法: php think worker_timer start
class WorkerTimer extends Command
{
    // yesterday_max_order_id_2025-11-17
    protected const YESTERDAY_MAX_ORDER_ID = "yesterday_max_order_id_";

    protected $redisPool;

    protected function configure()
    {
        $this->setName('worker_timer')
            ->addArgument('action', Argument::OPTIONAL, "action")
            ->addOption('other', '-d', Option::VALUE_OPTIONAL, 'test');
    }

    /**
     * 重置Cli参数（可选）
     */
    protected function resetCli()
    {
        global $argv, $argc;

        $file = isset($argv[0]) ? $argv[0] : '';
        $action = isset($argv[1]) ? $argv[1] : '';
        $extend = isset($argv[2]) ? $argv[2] : '';

        if (!$file || !$action) {
            return;
        }

        $argv = [$file, $action];
        if ($extend) {
            $argv[] = $extend;
        }
        $argc = count($argv);

        $_SERVER['argv'] = $argv;
        $_SERVER['argc'] = $argc;
    }

    protected function execute(Input $input, Output $output)
    {
        $this->resetCli();
        // 初始化 Redis
        $redisConfig = config('cache.stores.redis');
        // 创建 HTTP Worker 实例
        $http_worker = new Worker('http://0.0.0.0:9909');
        $http_worker->name = 'Http Worker';
        $http_worker->count = 4; // 启动 4 个进程处理请求
        $jwt_key = config('app.jwt_key');
        $secret = config('app.api_secret');
        $lang_arr['pt'] = require(base_path("api") . "lang/pt.php");
        $lang_arr['zh-cn'] = require(base_path("api") . "lang/zh-cn.php");
        $redisPool = new RedisSyncPool($redisConfig, 1, 200);
        // 队列数量
        $queue_number = Config::get('app.queue_number', 6);
        //队列名称 root 队列才需要执行队列匹配
        $queue_name = Config::get('app.queue_name', 'queue_name');
        // Workerman 5.0 onMessage 接收 Request 对象
        //允许跨域
        $http_worker->onMessage = function ($connection, Request $request) use ($redisPool, $jwt_key, $secret, $lang_arr, $queue_number) {
            //获取请求请求url
            $fullUrl = $request->uri();
//            echo "fullUrl: " . $fullUrl . "\n";
            // 获取 GET 或 POST 参数
            $password = $request->post('password') ?: $request->get('password');
            $level_id = $request->post('level_id', 0) ?: $request->get('level_id', 0);
            $timestamp = $request->post('timestamp') ?: $request->get('timestamp');
            $type = $request->post('type') ?: $request->get('type');
            $sign = $request->post('sign') ?: $request->get('sign');
            $lang = $request->post('lang') ?: $request->get('lang');

            if (empty($level_id)) {
                $level_id = 0;
            }
            // 获取 token
            $token = $request->header('authorization') ?: '';
            $user_id = $this->checkToken($token, $jwt_key);
            if (!$user_id) {
                $redis_key = "maestro:" . $user_id . '_' . $level_id . '_match_status';
                $result = json_encode(msg(0, [
                    'code' => -1,
                    'msg' => '登录已退出'
                ], 'success'));
                $redisPool->set($redis_key, $result, 300);
                $data = [
                    'code' => 0,
                    'msg' => 'success',
                    'data' => 'user_id can not be empty'
                ];
                $this->sendJson($connection, $data);
                return;
            }
            //获取匹配状态
            if (strpos($fullUrl, 'new_finance_product/get_finance_product_order')) {
                echo "get_status User ID: $user_id ----level_id $level_id " . date("Y-m-d H:i:s") . "\n";
                //获取匹配状态
                $redis_key = "maestro:" . $user_id . '_' . $level_id . '_match_status';
//                echo "redis_key-----" . $redis_key . date("Y-m-d H:i:s") . "\n";
                $result = $redisPool->get($redis_key);
//                trace_log("获取匹配状态：{$redis_key} 内容：{$result}", 'debug');
                if (empty($result)) {
                    $data = [
                        'code' => 0,
                        'data' => [],
                        'msg' => 'success',
                    ];
                    $this->sendJson($connection, $data);
                    return;
                }
                $data = json_decode($result, true);
                if (empty($data)) {
                    //数据异常
                    $result = $redisPool->get($redis_key);
//                    trace_log("重新获取匹配状态：{$redis_key} 内容：{$result}", 'debug');
                    if (empty($result)) {
                        $data = [
                            'code' => 0,
                            'data' => [],
                            'msg' => 'success',
                        ];
                        $this->sendJson($connection, $data);
                    }
                    $data = json_decode($result, true);
                }
//                if (isset($data['data']['data']['user_id']) && $data['data']['data']['user_id'] != $user_id) {
//                    trace_log("错误获取：{$redis_key} 内容：{$result}", 'error');
//                }
                // 翻译data里的msg
                if (isset($data['msg']) && is_string($data['msg'])) {
                    $data['msg'] = $this->lang_string($data['msg'], $lang_arr, $lang);
                }
                if (isset($data['data']['msg']) && is_string($data['data']['msg'])) {
                    $data['data']['msg'] = $this->lang_string($data['data']['msg'], $lang_arr, $lang);
                }
                if (isset($data['data']['data']['msg']) && is_string($data['data']['data']['msg'])) {
                    $data['data']['data']['msg'] = $this->lang_string($data['data']['data']['msg'], $lang_arr, $lang);
                }
                if (isset($data['data']['data']['data']['msg']) && is_string($data['data']['data']['data']['msg'])) {
                    $data['data']['data']['data']['msg'] = $this->lang_string($data['data']['data']['data']['msg'], $lang_arr, $lang);
                }
                if (!empty($data)) {
                    $redisPool->del($redis_key);
                }
                $this->sendJson($connection, $data);
            } else {
                $match_type = FinanceProductOrderRepository::MATCH_TYPE_NORMAL;
                if (strpos($fullUrl, 'new_finance_product/overdue_match')) {
                    //超额匹配
                    $match_type = FinanceProductOrderRepository::MATCH_TYPE_OVERDUE;
                }
                if (empty($password) && empty($level_id)) {
                    $data = [
                        'code' => 0,
                        'msg' => 'success',
                        'data' => 'password and level_id can not be empty'
                    ];
                    $this->sendJson($connection, $data);
                    return;
                }
                $ret = $this->checkSign([
                    'password' => $password,
                    'level_id' => $level_id,
                    'timestamp' => $timestamp,
                    'type' => $type,
                ], $sign, $secret);
                if ($ret !== true) {
                    $redis_key = "maestro:" . $user_id . '_' . $level_id . '_match_status';
                    $result = json_encode($ret);
                    $redisPool->set($redis_key, $result, 300);
                    $data = [
                        'code' => 0,
                        'msg' => 'success',
                        'data' => 'sign error'
                    ];
                    $this->sendJson($connection, $data);
                    return;
                }
                $redis_lock_key = "maestro:" . $user_id . '_' . $level_id . 'match_finance_product_lock';
                if ($redisPool->get($redis_lock_key) === 1) {
                    $data = [
                        'code' => 0,
                        'msg' => 'success',
                        'data' => 'matching is lock'
                    ];
                    $this->sendJson($connection, $data);
                    return;
                }
                // 分配队列
                $queue = $user_id % $queue_number + 1;
                $queue_data = [
                    'password' => $password,
                    'level_id' => $level_id,
                    'user_id' => $user_id,
                    'match_type' => $match_type
                ];
                //{
                //    "job": "MatchFinanceProduct@queue4",
                //    "maxTries": null,
                //    "timeout": null,
                //    "data": {
                //        "password": "907480",
                //        "level_id": "3",
                //        "user_id": 3,
                //        "match_type": 1
                //    },
                //    "id": "CpCZbDFWUQuNDnDnugYbXyLNdSkaYnLg",
                //    "attempts": 1
                //}
                $temp = [
                    'job' => 'MatchFinanceProduct@queue' . $queue,
                    'maxTries' => null,
                    'timeout' => null,
                    'data' => $queue_data,
                    'id' => uniqid('', true),
                    'attempts' => 1,
                ];

                $redisPool->rpush('{queues:match_finance_product' . $queue . "}", json_encode($temp, JSON_UNESCAPED_UNICODE));
                $redisPool->set($redis_lock_key, 1, 60); // 设置锁

                // 响应客户端
                $data = [
                    'code' => 0,
                    'msg' => 'success'
                ];
                $this->sendJson($connection, $data);
                echo "User ID: $user_id ----level_id $level_id " . date("Y-m-d H:i:s") . "\n";
            }
        };
        if ($queue_name == 'root') {
            //        // 任务进程
            for ($key = 1; $key <= $queue_number; $key++) {
                $task_worker = new Worker();
                $task_worker->name = 'Task Worker ' . $key;
                $task_worker->count = 1;
                $task_worker->onWorkerStart = function ($task) use ($key) {
                    // 添加定时器：每隔 3 秒执行一次
                    Timer::add(3, function () use ($key) {
                        // 使用 exec 函数执行命令 会阻塞进程 不重复执行
                        $command = 'php think finance_product -g ' . $key;
                        exec($command, $output, $return_code);
                        if (!empty($output)) {
                            print_r($output);
                        }
                        // 在这里添加你的任务逻辑
                    });
                };
            }
        }
        Worker::runAll();
    }

    public function executeQueue($redisPool, $key, $limit)
    {
        $all_list = $this->getQueueList($redisPool, $key, $limit);
//        print_r("开始：" . date("Y-m-d H:i:s") . "\n");
//        $all_start_time = microtime(true);
        foreach ($all_list as $temp) {
//            echo "队列{$key}长度为：" . count($all_list) . "\n";
            $user_data = isset($temp['data']) ? $temp['data'] : [];
//            echo "队列数据:" . json_encode($user_data);
            if (!empty($user_data)) {
//                echo "开始执行队列{$key}--user_id:{$user_data['user_id']}--level_id:{$user_data['level_id']}--password:{$user_data['password']}--match_type:{$user_data['match_type']}\n";
                $start_time = microtime(true);
                try {
//                    echo "队列执行成功user_id:{$user_data['user_id']}...\n";
                    $this->match($user_data['user_id'], $user_data['level_id'], $user_data['password'], $user_data['match_type'], $redisPool);
                } catch (\Exception $e) {
                    print_r($e);
                    trace_log("匹配失败：" . json_encode($user_data) . " 内容:" . json_encode($e), 'error');
                    $user_id = $user_data['user_id'];
                    $level_id = $user_data['level_id'];
                    $redis_key = "maestro:" . $user_id . '_' . $level_id . '_match_status';
                    $redis_lock_key = "maestro:" . $user_id . '_' . $level_id . '_match_lock';
                    $result = json_encode(msg(0, [
                        'code' => -1,
                        'msg' => '匹配失败,请稍后再试'
                    ], 'success'));
                    $redisPool->set($redis_key, $result, 60);
                    $redisPool->del($redis_lock_key);
                    echo "队列{$key}执行异常:" . $e->getMessage() . "\n";
                }

                trace_log("匹配结束：" . json_encode($user_data) . "结束执行队列{$key}--user_id:{$user_data['user_id']}用时:" . (microtime(true) - $start_time) . "\n", 'info');
            }
        }
//        print_r("结束：" . date("Y-m-d H:i:s") . "总耗时：" . (microtime(true) - $all_start_time) . "\n");
    }

    public function getQueueList($redisPool, $queue, $limit = 20)
    {
//        获取上面的队列
        $temp_list = [];
        for ($i = 0; $i < $limit; $i++) {
            $temp = $redisPool->lpop('{queues:match_finance_product' . $queue . "}");
            if ($temp) {
                $temp_list[] = json_decode($temp, true);
            } else {
                break;
            }
        }
        return $temp_list;
    }

    public function beforeMatchValidation($user_id, $level_id): bool
    {
        $level_data = (new LevelRepository())->getLevelByLevelID($level_id);

        $now = date('H:i:s');
        $task_session_list = (new TaskSessionRepository())->getTaskSessionList($level_data['type']);
        $task_session = null;
        foreach ($task_session_list as $task_session_) {
            if ($task_session_['start_time'] <= $now && $task_session_['end_time'] >= $now) {
                $task_session = $task_session_;
                break;
            }
        }

        if (null == $task_session) {
            return false; //不在时段不能购买
        }

        // 得到用户当前时段（同一批次）所有订单
        $orders = (new FinanceProductOrderRepository())->selectWhere([
            ['user_id', '=', $user_id],
            [
                'create_time', 'between', [
                Carbon::parse($task_session->start_time)->timestamp,
                Carbon::parse($task_session->end_time)->timestamp
            ]
            ]
        ]);

        // 没有订单可以直接购买
        if ($orders->isEmpty()) {
            return true;
        }
        $result = true;
        foreach ($orders as $order) {
            if ($level_data['type'] != (new LevelRepository())->getLevelByLevelID($order['level_id'])['type']) {
                $result = false;
                break;
            }
        }
        return $result;
    }

    // 匹配
    public function match($user_id, $level_id, $password, $match_type, $redisPool)
    {
        $LevelRepository = new LevelRepository();
        $redis_key = "maestro:" . $user_id . '_' . $level_id . '_match_status';
        $redis_lock_key = "maestro:" . $user_id . '_' . $level_id . 'match_finance_product_lock';
        $user = UserModel::where('id', $user_id)->find();
        $level_data = $LevelRepository->getLevelByLevelID($level_id);


        if (!$level_data) {
            $result = json_encode(msg(0, [
                'code' => -1,
                'msg' => '不存在的广告位'
            ], 'success'));
            $redisPool->set($redis_key, $result, 60);
            $redisPool->del($redis_lock_key);
            return;
        }
        try {
            $LevelRepository->checkPassword($password, $level_id, $level_data['type'], -2);
        } catch (FinanceProductPasswordException $e) {
            $data = msg(-2, [], '编码不正确');
            $result = json_encode(msg(0, $data, 'success'));
            $redisPool->set($redis_key, $result, 300);
            return;
        }
        // 升级
        try {
            $orderRepository = new FinanceProductOrderRepository();
            list($orders, $task_session) = $orderRepository->getLevelSessionOrders($level_id, $user_id);// 如果有未匹配的订单，直接匹配
            $orders = json_decode(json_encode($orders), true);
            if (empty($orders)) {
                //需要判断时段
                if (!$task_session) {  // 判断时段
                    $result = json_encode(msg(0, [
                        'code' => -1,
                        'msg' => $level_data['unlock_time_disabled_tips'] ?: '目前无法购买'
                    ], 'success'));
                    $redisPool->set($redis_key, $result, 300);
                    return;
                }
                $member_level_model = new MemberLevelModel();
                //获取有效时间段有效的升级等级 如果不在时间段内 等级无效
                $now_level_type = $member_level_model->where([
                    ['user_id', '=', $user_id],
                    [
                        'create_time', 'between', [
                        Carbon::parse($task_session->start_time)->timestamp,
                        Carbon::parse($task_session->end_time)->timestamp
                    ]
                    ]
                ])->order('id desc')->value('type');
                if (!empty($now_level_type) && $now_level_type != LevelRepository::TYPE_EXPERIENCE) {
                    //用户纯在有效升级等级类型 需要判断等级之间的互斥关系
                    if ($now_level_type != $level_data['type'] && $level_data['type'] != LevelRepository::TYPE_EXPERIENCE) {
                        // 等级不匹配 不允许匹配
                        $result = json_encode(msg(0, [
                            'code' => -1,
                            'msg' => '等级不匹配'
                        ], 'success'));
                        $redisPool->set($redis_key, $result, 300);
                        return;
                    }
                }
                // 获取用户余额，判断是否可以解锁 TODO:此处是否可以优化，减少查询次数
                $user_info = $user->toArray();//UserModel::getOneUser($user_id, 'id', 'user_money');
                if (empty($user_info)) {
                    throw new MatchFinanceProductException('用户不存在');
                }
                if ($user_info['user_money'] < $level_data['price']) {
                    $result = json_encode(msg(0, [
                        'code' => -1,
                        'msg' => '当前账户余额未满足该交易区最低竞价区间要求'
                    ], 'success'));
                    $redisPool->set($redis_key, $result, 300);
                    return;
                }
//                $time1 = microtime(true);
                if ($match_type == FinanceProductOrderRepository::MATCH_TYPE_NORMAL) {
                    // 超额匹配弹窗
                    $tree_ids = explode('/', $user->tree_id);
                    // 倒序
                    $tree_ids = array_reverse(array_filter($tree_ids));
                    foreach ($tree_ids as $tree_id) {
                        $user_excess_ad_ratio_config = (new HomeUserExcessAdRatioConfigRepository())->getUserExcessAdRatioConfig($tree_id, $level_id);
                        if ($user_excess_ad_ratio_config) {
                            $temp_config = json_decode($user_excess_ad_ratio_config->ratio, true);
                            if (!is_array($temp_config)) {
                                continue;
                            }
                            $ratio = array_filter(json_decode($user_excess_ad_ratio_config->ratio, true));
                            if (count($ratio) == 0) {
                                continue;
                            }
                            if ($tree_id == $user_id || $user_excess_ad_ratio_config->subordinate == 1) {
                                $result = json_encode(msg(0, [
                                    'code' => -3,
                                    'msg' => '广告库存已售空,执行超额匹配'
                                ], 'success'));
                                trace_log("广告库存已售空:{$redis_key}-" . $result);
                                $redisPool->set($redis_key, $result, 300);
                                return;
                            }
                        }
                    }
                }
//                $time2 = microtime(true);
//                trace_log("获取用户余额耗时:".$time1-$time2);

                list($current_inventory, $total_inventory, $can_over_match, $stock_range, $user_money_interval) = FinanceProductOrderRepository::isUserMoneyInRangeAndStockEnough($level_data,
                    $user);
//                $time3 = microtime(true);
//                trace_log("检测库存耗时:".$time3-$time2);
                if (!$current_inventory && !$total_inventory && $match_type == FinanceProductOrderRepository::MATCH_TYPE_NORMAL) {
                    if ($can_over_match) { // 为了执行超额匹配方法
                        $result = json_encode(msg(0, [
                            'code' => -3,
                            'msg' => '广告库存已售空,执行超额匹配'
                        ], 'success'));
                    } else {
                        $msg = "广告库存已售空";
                        if ($user_money_interval == -1) {
                            $msg = "当前账户余额未达到该交易区的竞价区间";
                        }
                        if ($user_money_interval == 1) {
                            $msg = "当前账户余额已超出该交易区的竞价区间";
                        }
                        $result = json_encode(msg(0, [
                            'code' => -1,
                            'msg' => $msg
                        ], 'success'));
                    }
                    trace_log("广告库存已售空:{$redis_key}-" . $result);
                    $redisPool->set($redis_key, $result, 300);
                    return;
                }
                $time4 = microtime(true);
                $this->upLevel($user_id, $level_id);
                $time5 = microtime(true);
                trace_log("升级耗时:" . $time5 - $time4);
            } else {
                if ($orders[0]['level_id'] != $level_id && $orders[0]['session_no'] != '') {
                    $result = json_encode(msg(0, [
                        'code' => -1,
                        'msg' => '无法进入当前广告位'
                    ], 'success'));
                    $redisPool->set($redis_key, $result, 300);
                    trace_log("无法进入当前广告位:{$redis_key}-" . $result);
                    return;
                }
                if (!$orderRepository->isNeedToast($orders, $user, $level_id, $task_session)) {
                    trace_log("无需弹窗:{$redis_key}-" . $level_id);
                    // 不需要弹窗，走超额匹配
                    $res = $orderRepository->match($user_id, $level_id, $password,
                        FinanceProductOrderRepository::MATCH_TYPE_OVERDUE);
                    $result = json_encode(msg(0, $res, 'success'));
                    $redisPool->set($redis_key, $result, 300);
                    return;
                }
            }
//            trace_log("开始匹配:{$redis_key}-".$level_id);
//            $s_time = microtime(true);
            $res = $orderRepository->match($user_id, $level_id, $password, $match_type);
//            trace_log("匹配耗时:".(microtime(true) - $s_time));
            $result = json_encode(msg(0, $res, 'success'), JSON_UNESCAPED_UNICODE);
            $redisPool->set($redis_key, $result, 300);
        } catch (MatchFinanceProductException $e) {
            trace_log("匹配失败:{$redis_key}-" . $e->getMessage() . json_encode($e->extendedData));
            $result = json_encode(msg($e->errorCode, $e->extendedData, 'success'));
            $redisPool->set($redis_key, $result, 300);
        } catch (LockException $e) {
            trace_log("匹配失败:{$redis_key}-" . $e->msg);
            $result = json_encode(msg(0, ['code' => $e->errorCode, 'msg' => $e->msg], 'success'));
            $redisPool->set($redis_key, $result, 300);
        } catch (\Exception $e) {
            print_r($e);
        }
    }

    /**
     * @throws DataNotFoundException
     * @throws MatchFinanceProductException
     */
    private function upLevel($user_id, $level_id)
    {
        $userModel = new UserModel();
        $mode = ConfigModel::getConfigValue('level_upgrade_type');
        $mode = intval($mode);
        $level_id = intval($level_id);
        $user_id = intval($user_id);
        $out_data = $userModel->vipUpgradeStatus($user_id, $level_id, $mode);
        if ($out_data['code'] != 1 && $out_data['code'] != 13) {
            // 失败
            throw new MatchFinanceProductException([
                'msg' => $out_data['msg'],
                'errorCode' => 0,
                'extendedData' => [
                    'msg' => $out_data['msg'],
                    'code' => -1,
                    'data' => $out_data
                ]
            ]);
        }
    }


    function sendJson($connection, $data)
    {
        $connection->send(
            (new \Workerman\Protocols\Http\Response(200, ['Content-Type' => 'application/json'], json_encode($data, JSON_UNESCAPED_UNICODE)))
        );
    }


    /**
     * 校验 JWT Token
     * @param string $token
     * @param string $jwt_key
     * @return int
     */
    public function checkToken(string $token, $jwt_key = ''): int
    {
        if (!$token) {
            return 0;
        }

        try {
            $decoded = JWT::decode($token, new Key($jwt_key, 'HS256'));
            $tokenInfo = (array)$decoded;
            //不校验过期
//            if ($tokenInfo['create_time'] + $tokenInfo['expires_in'] < time()) {
//                return 0;
//            }
            return $tokenInfo['user_id'];
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * @throws SignException
     */
    private function checkSign($data, $sign, $secret)
    {
        ksort($data); // 按键名排序
        $str = http_build_query($data); // 拼接成 key=value&key2=value2...
        $str .= $secret; // 拼接 secret
        if (md5($str) !== $sign) {
            return [
                'msg' => '签名不正确',
                'errorCode' => 0,
                'extendedData' => [
                    'msg' => '签名不正确',
                    'code' => -1,
                ]
            ];
        }
        return true;
    }

    /**
     * 语言包翻译
     * @param $key
     * @param $lang_arr
     * @param string $lang
     * @return mixed
     */
    public function lang_string($key, $lang_arr, $lang = 'zh-cn')
    {
        $lang_list = isset($lang_arr[$lang]) ? $lang_arr[$lang] : [];
        return isset($lang_list[$key]) ? $lang_list[$key] : $key;
    }
}
