<?php

namespace app\admin\model;

use app\common\model\HomeUserExcessAdRatioConfigModel;
use app\common\model\MemberLevelModel;
use app\common\repository\LevelRepository;
use app\common\repository\TaskRepository;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;
use think\model\relation\BelongsTo;
use think\model\relation\HasMany;

class LevelModel extends Model
{

    // 确定链接表名
    protected $name = 'level';
    protected $autoWriteTimestamp = true;

    /**
     * 根据搜索条件获取商家列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getLevelByWhere($where, $offset, $limit)
    {
        return $this->where($where)->limit($offset, $limit)->order(['sort' => 'asc'])->select();
    }

    /**
     * 根据搜索条件获取所有的商家数量
     * @param $where
     */
    public function getAllLevel($where)
    {
        return $this->alias('user')->where($where)->count();
    }

    /**
     * 插入管理员信息
     * @param $param
     */
    public function insertLevel($param)
    {
        try {
            $result = $this->validate('LevelValidate')->save($param);
            if (false === $result) {
                // 验证失败 输出错误信息
                return msg(-1, '', $this->getError());
            } else {
                insert_operation_log('会员等级:增加', $param);
                return msg(1, url('level/index')->build(), '添加等级成功');
            }
        } catch (PDOException $e) {

            return msg(-2, '', $e->getMessage());
        }
    }

    /**
     * 编辑管理员信息
     * @param $param
     */
    public function editLevel($param, $id)
    {
        try {
            $result = $this->where(['level_id' => $id])->update($param);
            if (false === $result) {
                // 验证失败 输出错误信息
                return msg(-1, '', $this->getError());
            } else {
                insert_operation_log('会员等级:修改', $param);
                return msg(1, url('level/index')->build(), '编辑等级成功');
            }
        } catch (\think\Exception $e) {
            return msg(-2, '', $e->getMessage());
        }
    }

    /**
     * 根据管理员id获取角色信息
     * @param $id
     */
    public function getOneLevel($id)
    {
        return $this->where('level_id', $id)->find();
    }

    /**
     * 删除管理员
     * @param $id
     */
    public function delLevel($id)
    {
        try {

            $this->where('level_id', $id)->delete();
            return msg(1, '', '删除等级成功');
        } catch (PDOException $e) {
            return msg(-1, '', $e->getMessage());
        }
    }

    /**
     * 更新管理员状态
     * @param array $param
     */
    public function updateStatus($param = [], $uid = 0)
    {
        try {
            $this->where('level_id', $uid)->update($param);
            return msg(1, '', 'ok');
        } catch (\Exception $e) {

            return msg(-1, '', $e->getMessage());
        }
    }

    // 获取所有分类
    public function getLevel()
    {
        return $this->where('type', 'in', [LevelRepository::TYPE_NORMAL, LevelRepository::TYPE_PROMOTION])->select();
    }

    public function setSettingAttr($val)
    {
        return empty($val) ? null : serialize($val);
    }

    public function getSettingAttr($val)
    {
        return empty($val) ? [] : unserialize($val);
    }

    /**
     * 获取等级名称
     * @param $id
     * @return mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getLevelName($id)
    {
        $data = self::where('level_id', $id)->field('level_name')->find();
        if (empty($data)) {
            return "";
        }
        return $data['level_name'];
    }

    public function userLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevelModel::class, 'level_id', 'to_level');
    }

    /**
     * 检查是否可以在当前时间解锁
     * @param  int  $level_id
     * @return bool
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function canUnlockAtCurrentTime(int $level_id): bool
    {
        $task_reset_type = ConfigModel::getConfigValue('task_reset_type');
        if ($task_reset_type == 1 || $task_reset_type == 2) {
                $level = $this->where('level_id', $level_id)->find();
            if(empty($level)) {
                return false;
            }

            // 如果没有设置时间限制,则允许解锁
            if(empty($level['unlock_time_start']) || empty($level['unlock_time_end'])) {
                return true;
            }

            // 获取当前时间
            $now = date('H:i:s');
            return $now >= $level['unlock_time_start'] && $now <= $level['unlock_time_end'];
        } else if ($task_reset_type == 3) { // 一天多场
            if ((new TaskRepository())->getTaskTimeLeft($level_id) > 0) {
                return true;
            }
        }
        return false;
    }

    public function levelTeam(): HasMany
    {
        return $this->hasMany(LevelTeamModel::class, 'level_id', 'level_id');
    }

    public function userExcessAdRatioConfig(): HasMany
    {
        return $this->hasMany(HomeUserExcessAdRatioConfigModel::class, 'level_id', 'level_id');
    }
}
