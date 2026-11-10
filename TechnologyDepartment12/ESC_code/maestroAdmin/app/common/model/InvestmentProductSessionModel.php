<?php

namespace app\common\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Model;

class InvestmentProductSessionModel extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $table = 'qd_investment_product_session';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

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
     * @return false
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

    /**
     * 获取当前时间段的理财配置
     * @return array|null 返回当前时间段的配置，如果不在任何时间段内返回null
     */
    public function getCurrentTimeConfig()
    {
        $current_time = date('H:i:s');
        return $this->where([
            'status' => self::STATUS_ENABLE // 只获取正常状态的时段
        ])->where('start_time', '<=', $current_time)
          ->where('end_time', '>=', $current_time)
          ->find();
    }

    /**
     * 获取指定等级的理财时段列表
     * @param  array  $where  查询条件
     * @param  int  $offset  偏移量
     * @param  int  $limit  每页数量
     */
    public function getinvestmentProductSessionList(array $where = [], int $offset = 0, int $limit = 10)
    {
        try {
            return $this->where($where)
                ->order('start_time asc')
                ->limit($offset, $limit)
                ->select();
        } catch (Exception $e) {
             return [];
        }
    }

    /**
     * 获取指定等级的理财时段总数
     * @param array $where 查询条件
     * @return int
     */
    public function getinvestmentProductSessionCount($where = [])
    {
        return $this->where($where)->count();
    }

    /**
     * 获取单个理财时段信息
     * @param int $id
     * @return array|null
     */
    public function getOneinvestmentProductSession($id)
    {
        return $this->where('id', $id)->find();
    }

    /**
     * 添加理财时段
     * @param array $data
     * @return int|false
     */
    public function addinvestmentProductSession($data)
    {
        return $this->save($data);
    }

    /**
     * 更新理财时段
     * @param array $data
     * @param int $id
     * @return int|false
     */
    public function updateinvestmentProductSession($data, $id)
    {
        return $this->where('id', $id)->update($data);
    }

    /**
     * 删除理财时段
     * @param int $id
     * @return int|false
     */
    public function deleteinvestmentProductSession($id)
    {
        return $this->where('id', $id)->delete();
    }
}
