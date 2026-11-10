<?php

namespace app\common\model;

use app\admin\model\LevelModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\LevelTeamRepository;
use think\Model;
use think\model\relation\BelongsTo;

class TaskModel extends Model
{
    //0取消 1进行中 2已完成 3已失效
    const STATUS_CANCEL = 0;//取消
    const STATUS_ING = 1;//进行中
    const STATUS_END = 2;//已完成
    const STATUS_EXPIRE = 3;//已失效

    //
    static $STATUS_NAMES = [
        self::STATUS_CANCEL => "取消",
        self::STATUS_ING => "进行中",
        self::STATUS_END => "已完成",
        self::STATUS_EXPIRE => "已失效",
    ];

    //1商品任务  2分享任务 3计划任务 4任务产品 5视频任务
    const TYPE_GOODS = 1;//商品任务
    const TYPE_SHARE = 2;//分享任务
    const TYPE_PLAN = 3;//计划任务
    const TYPE_PRODUCT = 4;//任务产品
    const TYPE_VIDEO = 5;//视频任务

    static $TYPE_NAMES = [
        self::TYPE_GOODS => "商品任务",
        self::TYPE_SHARE => "分享任务",
        self::TYPE_PLAN => "计划任务",
        self::TYPE_PRODUCT => "任务产品",
        self::TYPE_VIDEO => "视频任务",
    ];

    protected $autoWriteTimestamp = true;
    // 确定链接表名
    protected $name = 'task';

    //刷新任务
    public function updateTask($user_id)
    {
        $plan_id = UserModel::getFieldData($user_id, 'plan_id');
        $day24 = false;
        $hours_24 = ConfigModel::getConfigValue('task_reset_type');
        //升级是否重置新的任务：比如会员充值VIP1做完任务后当天再充值VIP2是否要让他继续做任务。
        $is_upgrade_reset_task = ConfigModel::getConfigValue('is_upgrade_reset_task');
        if ($hours_24 == 1) {
            $day24 = true;
        }
        if (empty($plan_id)) {
            $level_id = UserModel::getFieldData($user_id, 'level_id');
            $member_level_data = MemberLevelModel::where('user_id', $user_id)->order('id DESC')->limit(2)->select();
            if (!empty($member_level_data)) {
                if (2 === count($member_level_data)) {
                    $last_level_data = $member_level_data[0];
                    $create_time = strtotime($last_level_data['create_time']);
                    $day = date('Y-m-d', $create_time);
                    if ($day == date('Y-m-d')) {
                        if ($is_upgrade_reset_task != 1) {
                            return [];
                        }
                    }
                }
            }
            $data = $this->levelUpgrade($user_id, $level_id, $day24);
        } else {
            $data = $this->addPlanTask($user_id, 1, 1);
        }
        return $data;
    }

