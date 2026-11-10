<?php
declare (strict_types=1);

namespace app\listener;

use app\common\repository\HomeUserRepository;
use app\common\repository\MemberWaterRepository;

class MatchFinanceProductSuccess
{
    /**
     * 事件监听处理
     *
     */
    public function handle($order): void
    {
        // 减少流水
        $this->subWater($order);
    }

    private function subWater($order): void
    {
        $can_negative = false;
        $order_amount = $order['money'];
        $user_id = $order['user_id'];
        $user_money = $order['old_user_money'];

        if ($user_money >= 0) {
            if ($user_money < $order_amount) {
                $user = (new HomeUserRepository())->findWhere(['id' => $user_id],
                    ['is_xuni', 'user_money', 'water_money']);
                $water_money = $user->water_money;

                $can_negative = true;
                if ($water_money < $order_amount) {
                    $order_amount = $order_amount - $user_money + $water_money;
                }
            }
        }

        (new MemberWaterRepository())->subWater($user_id, 12, $order['id'], $order_amount, 0, $can_negative);
    }
}
