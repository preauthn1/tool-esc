<?php

// +----------------------------------------------------------------------
// | snake
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://baiyf.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: NickBai <1902822973@qq.com>
// +----------------------------------------------------------------------

namespace app\admin\model;

use think\Model;
use think\model\relation\BelongsTo;

class CollectionDetailModel extends Model
{

    // 确定链接表名
    protected $name = 'collection_detail';


    const STATUS_PENDING = 1;
    const STATUS_SUCCESS = 3;
    const STATUS_FAIL = 4;

    static $statusMap = [
        self::STATUS_PENDING => '归集处理中',
        self::STATUS_SUCCESS => '归集成功',
        self::STATUS_FAIL    => '归集失败',
    ];

    public function recharge(): BelongsTo
    {
        return $this->belongsTo(RechargeModel::class, 'id', 'order_id');
    }
}