    /**
     * 用户升级增加任务
     * @param $user_id
     * @param $level_id
     * @param $day24 //是否24小时,false自然日
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function levelUpgrade($user_id, $level_id, $day24 = false)
    {
        $level_data = LevelModel::where('level_id', $level_id)->field('task_num,income')->find();
        if (empty($level_data)) {
            return [
                'code' => 9,
                'msg' => '等级不存在'
            ];
        }
        //刷新任务
        $this->updateExpire($user_id, $day24);
        $map = [
            'user_id' => $user_id,
            'level_id' => $level_id,
        ];
        //判断是否可以增加任务
        if ($day24) {
//            $temp = $this->where($map)->where('task_type', '<>', self::TYPE_PLAN)->where('expire_time', '>', time())->find();
            $temp = $this->where($map)->where('expire_time', '>', time())->find();
        } else {
//            $temp = $this->where($map)->where('task_type', '<>', self::TYPE_PLAN)->where('day', '=', date("Y-m-d"))->find();
            $temp = $this->where($map)->where('day', '=', date("Y-m-d"))->find();
        }
        if (!empty($temp)) {
            return [
                'code' => 10,
                'msg' => '该用户等级任务已经添加'
            ];
        }
        $goods_model = new GoodsModel();
        $product = $goods_model->getProductList($level_id, $level_data['task_num']);
        $brokerage = [
            'level_id' => $level_id,
            'money' => $level_data['income'],
        ];
        $data = $this->addTasks($user_id, $product, $brokerage, 1);
        if ($data['code'] == 1) {
            //添加升级任务后清除用户升级状态
            UserModel::upgradeStatus($user_id, 0);
        }
        return $data;
    }

    /**
     * 刷新任务状态
     * @param string $user_id
     * @param boolean $day24
     * @return bool
     */
    public function updateExpire($user_id = '', $day24 = false)
    {
        $map = [
            'status' => 1
        ];
        if (!empty($user_id)) {
            $map['user_id'] = $user_id;
        }
        $task_type = [
            TaskModel::TYPE_GOODS,
            TaskModel::TYPE_PRODUCT,
        ];
        if ($day24 == false) {
            $ret = $this->where($map)->whereIn('task_type', $task_type)->where('day', '<', date("Y-m-d"))->update(['status' => 3]);
        } else {
            $ret = $this->where($map)->whereIn('task_type', $task_type)->where('expire_time', '<', time())->update(['status' => 3]);
        }
        if ($ret == false) {
            return false;
        }
        return true;
    }

    /**
     * 增加单条任务
     * @param $user_id //会员id
     * @param $product
     * id 产品id
     * price 产品价格
     * task_type 产品类型
     * @param $brokerage
     * level_id 会员等级id
     * money 会员等级佣金
     * @param $order_type //派单类型 1自动派单  2手动派单
     * @param int $is_xuni 是否是虚拟 0不是 1是
     * @return array
     */
    public function addTask($user_id, $product, $brokerage, $order_type = 1, $is_xuni = 0)
    {
        $in_data = [
            'is_xuni' => $is_xuni,
            'user_id' => $user_id,
            'level_id' => $brokerage['level_id'],
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'product_img' => $product['img'],
            'product_price' => $product['price'],//产品价格
            'income_profit' => round($brokerage['money'] / $product['price'], 2),//产品售价（商品价格+佣金）
            'task_type' => $product['task_type'],//任务类型 1商品任务  2分享任务 3计划任务 4任务产品 5视频任务
            'order_type' => $order_type,//派单类型 1自动派单  2手动派单
            'money' => $brokerage['money'],//佣金
            'day' => date("Y-m-d"),//时间
            'status' => 1,//拼单状态 0取消 1 进行中 2 已完成 3 已失效
            'expire_time' => time() + (3600 * 24),
            'create_time' => time(),
            'update_time' => time(),
        ];
        $id = $this->insertGetId($in_data);
        if (empty($id)) {
            return [
                'code' => 10,
                'msg' => '添加失败'
            ];
        }
        return [
            'code' => 1,
            'data' => $id,
            'msg' => '添加成功'
        ];
    }

