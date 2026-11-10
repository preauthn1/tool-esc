<?php

namespace app\common\model;

use think\Model;

class BankModel extends Model {

    protected $autoWriteTimestamp = true;
    // 确定链接表名
    protected $name = 'banks';

}
