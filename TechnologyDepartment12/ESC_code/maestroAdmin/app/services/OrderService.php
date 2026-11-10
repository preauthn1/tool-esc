<?php

namespace app\services;

use app\common\model\OrderModel;
use think\facade\Db;

class OrderService {
    
    /**
     * 获取拼单完成人数
     * @param type $id
     * @return type
     */
    public static function getRepurchaseUsersByOrderId($id) {
        $count = OrderModel::where('id|parent_id', $id)->where('status', 2)->where('repurchase_status', 3)->count();
        return intval($count);
    }
    
    /**
     * 获取拼单人数
     * @param type $id
     * @return type
     */
    public static function getUsersByOrderId($id) {
        $count = OrderModel::where('id|parent_id', $id)->count();
        return intval($count);
    }
    
    /**
     * 完成拼单
     * @param type $order_id
     * @return boolean
     */
    public static function completeOrder($order_id) {
        $order_info = OrderModel::with('goods')->where('id', $order_id)->where('parent_id', 0)->find();
        if (!$order_info) {
            return false;
        }
        if (empty($order_info['goods'])) {
            return false;
        }
        $order_users = OrderModel::where('parent_id', $order_id)->whereOr('id', $order_id)->count();
        if ($order_users >= $order_info['goods']['order_number']) {
            OrderModel::where('parent_id', $order_id)->whereOr('id', $order_id)->where('status', '<>', 2)->update(['status' => 2]);
        }
        
        return true;
    }

    /**
     * 完成拼单——CP共同完成拼单
     * @param type $order_id
     * @return boolean
     */
    public static function completeOrder_cp($order_id) {
        $order_info = OrderModel::with('goods')->where('id', $order_id)->where('parent_id', 0)->find();
        if (!$order_info) {
            return false;
        }
        if (empty($order_info['goods'])) {
            return false;
        }
        $order_users = OrderModel::where('parent_id', $order_id)->find();
        if ($order_info['paid_status']==2 && $order_users['paid_status']==2) {//A、B都完成付款
            OrderModel::where('parent_id', $order_id)->whereOr('id', $order_id)->where('status', '<>', 2)->update(['status' => 2]);
        }
        return true;
    }

    /**
     * 删除错误订单（多设置的cp发起的订单）
     * @param type $order_id
     * @param type $user_id
     * @return boolean
     */
    /*public static function delOtherOrder($order_id, $user_id) {
        $order_info = OrderModel::with('goods')->where('id', $order_id)->where('parent_id', 0)->find();
        if (!$order_info) {
            return false;
        }
        if (empty($order_info['goods'])) {
            return false;
        }
        OrderModel::where('parent_id', '<>', $order_id)->where('id', '<>', $order_id)->where('status', 1)->where('specify_user_id', $user_id)->delete();
        return true;
    }*/
    
    /**
     * 获取正在拼单的订单
     * @param type $goods_id
     * @param type $user_id
     * @return type
     */
    public static function getUndoneOrderByGoodsId($goods_id, $user_id, $cp_id) {
        return OrderModel::with('user')->where('goods_id', $goods_id)
            ->where(function($query) use($user_id, $cp_id){
                $query->whereIn('user_id', function($query) use($user_id, $cp_id){
                    $query->name('home_user')->where(function($query) use($user_id, $cp_id){
                        $query->where('cp_id', $user_id)->whereOr('id', $cp_id)->whereOr('id', $user_id);
                    })->field('id');
                })->whereOr('is_xuni', 1);
            })->where('status', 1)->where('parent_id', 0)->orderRaw('rand()')->limit(3)->select();
    }

    /**
     * 获取正在被邀请拼单的订单
     * @param type $user_id
     * @return type
     */
    public static function getOrderByUser($user_id) {
        return Db::name('order a')->where('a.specify_user_id', $user_id)
            ->where('a.status', 1)->where('NOT EXISTS (select * from qd_order b where b.parent_id=a.id)')->find();
    }
}