    /**
     * 增加多条任务
     * @param $user_id
     * @param array $product 产品列表
     * id 产品id
     * price 产品价格
     * task_type 产品类型
     * @param $brokerage
     * level_id 会员等级id
     * money 会员等级佣金
     * @param $order_type
     * @param int $is_xuni
     * @return array
     */
    public function addTasks($user_id, $product, $brokerage, $order_type, $is_xuni = 0)
    {
        $in_datas = [];
        foreach ($product as $value) {
            if (empty($value['price'])) {
                $income_profit = 0;
            } else {
                $income_profit = round($brokerage['money'] / $value['price'], 2);//收益率（佣金/价格）
            }
            $in_datas[] = [
                'is_xuni' => $is_xuni,
                'user_id' => $user_id,
                'level_id' => $brokerage['level_id'],
                'product_id' => $value['id'],
                'product_name' => $value['name'],
                'product_img' => $value['img'],
                'product_price' => $value['price'],//产品价格
                'income_profit' => $income_profit,//收益率（佣金/价格）
                'task_type' => $value['task_type'],//任务类型 1商品任务  2分享任务 3计划任务 4任务产品 5视频任务
                'order_type' => $order_type,//派单类型 1自动派单  2手动派单
                'money' => $brokerage['money'],//佣金
                'day' => date("Y-m-d"),//时间
                'status' => 1,//拼单状态 0取消 1 进行中 2 已完成 3 已失效
                'expire_time' => time() + (3600 * 24),
                'create_time' => time(),
                'update_time' => time(),
            ];
        }

        $ret = $this->insertAll($in_datas);
        if ($ret == false) {
            return [
                'code' => 10,
                'msg' => '添加失败'
            ];
        }
        return [
            'code' => 1,
            'msg' => '添加成功'
        ];
    }

