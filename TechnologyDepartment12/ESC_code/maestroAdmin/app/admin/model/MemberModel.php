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

use app\common\model\FinanceProductOrderModel;
use app\common\model\HomeUserBrokeragePoolModel;
use app\common\model\MemberIpModel;
use think\Model;
use think\model\relation\BelongsTo;
use think\model\relation\HasMany;
use think\model\relation\HasOne;
use think\model\concern\SoftDelete;

class MemberModel extends Model
{
    use SoftDelete;
    protected $deleteTime = 'delete_time';
    // 确定链接表名
    protected $name = 'home_user';

    /**
     * 根据搜索条件获取用户列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getUsersByWhere($where, $offset, $limit, $orderby = ['id' => 'DESC'])
    {
        $lists = $this->withTrashed()
            ->with([
                'leader1_info'    => function ($query) {
                    $query->field(['id', 'user_name']);
                }, 'leader2_info' => function ($query) {
                    $query->field(['id', 'user_name']);
                }, 'leader3_info' => function ($query) {
                    $query->field(['id', 'user_name']);
                }
            ])
            ->where($where)
            ->limit($offset, $limit)
            ->order($orderby)
            ->select();
        foreach ($lists as $k => $val) {
            $lists[$k] = $val;
        }
        return $lists;
    }
    /**
     * 根据搜索条件获取用户列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getAllUsersByWhere($where, $orderby = ['id' => 'DESC'])
    {
        $lists = $this->withTrashed()->where($where)->order($orderby)->select();
        return $lists;
    }

    /**
     * 根据搜索条件获取所有的用户数量
     * @param $where
     */
    public function getAllUsers($where)
    {
        return $this->alias('user')->where($where)->count();
    }

    /**
     * 插入管理员信息
     * @param $param
     */
    public function insertUser($param)
    {
        try{
            $param['user_money'] = 0;
            $param['tyj_money'] = 0;
            $param['frozen_money'] = 0;
            $param['total_money'] = 0;
            $param['invite_code'] = generateInviteCode();// 创建自己的ID
            MemberModel::create($param);
            return msg(1, url('member/index')->build(), '添加用户成功');
        }catch(PDOException $e){

            return msg(-2, '', $e->getMessage());
        }
    }

    /**
     * 编辑管理员信息
     * @param $param
     * @return array
     */
    public function editUser($param): array
    {
        try{
            $this->save($param, ['id' => $param['id']]);
            return msg(1, url('member/index')->build(), '编辑用户成功');
        }catch(PDOException $e){
            return msg(-2, '', $e->getMessage());
        }
    }

    /**
     * 根据管理员id获取角色信息
     * @param $id
     */
    public function getOneUser($id, $field = "*")
    {
        $info = $this->withTrashed()->where('id', $id)->field($field)->find();
        return !empty($info) && isset($info[$field]) ? $info[$field] : $info;
    }

    /**
     * 删除管理员
     * @param $id
     */
    public function delUser($id)
    {
        try{
            $this->where('id', $id)->delete();
            return msg(1, '', '删除用户成功');
        }catch( PDOException $e){
            return msg(-1, '', $e->getMessage());
        }
    }

    /**
     * 根据用户名获取管理员信息
     * @param $name
     */
    public function findUserByName($name)
    {
        return $this->where('nick_name', $name)->find();
    }

    /**
     * 更新管理员状态
     * @param array $param
     */
    public function updateStatus($param = [], $uid = 0)
    {
        try{
            $this->where('id', $uid)->update($param);
            return msg(1, '', 'ok');
        }catch (\Exception $e){

            return msg(-1, '', $e->getMessage());
        }
    }

    /**
     * 根据手机检测用户数据
     * @param $mobile
     */
    public function checkUser($mobile)
    {
        return $this->alias('u')
                ->field('u.id, u.nick_name, u.avatar, u.paypwd, u.status, u.last_login_time')
                ->where('u.mobile', $mobile)
                ->find();
    }

    public function parent(): HasOne
    {
        return $this->hasOne('MemberModel', 'id', 'leader1');
    }

    public function cp()
    {
        return $this->hasOne('MemberModel', 'id', 'cp_id');
    }

    public function userIp(): HasMany
    {
        return $this->hasMany(MemberIpModel::class, 'ip', 'last_login_ip');
    }

    public function financeProductOrder(): HasMany
    {
        return $this->hasMany(FinanceProductOrderModel::class, 'user_id', 'id');
    }

    public function leader1Info(): HasOne
    {
        return $this->hasOne(self::class, 'id', 'leader1');
    }
    public function leader2Info(): HasOne
    {
        return $this->hasOne(self::class, 'id', 'leader2');
    }
    public function leader3Info(): HasOne
    {
        return $this->hasOne(self::class, 'id', 'leader3');
    }
    public function recharge(): hasMany
    {
        return $this->hasMany(RechargeModel::class, 'user_id', 'id');
    }

    public function userBrokeragePool(): BelongsTo
    {
        return $this->belongsTo(HomeUserBrokeragePoolModel::class, 'id', 'user_id');
    }
}
