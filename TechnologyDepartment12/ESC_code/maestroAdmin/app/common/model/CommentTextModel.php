<?php

namespace app\common\model;

use think\Model;

class CommentTextModel extends Model {

    protected $autoWriteTimestamp = 'datetime';
    // 确定链接表名
    protected $name = 'comment_text';

}