    /**
     * 获取计划任务
     * @param $user_id
     * @param int $order_type
     * @param int $crawl
     * @param int $is_xuni
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function addPlanTask($user_id, $order_type = 1, $crawl = 0, $is_xuni = 0)
    {
        $plan_task_model = new PlanTaskModel();
        $status = PlanTaskModel::getHasDayPlanTask($user_id);
        if ($status == 0) {
            return [
                'code' => 10,
                'msg' => 'has plan task'
            ];
        }
        if ($status == 2 && $crawl != 1) {
            return [
                'code' => 10,
                'msg' => 'no plan task'
            ];
        }
        $goods_model = new GoodsModel();
        $product = $goods_model->getProductPlanRand();
        $task = $plan_task_model->getPlanTask($user_id);
        if (empty($task)) {
            return [
                'code' => 11,
                'msg' => 'no plan task'
            ];
        }
        $home_user = new UserModel();
        $fields = implode(',', MoneyLogModel::$TYPE_FIELDS);
        $user_data = $home_user->where('id', $user_id)->field($fields)->find();
        $base_money = $user_data[MoneyLogModel::$TYPE_FIELDS[MoneyLogModel::TYPE_USER]];
        $brokerage_money = $user_data[MoneyLogModel::$TYPE_FIELDS[MoneyLogModel::TYPE_BROKERAGE]];
        //当前佣金金额
        $all_money = $base_money + $brokerage_money;
        //将基础账号钱转入佣金账号
        $money_log_model = new MoneyLogModel();
        $data = $money_log_model->transformMoney($user_id, MoneyLogModel::TYPE_USER, MoneyLogModel::TYPE_BROKERAGE, $base_money);
        if ($data['code'] != 1) {
            return [
                'code' => 12,
                'msg' => '转化失败'
            ];
        }
        if ($task['type'] == PlanModel::TYPE_MULTIPLE) {
            //按照账户总额倍数
            //渠道佣金账户余额
            $finish_money = round($all_money * $task['multiple'], 2);
            $income_profit = round($task['brokerage_ratio'] / 100, 2);
            $money = round($finish_money * ($task['brokerage_ratio'] / 100), 2);
        } else {
            //渠道佣金账户余额
            if (empty($task['recharge_money'])) {
                $income_profit = 0;
            } else {
                $income_profit = round($task['brokerage_money'] / $task['recharge_money'], 2);
            }
            $finish_money = round($task['recharge_money'], 2);
            $money = round($task['brokerage_money'], 2);
        }
        $in_data = [
            'is_xuni' => $is_xuni,
            'user_id' => $user_id,
            'level_id' => UserModel::getFieldData($user_id, 'level_id'),
            'plan_id' => $task['plan_id'],
            'plan_task_id' => $task['id'],
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'product_img' => $product['img'],
            'product_price' => $finish_money,//产品价格
            'income_profit' => $income_profit,//
            'task_type' => self::TYPE_PLAN,//任务类型 1商品任务  2分享任务 3计划任务 4任务产品 5视频任务
            'order_type' => $order_type,//派单类型 1自动派单  2手动派单
            'money' => $money,//佣金
            'finish_money' => $finish_money,//完成金额
            'day' => date("Y-m-d"),//时间
            'status' => 1,//拼单状态 0取消 1 进行中 2 已完成 3 已失效
            'expire_time' => 0,//计划任务永不过期
            'create_time' => time(),
            'update_time' => time(),
        ];
        $id = $this->insertGetId($in_data);
        if (empty($id)) {
            return [
                'code' => 10,
                'msg' => '添加失败'
            ];
        }
        return [
            'code' => 1,
            'data' => $id,
            'msg' => '添加成功'
        ];
    }

    /**
     * 添加视频任务（评分任务）
     * @param $user_id
     * @param int $level_id
     * @param int $video_id
     * @param int $is_xuni
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function addVideoTask($user_id, $level_id = 1, $video_id = 0, $is_xuni = 0)
    {
        $user_level_model = new MemberLevelModel();
        //判断该等级是否可以做任务
        $has_level = $user_level_model
            ->where('user_id', $user_id)
            ->where('to_level', $level_id)
            ->order('create_time desc')
            ->find();
        if (empty($has_level)) {
            return [
                'code' => 10,
                'msg' => '等级未升级'
            ];
        }
        if ($has_level['level_end_time'] < time()) {
            return [
                'code' => 10,
                'msg' => '等级解锁已过期'
            ];
        }
        //获取等级数据
        $level_data = LevelModel::where('level_id', $level_id)->field('task_num,income,type,is_show,is_can_buy,active_income,income_max')->find();
        if (empty($level_data)) {
            return [
                'code' => 10,
                'msg' => '等级数据不存在'
            ];
        }

        if ($level_data['is_show'] == 0 || $level_data['is_can_buy'] == 0) {
            return [
                'code' => 10,
                'msg' => '等级未升级'
            ];
        }

        if ($level_data['type'] > 0 && $level_data['type'] == 2) { // 2-活动专区
            $map = [
                'level_id' => $level_id,
                'user_id' => $user_id
            ];
            $day_num = TaskModel::where($map)->count();
            if ($day_num >= $level_data['task_num']) {
                return [
                    'code' => 10,
                    'msg' => '任务数量已达上限'
                ];
            }
        } else {
            $task_num = $level_data['task_num'];
            $map = [
                'day' => date("Y-m-d"),
                'level_id' => $level_id,
                'user_id' => $user_id
            ];
            $day_num = TaskModel::where($map)->count();
            if ($day_num >= $task_num) {
                return [
                    'code' => 10,
                    'msg' => '今日任务数量已达上限'
                ];
            }

            // 判断是否有代理vip配置
            $top_leader = UserModel::getTopLeader($user_id);
            if ($top_leader) {
                $team_level = (new LevelTeamRepository)->findWhere([
                    ['user_id', '=', $top_leader['id']],
                    ['level_id', '=', $level_id],
                    ['status', '=', LevelTeamRepository::STATUS_ENABLE]
                ]);
                if ($team_level) {
                    $level_data = $team_level;
                }
            }
        }
        $product = VideoModel::where('id', $video_id)->find();
        if (empty($product)) {
            return [
                'code' => 10,
                'msg' => '视频不存在'
            ];
        }
        $money = $level_data['income'];
        if ($level_data['active_income'] > 0) {
            // 获取一级下级用户
            $team_one_ids = UserModel::getTeamAllIds($user_id);
            $active_count = (new FinanceProductOrderRepository)->getYesterdayFinanceNum($team_one_ids ?: [0]);
            if ($active_count > 0) {
                $new_money = $money + $level_data['active_income'] * $active_count;
                $income_max = max($level_data['income_max'], $level_data['income']);
                $money = min($new_money, $income_max);
            }
        }
        // 获取活跃用户
        $income_profit = rand(10, 30) / 100;
        $product['price'] = $money * ($income_profit) + 1;
        $in_datas = [
            'is_xuni'       => $is_xuni,
            'user_id'       => $user_id,
            'level_id'      => $level_id,
            'product_id'    => $product['id'],
            'product_name'  => $product['name'],
            'product_img'   => $product['pic'],
            'product_price' => $product['price'],//产品价格
            'income_profit' => $income_profit,//收益率（佣金/价格）
            'task_type'     => TaskModel::TYPE_VIDEO,//任务类型 1商品任务  2分享任务 3计划任务 4任务产品 5视频任务
            'order_type'    => 1,//派单类型 1自动派单  2手动派单
            'money'         => $money,//佣金
            'day'           => date("Y-m-d"),//时间
            'status'        => TaskModel::STATUS_END,//拼单状态 0取消 1 进行中 2 已完成 3 已失效
            'expire_time'   => time() + (3600 * 24),
            'create_time'   => time(),
            'update_time'   => time(),
        ];
        $this->startTrans();
        $ret = $this->insert($in_datas);
        if ($ret == false) {
            $this->rollback();
            return [
                'code' => 10,
                'msg' => '添加失败'
            ];
        }
        if (floatval($money) == 0) {
            $this->commit();
            return [
                'code' => 1,
                'msg' => '任务完成成功',
            ];
        }
        $title = "任务返佣";
        $remarks = "完成任务返佣:{$money} ";
        $money_log = new MoneyLogModel();
        $change = "add";
        $data = $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_BROKERAGE, $change, $title, $remarks, 3);
        if ($data['code'] == 1) {
            $this->commit();
            // 视频任务不返佣
//            $money_log->allotBrokerage($user_id, $money, 3, false);
            return [
                'code' => 1,
                'msg' => '任务完成成功',
            ];
        }
        $this->rollback();
        return [
            'code' => 2,
            'msg' => '评分失败'
        ];
    }

    /**
     * 完成任务
     * @param $id
     * @param $user_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function endTask($id, $user_id)
    {
        $map = [
            'id' => $id,
            'user_id' => $user_id
        ];
        $temp = $this->where($map)->find();
        if (empty($temp)) {
            return [
                'code' => 11,
                'msg' => '任务不存在',
            ];
        }
        $status = $temp['status'];
        if ($status != 1) {
            return [
                'code' => 12,
                'msg' => '任务状态有误',
            ];
        }
        $expire_time = $temp['expire_time'];
        if ($expire_time < time()) {
            return [
                'code' => 13,
                'msg' => '任务已过期',
            ];
        }
        $data = UserModel::endTaskCondition($user_id, $temp['level_id']);
        if ($data['code'] != 1) {
            return $data;
        }
        $in_update = [
            'status' => 2,
            'finish_time' => time(),
            'update_time' => time(),
        ];
        $this->startTrans();
        $ret = $this->where('id', $id)->update($in_update);
        if ($ret == false) {
            $this->rollback();
            return [
                'code' => 14,
                'msg' => '任务完成失败',
            ];
        }
        $money = $temp['money'];
        if (floatval($money) == 0) {
            $this->commit();
            UserModel::decTaskCount($user_id);
            return [
                'code' => 1,
                'msg' => '任务完成成功',
            ];
        }
        $title = "任务返佣";
        $remarks = "完成任务返佣:{$money} ";
        $money_log = new MoneyLogModel();
        $change = "add";
        $data = $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_BROKERAGE, $change, $title, $remarks, 3);
        if ($data['code'] == 1) {
            $this->commit();
            $money_log->allotBrokerage($user_id, $money, 3, false);
            UserModel::decTaskCount($user_id);
            return [
                'code' => 1,
                'msg' => '任务完成成功',
            ];
        }
        $this->rollback();
        return [
            'code' => 15,
            'msg' => '任务完成失败',
        ];
    }

    /**
     * 完成计划任务
     * @param $id
     * @param $user_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function endPlanTask($id, $user_id)
    {
        $map = [
            'id' => $id,
            'task_type' => self::TYPE_PLAN,
            'user_id' => $user_id
        ];
        $temp = $this->where($map)->find();
        if (empty($temp)) {
            return [
                'code' => 11,
                'msg' => '任务不存在',
            ];
        }
        $status = $temp['status'];
        if ($status != 1) {
            return [
                'code' => 12,
                'msg' => '任务状态有误',
            ];
        }
        $money_log_model = new MoneyLogModel();
        $user_data = $money_log_model->getUserMoney($user_id);
        $user_money = $user_data[MoneyLogModel::$TYPE_FIELDS[MoneyLogModel::TYPE_USER]];
        $brokerage_money = $user_data[MoneyLogModel::$TYPE_FIELDS[MoneyLogModel::TYPE_BROKERAGE]];
        $sub_money = round($temp['finish_money'] - ($user_money + $brokerage_money), 2);
        if ($sub_money > 0) {
            return [
                'code' => 16,
                'data' => [
                    'now_money' => number_format($user_money + $brokerage_money, 2, '.', ','),
                    'sub_money' => number_format($sub_money, 2, '.', ','),
                ],
                'msg' => '充值流金额未达到',
            ];
        }
        $in_update = [
            'status' => 2,
            'finish_time' => time(),
            'update_time' => time(),
        ];
        $this->startTrans();
        $ret = $this->where('id', $id)->update($in_update);
        if ($ret == false) {
            $this->rollback();
            return [
                'code' => 14,
                'msg' => '任务完成失败',
            ];
        }
        if (empty($money)) {
            $this->commit();
            return [
                'code' => 1,
                'msg' => '任务完成成功',
            ];
        }
        $money = $temp['money'];
        $title = "任务返佣";
        $remarks = "完成任务返佣:{$money} ";
        $money_log = new MoneyLogModel();
        $change = "add";
        //将基础账号钱转入佣金账号
        $data = $money_log_model->transformMoney($user_id, MoneyLogModel::TYPE_USER, MoneyLogModel::TYPE_BROKERAGE, $temp['finish_money'] - ($user_money + $brokerage_money));
        if ($data['code'] != 1) {
            $this->rollback();
            return [
                'code' => 12,
                'msg' => '转化失败'
            ];
        }
        $data = $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_BROKERAGE, $change, $title, $remarks, 3);
        if ($data['code'] == 1) {
            $this->commit();
            $money_log->allotBrokerage($user_id, $money, 3, false);
            return [
                'code' => 1,
                'msg' => '任务完成成功',
            ];
        }
        $this->rollback();
        return [
            'code' => 15,
            'msg' => '任务完成失败',
        ];
    }

    /**
     * 获取最后一次计划产品id
     * @param $user_id
     * @return mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getLastPlanId($user_id)
    {
        $task = $this->where('user_id', $user_id)->where('task_type', TaskModel::TYPE_PLAN)->field('plan_task_id,status')->order('id DESC')->find();
        if (empty($task)) {
            return '';
        }
        if ($task['status'] == self::STATUS_ING) {
            return false;
        }
        return $task['plan_task_id'];
    }

    /**
     * 判断计划任务是否完成
     * @param $user_id
     * @param $plan_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function isPlanIdEnd($user_id, $plan_id)
    {
        $task = $this->where('user_id', $user_id)->where('plan_id', $plan_id)->where('task_type', TaskModel::TYPE_PLAN)->field('plan_task_id,status')->order('id DESC')->select();
        $plan_task_model = new PlanTaskModel();
        $plan_task_ids = $plan_task_model->where('plan_id', $plan_id)->column('id');
        $is_end = true;
        foreach ($task as $value) {
            if (!in_array($value['plan_task_id'], $plan_task_ids)) {
                $is_end = false;
                return $is_end;
            }
        }
        return $is_end;
    }

    /**
     * 更新状态
     * @param array $param
     * @param array $where
     */
    public function updateStatus($param = [], $where = [])
    {
        try {
            $this->where($where)->update($param);
            return msg(1, '', 'ok');
        } catch (\Exception $e) {

            return msg(-1, '', $e->getMessage());
        }
    }

