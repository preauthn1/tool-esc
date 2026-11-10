<?php

namespace app\services;

use think\facade\Db;
use Carbon\Carbon;
use think\facade\Cache;
use app\common\model\GoodsModel;
use app\common\model\CollectModel;
use app\common\model\CommentTextModel;
use app\common\model\GoodsCommentModel;

class CommonService {

    /**
     * 获取商品价格分区设置
     * @param int $index
     * @return array
     */
    public static function getGoodsPricePartConfig($index = null) {
        $config = getConfig();
        $pd_part = config('my.pd_part');
        foreach ($pd_part as $i => $item) {
            foreach ($item as $key => $value) {
                $pd_part[$i][$key] = isset($config["pd_{$key}_{$i}"]) ? $config["pd_{$key}_{$i}"] : $item[$key];
            }
        }

        return $index !== null && isset($pd_part[$index]) ? $pd_part[$index] : $pd_part;
    }


    public static function getGoodsList($area,$amount = 10) {
        $part = self::getGoodsPricePartConfig($area);
        if (!isset($part['price_min'])) {
            return [];
        }
        $goods_list = GoodsModel::where('retail_price', '>=', $part['price_min'])->where('retail_price', '<=', $part['price_max'])->limit($amount)->order('retail_price ASC')->select();
        if (empty($goods_list)) {
            return [];
        }
        return $goods_list;
    }
    /**
     * 全部商品/优惠商品（新UI不以价格阶段来区分）
     * @param $area
     * @return array|false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getGoodsList1($area,$amount=10) {
        $where[] = ['is_discount', '=', $area];
        $goods_list = GoodsModel::where($where)->limit($amount)->order('retail_price ASC')->select();
        if (empty($goods_list)) {
            return [];
        }
        return $goods_list;
    }

    /**
     * 获取商品信息
     * @param type $user_id
     * @param type $goods_id
     */
    public static function getGoods($goods_id) {
        $info = GoodsModel::where('goods_id', $goods_id)->find();
        return $info;
    }

    /**
     * 收藏商品
     * @param type $user_id
     * @param type $goods_id
     */
    public static function collectGoods($user_id, $goods_id) {
        $info = CollectModel::where('user_id', $user_id)->where('goods_id', $goods_id)->find();
        if (empty($info)) {
            CollectModel::create([
                'user_id' => $user_id,
                'goods_id' => $goods_id,
            ]);
        }else{
            $info->delete();
        }
    }

    /**
     * 判断是否已经收藏了商品
     * @param type $user_id
     * @param type $goods_id
     * @return type
     */
    public static function isCollectGoods($user_id, $goods_id) {
        $info = CollectModel::where('user_id', $user_id)->where('goods_id', $goods_id)->find();
        return empty($info) ? false : true;
    }

    /**
     * 给商品生成随机评论
     * @param type $goods_id
     * @param type $order_id
     * @param type $user_id
     */
    public static function generateGoodsComment($goods_id, $order_id = null, $user_id = null) {
        $text = CommentTextModel::orderRaw('rand()')->value('text');
        if (empty($text)) {
            return false;
        }

        $data = [
            'user_id' => $user_id ?? 0,
            'order_id' => $order_id ?? 0,
            'goods_id' => $goods_id,
            'avatar' => random_int(1, 10),
            'nick_name' => hidnstr(random(10), 1),
            'text' => $text,
            'rate' => random_int(475, 500) / 100
        ];
        GoodsCommentModel::create($data);

        return true;
    }
    /**
     * 获取商品平均评分
     * @param type $goods_id
     */
    public static function getGoodsAvgRate($goods_id) {
        $rate = GoodsCommentModel::where('goods_id', $goods_id)->avg('rate');

        return empty($rate) ? 5 : sprintf4f($rate, true);
    }
}
