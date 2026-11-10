<?php

namespace app\common\model;

use think\Model;

class CollectModel extends Model {

    protected $autoWriteTimestamp = 'datetime';
    // 确定链接表名
    protected $name = 'collect';

    public function goods() {
        return $this->hasOne('GoodsModel', 'goods_id', 'goods_id');
    }
}
