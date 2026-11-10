<?php

namespace app\common\model;

use think\Model;

class DomainModel extends Model {

    protected $autoWriteTimestamp = 'datetime';
    // 确定链接表名
    protected $name = 'domain';

}
