<?php

namespace app\common\model;

use think\facade\Db;
use think\Model;

class GoodsModel extends Model
{

    protected $autoWriteTimestamp = true;
    // 确定链接表名
    protected $name = 'goods';

    /**
     * 更新商品状态
     * @param array $param
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
     * 获取商品产品任务
     * @param $level_id
     * @param $num
     * @return array|false|string
     */
    public function getProductList($level_id, $num)
    {
        if (empty($num)) {
            return [];
        }
        $map = [
            'level_id' => $level_id,
            'status' => 1
        ];
        $list = $this->where($map)->orderRaw("rand()")->limit($num)->column('id,name,pic as img,price', 'id');
        $list_item = [];
        foreach ($list as $value) {
            $value['task_type'] = TaskModel::TYPE_GOODS;
            $value['img'] = toMedia($value['img'], '/upload/', 1);
            $list_item[] = $value;
        }
        $len = count($list_item);
        $temp_num = $num - $len;
        for ($i = 0; $i < $temp_num; $i++) {
            $list_item[] = $list_item[rand(0, $len)];
        }
        return $list_item;
    }

    /**
     * 获取计划任务随机商品
     * @return array|mixed
     */
    public function getProductPlanRand()
    {
        $map = [
            'status' => 1
        ];
        $list = $this->where($map)->orderRaw("rand()")->field('id,name,pic as img,price')->find();
        $item = [];
        if (!empty($list)) {
            $item = [
                'id' => $list['id'],
                'name' => $list['name'],
                'img' => toMedia($list['img'], '/upload/', 1),
                'price' => $list['price'],
                'task_type' => TaskModel::TYPE_PLAN
            ];
        }
        return $item;
    }
}
