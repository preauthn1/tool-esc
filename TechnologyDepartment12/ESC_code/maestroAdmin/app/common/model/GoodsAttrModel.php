<?php

namespace app\common\model;

use think\Model;

class GoodsAttrModel extends Model {

    protected $autoWriteTimestamp = false;
    // 确定链接表名
    protected $name = 'goods_attr';

    public function goods() {
        return $this->belongsTo('GoodsModel');
    }

}