    /**
     * 获取任务次数刷新时间
     * @param $user_id
     * @param $day24
     */
    public function getTaskNumber($user_id, $day24 = false)
    {
        //获取用户任务数量 以及下次刷新任务的时间
        $task_last = $this->where('user_id', $user_id)->whereIn('task_type', [TaskModel::TYPE_GOODS, TaskModel::TYPE_PRODUCT])->order('id DESC')->find();
        if (!empty($task_last)) {
            $task_his_time = strtotime($task_last['create_time']);
        } else {
            $task_his_time = strtotime('23:59:59');
        }
        if ($day24) {
            //绝对24小时
            //下次任务重置时间
            $task_reset_time = time() >= $task_his_time ? (($task_his_time + (24 * 3600)) - time()) : ($task_his_time - time());
            $reset_time = [
                'time' => $task_reset_time,
                'hour' => intval($task_reset_time / 3600),
                'minute' => intval(($task_reset_time % 3600) / 60),
                'second' => intval($task_reset_time % 60),
            ];
        } else {
            //自然日
            $task_reset_time = strtotime('23:59:59') - time();
            $reset_time = [
                'time' => $task_reset_time,
                'hour' => intval($task_reset_time / 3600),
                'minute' => intval(($task_reset_time % 3600) / 60),
                'second' => intval($task_reset_time % 60),
            ];
        }
        if (!empty($task_last)) {
            $map = [
                'user_id' => $user_id
            ];
            if ($task_last['day'] == date("Y-m-d")) {
                $map['day'] = date("Y-m-d");
            } else {
                $map['day'] = date("Y-m-d", strtotime($task_last['create_time']));
            }
        } else {
            $map = [
                'user_id' => $user_id,
                'day' => date("Y-m-d"),
            ];
        }
        //总任务
        $day_task_all = $this->where($map)->whereIn('status', [self::STATUS_ING, self::STATUS_END])->count();
        //进行中的任务
        $day_task_ing = $this->where('user_id', $user_id)->where('status', self::STATUS_ING)->where('day', date("Y-m-d"))->count();
        return [
            'day_task_all' => $day_task_all,
            'day_task_ing' => $day_task_ing,
            'reset_time' => $reset_time
        ];
    }

