<?php

namespace app\common\model;

use think\Model;

class GoodsCommentModel extends Model {

    protected $autoWriteTimestamp = 'datetime';
    // 确定链接表名
    protected $name = 'goods_comment';

}
