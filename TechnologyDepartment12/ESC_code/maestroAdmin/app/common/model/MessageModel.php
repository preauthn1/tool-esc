<?php

namespace app\common\model;

use think\Model;

class MessageModel extends Model {

    protected $autoWriteTimestamp = 'timestamp';
    protected $updateTime = false;
    // 确定链接表名
    protected $name = 'message';
    
    protected $insert = ['ip'];

    protected function setIpAttr() {
        return getip();
    }
}
