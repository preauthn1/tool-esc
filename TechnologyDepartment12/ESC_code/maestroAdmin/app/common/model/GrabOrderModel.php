<?php

namespace app\common\model;

use think\Model;

class GrabOrderModel extends Model {

    protected $autoWriteTimestamp = 'datetime';
    // 确定链接表名
    protected $name = 'grab_order';

    public function user() {
        return $this->hasOne('UserModel', 'id', 'user_id')->field('id,nick_name,true_name,mobile,avatar,user_name,create_time');
    }
    
    public function goods() {
        return $this->hasOne('GoodsModel', 'goods_id', 'goods_id');
    }
    
    public function goodsAttr() {
        return $this->hasOne('GoodsAttrModel', 'id', 'attr_id');
    }

}
