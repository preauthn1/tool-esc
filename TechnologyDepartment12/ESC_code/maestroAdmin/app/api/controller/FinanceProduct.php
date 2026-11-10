<?php

namespace app\api\controller;

use app\admin\model\AdvertisSpaceModel;
use app\admin\model\UserModel;
use app\api\transform\FinanceProductOrderTransform;
use app\common\model\ConfigModel;
use app\common\model\MemberCreditScoreModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\HomeUserAdBufferCountdownConfigRepository;
use app\common\repository\HomeUserExcessAdRatioConfigRepository;
use app\common\repository\LevelRepository;
use Carbon\Carbon;
use think\db\Where;
use think\facade\Cache;
use think\facade\Db;
use think\response\Json;

class FinanceProduct extends Base
{
    public function index(
        $level_id,
        LevelRepository $levelRepository,
        FinanceProductOrderRepository $orderRepository,
        FinanceProductOrderTransform $transform
    ): Json
    {
        // 判断用户等级是否有效
        $user_id = $this->userid;
        // 获取所有广告位数量
        $level = $levelRepository->findwhere([['level_id', '=', $level_id]]);
        if (!$level) {
            return json(msg(-1, '', lang('该广告位不存在')));
        }
        if ($level->is_can_buy == 0) {
            return json(msg(-1, '', lang('该广告位暂未开放购买')));
        }
        $redis = Cache::store('redis');

        $user_excess_ad_ratio_config = null;
        $finance_product_order_repository = new FinanceProductOrderRepository();
        $tree_ids = explode('/', $this->userInfo['tree_id']);
        if (!empty($tree_ids)) {
            // 倒序
            $tree_ids = array_reverse(array_filter($tree_ids));
        }
        foreach ($tree_ids as $tree_id) {
            $config = (new HomeUserExcessAdRatioConfigRepository())->getUserExcessAdRatioConfig($tree_id, $level_id);
            if ($config) {
                $ratio = array_filter(json_decode($config->ratio, true));
                if (count($ratio) == 0) {
                    continue;
                }
                if ($tree_id == $user_id || $config->subordinate == 1) {
                    $user_excess_ad_ratio_config = $config;
                    break;
                }
            }
        }

        if ($user_excess_ad_ratio_config) {
            $invest_num = count(json_decode($user_excess_ad_ratio_config['ratio'], true));
        } else {
            // 单控群控
            list($level, $level_first) = $finance_product_order_repository->getTeamLevel($level, $user_id, $level_id);
            $invest_num = $level->invest_num;
        }


        list($orders, $task_session) = $orderRepository->getLevelSessionOrdersV2($level_id, $this->userid);

        $matched_order_count = 0;
        $pending_order_count = 0;
        if ($orders) {
            if ($level->type == LevelRepository::TYPE_EXPERIENCE) {
                //如果是体验区 不判断level_id
                // 未匹配数量
                $pending_order_count = count($orders->filter(function ($item) use ($level_id) {
                    return $item['status'] == FinanceProductOrderRepository::STATUS_PENDING;
                }));
                // 已匹配数量
                $matched_order_count = count($orders->filter(function ($item) use ($level_id) {
                    return $item['status'] != FinanceProductOrderRepository::STATUS_PENDING;
                }));
            } else {
                // 未匹配数量
                $pending_order_count = count($orders->filter(function ($item) use ($level_id) {
                    return $item['status'] == FinanceProductOrderRepository::STATUS_PENDING && $item['level_id'] == $level_id;
                }));
                // 已匹配数量
                $matched_order_count = count($orders->filter(function ($item) use ($level_id) {
                    return $item['status'] != FinanceProductOrderRepository::STATUS_PENDING && $item['level_id'] == $level_id;
                }));
            }
        } else {
            // 未匹配过，判断是否有团队广告位配置
            list($level, $level_first) = (new FinanceProductOrderRepository())->getTeamLevel($level, $user_id, $level_id);
        }

        $order_count = $pending_order_count + $matched_order_count;

        // 获取待支付订单列表
        $order_list = $orderRepository->selectWhere([
            ['user_id', '=', $this->userid],
            ['level_id', '=', $level_id],
            ['status', '=', FinanceProductOrderRepository::STATUS_WAIT_BUY]
        ]);

        $data = [
            'product_count' => $order_count > 0 ? $order_count : $invest_num,
            'product_order_count' => $matched_order_count,
            'wait_list' => $transform->transformCollection($order_list->toArray()),
        ];
        return json(msg(0, $data, lang('获取成功')));
    }