    /**
     * 获取计划任务次数
     * @param $user_id
     * @return array
     * @throws \think\Exception
     */
    public function getTaskPlanNumber($user_id)
    {
        //进行中的任务
        $task_ing = $this->where('user_id', $user_id)->where('status', self::STATUS_ING)->where('task_type', TaskModel::TYPE_PLAN)->count();
        $task_all_end_num = $this->getPlanAllEndNum($user_id);
        return [
            'task_all' => $task_all_end_num['task_all'],
            'task_ing' => $task_ing,
            'task_end' => $task_all_end_num['task_end'],
        ];
    }

    /**
     * 获取任务列表
     * @param $user_id
     * @param int $type
     * @param boolean $day24
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTaskList($user_id, $type = self::TYPE_GOODS, $day24 = false)
    {

        if ($day24) {
            $last = $this->where('user_id', $user_id)->whereIn('task_type', [TaskModel::TYPE_GOODS, TaskModel::TYPE_PRODUCT])->order('id DESC')->find();
            if (!empty($last)) {
                $map = [
                    'user_id' => $user_id
                ];
                if ($last['day'] == date("Y-m-d")) {
                    $map['day'] = date("Y-m-d");
                } else {
                    $map['day'] = date("Y-m-d", strtotime($last['create_time']));
                }
            } else {
                $map = [
                    'user_id' => $user_id,
                    'day' => date("Y-m-d"),
                ];
            }

        } else {
            $map = [
                'user_id' => $user_id,
                'day' => date("Y-m-d"),
            ];
        }
        if ($type == self::TYPE_PLAN) {
            $day_task_ing_list1 = $this->where($map)->where('status', self::STATUS_ING)->whereIn('task_type', [TaskModel::TYPE_GOODS, TaskModel::TYPE_PRODUCT])->order('id DESC')->select();
            //无时间限制
            unset($map['day']);
            $day_task_ing_list = $this->where($map)->where('status', self::STATUS_ING)->whereIn('task_type', [TaskModel::TYPE_PLAN])->order('id DESC')->select();
            $day_task_ing_list = array_merge($day_task_ing_list, $day_task_ing_list1);

            $task_all_end_num = $this->getPlanAllEndNum($user_id);
            $task_end_num = $task_all_end_num['task_end'];
            $day_task_end_list_temp = $this->where($map)->where('status', self::STATUS_END)->whereIn('task_type', [TaskModel::TYPE_PLAN])->order('id DESC')->select();
            $day_task_end_list = [];
            for ($i = 0; $i < $task_end_num; $i++) {
                $day_task_end_list[] = $day_task_end_list_temp[$i];
            }
        } else {
            $day_task_ing_list = $this->where($map)->where('status', self::STATUS_ING)->whereIn('task_type', [TaskModel::TYPE_GOODS, TaskModel::TYPE_PRODUCT])->order('id DESC')->select();
            $day_task_end_list = $this->where($map)->where('status', self::STATUS_END)->whereIn('task_type', [TaskModel::TYPE_GOODS, TaskModel::TYPE_PRODUCT])->order('id DESC')->select();
        }
        return [
            'ing_list' => $day_task_ing_list,
            'end_list' => $day_task_end_list,
        ];
    }

    /**
     * 获取完成的计划任务次数
     * @param string $user_id
     * @return array|int|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getPlanAllEndNum($user_id = '')
    {
        $plan_id = UserModel::getFieldData($user_id, 'plan_id');
        $plan_model = new PlanModel();
        $plan = $plan_model->where('id', $plan_id)->find();
        if (empty($plan)) {
            return [
                'task_all' => 0,
                'task_end' => 0,
            ];
        }
        $plan_task_model = new PlanTaskModel();
        $plan_list = $plan_task_model->where('plan_id', $plan_id)->order('sort ASC')->select();
        $plan_list_next = $plan_task_model->where('plan_id', $plan['next_id'])->order('sort ASC')->select();
        $plan_list = array_merge($plan_list, $plan_list_next);
        $task_all = count($plan_list);
        //总任务
//        $task_all = $this->where('user_id', $user_id)->whereIn('task_type', [TaskModel::TYPE_PLAN, TaskModel::TYPE_SHARE])->count();
        //进行中的任务
        $task_ing = $this->where('user_id', $user_id)->where('status', self::STATUS_ING)->where('task_type', TaskModel::TYPE_PLAN)->count();

        $task_end_id = $this->where('user_id', $user_id)->where('status', self::STATUS_END)->where('task_type', TaskModel::TYPE_PLAN)->order('id DESC')->value('plan_task_id');
        //已完成任务
//        $task_end = $this->where('user_id', $user_id)->where('status', self::STATUS_END)->whereIn('task_type', TaskModel::TYPE_PLAN)->count();
        $task_end = 0;
        foreach ($plan_list as $key => $value) {
            if ($value['id'] == $task_end_id) {
                $task_end = $key + 1;
            }
        }
        if ($task_end >= $task_all) {
            $task_end = 0;
        }
        return [
            'task_all' => $task_all,
            'task_end' => $task_end,
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(LevelModel::class, 'level_id', 'level_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id', 'id');
    }
}
