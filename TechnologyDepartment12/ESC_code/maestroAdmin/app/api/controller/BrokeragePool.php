<?php

namespace app\api\controller;

use app\common\model\MoneyLogModel;
use app\common\repository\HomeUserBrokeragePoolRepository;
use think\response\Json;
use util\Locker;

class BrokeragePool extends Base
{
// 佣金池
    public function index()
    {
        // 获取用户佣金池数据
        $brokerage_pool = $this->userInfo['brokerage_pool'];
        // 可领取金额
        $brokerage_pool_repository = new HomeUserBrokeragePoolRepository();
        $brokerage_poll_data = $brokerage_pool_repository->findWhere([
            ['user_id', '=', $this->userid]
        ], ['user_id', 'receivable', 'received']);

        $temp_list = $brokerage_pool_repository->findWhere([['user_id', '=', $this->userid]]);

        $receivable = $received = 0;
        if ($brokerage_poll_data) {
            $receivable = $brokerage_poll_data->receivable;
            $received = $brokerage_poll_data->received;
        }
        $receivable = min($receivable - $received, $brokerage_pool - $received);
        $data = [
            'brokerage_pool' => i18n_currency(max($brokerage_pool - $received - $receivable, 0)),
            'receivable' => i18n_currency($receivable),
            'receivable_value' => $receivable,
            'receivable_end' => empty($temp_list) ? 0 : 1,
            'received' => i18n_currency($received),
        ];
        return json(msg(0, $data, lang('success')));
    }

    // 领取佣金池佣金
    public function receive(): Json
    {
        (new Locker('brokerage_pool_receive_lock_' . $this->userid))->lock();
        $user_id = $this->userid;
        // 获取用户佣金池数据
        $brokerage_pool = $this->userInfo['brokerage_pool'];
        // 可领取金额
        $brokerage_pool_repository = new HomeUserBrokeragePoolRepository();
        $brokerage_poll_data = $brokerage_pool_repository->findWhere([
            ['user_id', '=', $user_id]
        ], ['user_id', 'receivable', 'received']);

        $receivable = $received = 0;
        if ($brokerage_poll_data) {
            $receivable = $brokerage_poll_data->receivable; // 应可领取
            $received = $brokerage_poll_data->received;
        }

        // 实际可领取
        $amount = min($receivable - $received, $brokerage_pool - $received);
        if ($amount <= 0) {
            return json(msg('-1', [], lang('暂无可领取的额度')));
        }
        $money_log_model = new MoneyLogModel();
        $res = $money_log_model->changeMoney($user_id, $amount, MoneyLogModel::TYPE_USER, 'add', '领取佣金池佣金',
            '领取佣金池佣金' . $amount, 25);
        if ($res['code'] !== 1) {
            return json(msg(-1, '', lang($res['msg'])));
        }
        $brokerage_pool_repository->model()
            ->where('user_id', $user_id)
            ->setInc('received', $amount);
        return json(msg(0, [], lang('领取成功')));
    }
}
