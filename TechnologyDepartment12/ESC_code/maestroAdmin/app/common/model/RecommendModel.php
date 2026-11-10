<?php

namespace app\common\model;

use think\Model;

class RecommendModel extends Model
{

    protected $autoWriteTimestamp = 'datetime';
    // 确定链接表名
    protected $name = 'recommend';

    /**
     * 获取推荐活动数据
     * @param string $id
     * @return array|bool|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getRecommend($id = '')
    {
        if (empty($id)) {
            $temp = $this->where([])->order('id DESC')->find();
        } else {
            $temp = $this->where('id', $id)->find();
        }
        if (empty($temp)) {
            return [];
        }
        return $temp;
    }
}
