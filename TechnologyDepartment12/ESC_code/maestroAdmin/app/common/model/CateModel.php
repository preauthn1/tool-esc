<?php

namespace app\common\model;

use think\Model;

class CateModel extends Model {

    protected $autoWriteTimestamp = 'datetime';
    // 确定链接表名
    protected $name = 'goods_cate';

}
