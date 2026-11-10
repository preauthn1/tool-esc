<?php

namespace app\api\controller;

use app\common\model\ProductInfoModel;
use think\facade\Cache;
use think\response\Json;

class Product extends Base
{
    public function hot(): Json
    {
        $hot_list = $this->baseHot();
        return json(msg(0, $hot_list, lang('ok')));
    }

    public function collect()
    {
        $hot_list = $this->baseHot();
        $collect_index = array_rand($hot_list, 3);
        $collect_list = [];
        foreach ($collect_index as $k => $item) {
            $collect_list[] = $hot_list[$item];
        }
    }

    public function detail()
    {
        $hot_list = $this->baseHot();
        $all_index = array_rand($hot_list, 4);
        $all_list = [];
        foreach ($all_index as $k => $item) {
            $all_list[] = $hot_list[$item];
        }
    }

    private function baseHot(): array
    {
        $redis_temp = Cache::store('redis');
        $redis_key = "shop_markethuobi";
        $redis_key_info = "shop_productinfo";
        $temp_info = $redis_temp->get($redis_key_info);
        $redis = Cache::store('redis')->handler();

        if (empty($temp_info)) {
            $products = ProductInfoModel::where('isopen', 1)->where('isdelete', 0)->where('cid', 5)->select();
            $temp_info = serialize($products);
            $redis_temp->set($redis_key_info, $temp_info);
        } else {
            $products = unserialize($temp_info);
        }
        $hot_list = [];
        foreach ($products as $product) {
            $cache = json_decode($redis->hget($redis_key, $product['procode']), true);
            $hot_list[] = $cache;
        }
        return $hot_list;
    }
}
