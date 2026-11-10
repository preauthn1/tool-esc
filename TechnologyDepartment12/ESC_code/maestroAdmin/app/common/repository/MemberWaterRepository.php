<?php

namespace app\common\repository;

use app\common\model\ConfigModel;
use app\common\model\MemberWaterModel;

class MemberWaterRepository extends Repository
{
    protected $model;

    public function __construct()
    {
        //绑定模型
        $this->model = new MemberWaterModel();
    }

    public function addWater($user_id, $detail_type, $order_id, $amount, $admin_id = 0)
    {
        $remark = '增加流水';
        $title = '增加流水';
        switch ($detail_type) {
            case 0:
                $remark = '后台手动增加流水';
                $title = '后台手动增加流水';
                break;
            case 1:
                $remark = '充值增加流水';
                $title = '充值增加流水';
                break;
        }
        $rate = ConfigModel::getConfigValue('withdraw_water_limit');
        return $this->changeWater($user_id, abs($amount), $order_id, $remark, $detail_type, $rate, $title, $admin_id);
    }

    public function subWater($user_id, $detail_type, $order_id, $amount, $admin_id = 0, $can_negative = true)
    {
        $remark = '减少流水';
        $title = '减少流水';
        switch ($detail_type) {
            case 0:
                $remark = '后台手动减少流水';
                $title = '后台手动减少流水';
                break;
            case 12:
                $remark = '购买广告位减少流水';
                $title = '购买广告位减少流水';
        }
        return $this->changeWater($user_id, -abs($amount), $order_id, $remark, $detail_type, 100, $title, $admin_id, $can_negative);
    }

    public function changeWater($user_id, $amount, $order_id, $remark, $detail_type, $rate, $title = '', $admin_id = 0, $can_negative = true)
    {
        $user = (new HomeUserRepository())->findWhere(['id' => $user_id], ['is_xuni', 'user_money', 'water_money']);
        $water = $amount * $rate / 100;
        $new_water = $user->water_money + $water;
        if(!$can_negative && $new_water < 0) {
            $new_water = 0;
        }
        $old_water = $user->water_money;
        // 更新用户表的流水
        (new HomeUserRepository())->update(['id' => $user_id], ['water_money' => $new_water]);
        $res = $this->create([
            'user_id'     => $user_id,
            'is_xuni'     => $user->is_xuni,
            'user_water'  => $old_water,//操作前余额
            'water'       => $water,
            'last_water'  => $new_water,//当前余额
            'amount'      => abs($amount),
            'rate'        => $rate,
            'order_id'    => $order_id,
            'admin_id'    => $admin_id,
            'remarks'     => $remark,
            'title'       => $title,
            'detail_type' => $detail_type,
        ]);
        if ($res) {
            return ['code' => 1];
        }
        return ['code' => 0];
    }
}
