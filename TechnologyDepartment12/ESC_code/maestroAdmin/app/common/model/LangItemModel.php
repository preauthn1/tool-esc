<?php

namespace app\common\model;

use think\Model;

class LangItemModel extends Model {

    // 确定链接表名
    protected $name = 'lang_items';
    protected $autoWriteTimestamp = 'timestamp';

    public function detail()
    {
        return $this->hasMany('LangItemDetailModel', 'item_id');
    }
}
