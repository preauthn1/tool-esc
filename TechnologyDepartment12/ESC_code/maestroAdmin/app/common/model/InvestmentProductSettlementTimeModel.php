<?php

namespace app\common\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Model;

class InvestmentProductSettlementTimeModel extends Model
{

    // 确定链接表名
    protected $name = 'investment_product_settlement_time';
    protected $autoWriteTimestamp = true;

    /**
     * 状态常量
     */
    const STATUS_DISABLE = 0; // 停用
    const STATUS_ENABLE = 1;  // 正常

    /**
     * 状态映射
     */
    public static $statusMap = [
        self::STATUS_DISABLE => '停用',
        self::STATUS_ENABLE => '正常'
    ];


    /**
     * 切换状态
     * @param  int  $id
     * @return TaskSessionModel|false
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function toggleStatus($id)
    {
        $info = $this->where('id', $id)->find();
        if (!$info) {
            return false;
        }

        return $this->where('id', $id)->update([
            'status' => $info['status'] == self::STATUS_ENABLE ? self::STATUS_DISABLE : self::STATUS_ENABLE,
            'update_time' => time()
        ]);
    }

}
