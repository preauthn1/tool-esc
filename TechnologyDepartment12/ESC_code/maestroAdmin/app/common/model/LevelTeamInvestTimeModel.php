<?php

namespace app\common\model;

use think\Model;

class LevelTeamInvestTimeModel extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $table = 'qd_level_team_invest_time';

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
     * @param int $id
     * @return bool
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
     * @param int $level_id 等级ID
     * @return array|null 返回当前时间段的配置，如果不在任何时间段内返回null
     */
    public function getCurrentTimeConfig($level_id, $user_id)
    {
        $current_time = date('H:i:s');
        return $this->where([
            'level_id' => $level_id,
            'user_id' => $user_id,
            'status' => self::STATUS_ENABLE // 只获取正常状态的时段
        ])->where('start_time', '<=', $current_time)
          ->where('end_time', '>=', $current_time)
          ->find();
    }

    /**
     * 获取指定等级的理财时段列表
     * @param array $where 查询条件
     * @param int $offset 偏移量
     * @param int $limit 每页数量
     * @return array
     */
    public function getInvestTimeList($where = [], $offset = 0, $limit = 10)
    {
        return $this->where($where)
            ->order('start_time asc')
            ->limit($offset, $limit)
            ->select();
    }

    /**
     * 获取指定等级的理财时段总数
     * @param array $where 查询条件
     * @return int
     */
    public function getInvestTimeCount($where = [])
    {
        return $this->where($where)->count();
    }

    /**
     * 获取单个理财时段信息
     * @param int $id
     * @return array|null
     */
    public function getOneInvestTime($id)
    {
        return $this->where('id', $id)->find();
    }

    /**
     * 添加理财时段
     * @param array $data
     * @return int|false
     */
    public function addInvestTime($data)
    {
        return $this->save($data);
    }

    /**
     * 更新理财时段
     * @param array $data
     * @param int $id
     * @return int|false
     */
    public function updateInvestTime($data, $id)
    {
        return $this->where('id', $id)->update($data);
    }

    /**
     * 删除理财时段
     * @param int $id
     * @return int|false
     */
    public function deleteInvestTime($id)
    {
        return $this->where('id', $id)->delete();
    }
}
