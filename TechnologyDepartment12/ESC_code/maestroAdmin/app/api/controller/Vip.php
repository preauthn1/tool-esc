<?php

namespace app\api\controller;

use app\admin\model\LevelModel;
use app\common\model\GoodsModel;
use app\common\model\UserFakeModel;
use think\facade\Cache;
use think\response\Json;

class Vip extends Base
{
    public function index(): Json
    {
        //获取等级列表
        $level_list = LevelModel::where('is_show', 1)->order('sort')->select();

        $redis = Cache::store('redis');
        $redis_key = "index_vip_data";
        if ($this->configInfo['index_show_data'] == 'goods') {
            if ($redis->has($redis_key)) {
                $index_vip_data = $redis->get($redis_key);
            } else {
                foreach ($level_list as $k => $level) {
                    $goods = GoodsModel::where('level_id', $level->level_id)->orderRaw('RAND()')->find();
                    $level_list[$k]['goods'] = $goods;
                }
                $redis->set($redis_key, $level_list);
                $index_vip_data = $level_list;
            }
        } else {
            $index_vip_data = $level_list;
        }

        // img 字段加上拼接域名
        foreach ($index_vip_data as &$item) {
            $item['img'] = toWebUrl($item['img']);
            if (isset($item['goods'])) {
                $item['goods']['pic'] = toWebUrl($item['goods']['pic']);
                $item['goods']['main_pic'] = toWebUrl($item['goods']['main_pic']);
            }
        }
        unset($item);

        return json(msg(0, $index_vip_data, ''));
    }

    public function vip_list()
    {
        $vip_list = UserFakeModel::getListAll();
        return json(msg(0, $vip_list, ''));
    }

}