    /**
     * 获取广告位详情
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function details(): Json
    {
        $level_id = input('level_id', 0);
        $LevelRepository = new LevelRepository();
        $level = $LevelRepository->findwhere([['level_id', '=', $level_id]]);
        if (!$level) {
            return json(msg(-1, '', lang('该广告位不存在')));
        }
        $advertis_list = Db::name('advertis_num')->order('id', 'asc')->select();
        if (!empty($advertis_list)) {
            $advertis_list = $advertis_list->toArray();
        } else {
            $advertis_list = [];
        }
        $advertis_arr = [];
        $advertis_arr[] = [
            'name' => lang($level->level_name),
            'cpm' => lang('数量(IMP)')
        ];
        foreach ($advertis_list as $key => $value) {
            $advertis_arr[] = [
                'name' => $value['advertis_name'],
                'cpm' => $value['num'],
            ];
        }
        $data = [
            'level_id' => $level_id,
            'fee_rate' => $level->fee_rate,
            'advertis_arr' => $advertis_arr
        ];
        return json(msg(0, $data, lang('获取成功')));
    }

    // 获取流量包超额匹配配置
    public function overdue_config(): Json
    {
        $user_config = [];
        $config = (new ConfigModel)->getConfig();
        $finance_product_dialog_count_down = $config['finance_product_dialog_count_down'];
        $tree_ids = explode('/', $this->userInfo['tree_id']);
        if (!empty($tree_ids)) {
            // 倒序
            $tree_ids = array_reverse(array_filter($tree_ids));
        }
        foreach ($tree_ids as $tree_id) {
            $config = (new HomeUserAdBufferCountdownConfigRepository())->getUserAdBufferCountdownConfig($tree_id);;
            if ($config && ($tree_id == $this->userid || $config->apply_to_subordinates == 1)) {
                $user_config = $config;
                break;
            }
        }
        if (!$user_config) {
            $config = (new ConfigModel)->getConfig();
        } else {
            $config = $user_config;
        }
        $data = [
            'finance_product_no_can_click' => $config['finance_product_no_can_click'],
            'finance_product_yes_can_click' => $config['finance_product_yes_can_click'],
            'finance_product_dialog_count_down' => $finance_product_dialog_count_down,
            'finance_product_button_count_down' => $config['finance_product_button_count_down'], // 按钮loading时长
            'finance_product_dialog_btn_cancel' => $config['finance_product_dialog_btn_cancel'], // 按钮loading时长
        ];
        return json(msg(0, $data, ''));
    }

    /**
     * 待购买的广告位列表
     * @param FinanceProductOrderTransform $transform
     * @param FinanceProductOrderRepository $orderRepository
     * @return Json
     */
    public function waitingPayList(
        FinanceProductOrderTransform $transform,
        FinanceProductOrderRepository $orderRepository
    ): Json
    {
        $where = [];
        $where[] = ['status', 'in', [FinanceProductOrderRepository::STATUS_WAIT_BUY]];
        $where[] = ['end_time', '>', time()];
        $orders = $orderRepository->selectWhere($where);
        $data = $transform->transformCollection($orders->toArray());
        return json(msg(0, $data, lang('success')));
    }

    public function order_list(FinanceProductOrderTransform $transform): Json
    {
        $page = input('page', 1);
        $size = input('limit', 10);
        $status = input('status', '');
        $where = [];
        $where[] = ['status', '=', $status];
        $where[] = ['user_id', '=', $this->userid];
        $orderRepository = new FinanceProductOrderRepository();
        $list = $orderRepository->order(['start_time' => 'desc'])
            ->where($where)
            ->paginate(['page' => $page, 'list_rows' => $size]);
        $data = $transform->transformPages($list->toArray());
        return json(msg(0, $data, lang('获取成功')));
    }

    /**
     * 理财订单统计
     * @param FinanceProductOrderRepository $orderRepository
     * @return Json
     */
    public function order_count(FinanceProductOrderRepository $orderRepository): Json
    {

        $where = [
            ['user_id', '=', $this->userid],
            ['status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]],
        ];
        $orders = $orderRepository->selectWhere($where, ['id', 'money', 'profit_money', 'end_time']);
        $money = collect($orders)->column('money');
        $profit_money = collect($orders)->column('profit_money');


        $yesterday_where = [
            ['user_id', '=', $this->userid],
            [
                'status',
                'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]
            ],
            [
                'end_time',
                'between',
                [Carbon::now()->subDay()->startOfday()->timestamp, Carbon::now()->subDay()->endOfday()->timestamp]
            ]
        ];
        $yesterday_orders = $orderRepository->selectWhere($yesterday_where,
            ['id', 'money', 'profit_money', 'end_time']);;
        $yesterday_profit_money = collect($yesterday_orders)->column('profit_money');
        $sum_money = array_sum($money);
        $data = [
            'order_money' => sprintf4f($sum_money), // 理财订单总金额
            'order_num' => count($orders), // 理财订单数量
            'total_invest_income' => sprintf4f(array_sum($profit_money)), // 理财收益总金额
            'yesterday_order_income' => sprintf4f(array_sum($yesterday_profit_money)),
            // 昨日理财收益金额
        ];
        return json(msg(0, $data, lang('获取成功')));
    }

    // 定时轮训获取匹配结果
    public function get_finance_product_order($level_id): Json
    {
        $user_id = $this->userid;
        $redis = Cache::store('redis');
        $redis_key = $user_id . '_' . $level_id . '_match_status';
        $result = $redis->get($redis_key);
        if (!$result) {
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
        $redis->delete($redis_key);
        return json($data);
    }
}
