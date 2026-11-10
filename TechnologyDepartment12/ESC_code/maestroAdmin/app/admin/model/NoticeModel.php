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

use app\common\model\NoticeInfoModel;
use think\Model;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

class NoticeModel extends Model
{
    protected $autoWriteTimestamp = true;
    protected $update_time = false;
    // 确定链接表名
    protected $name = 'notice';

    /**
     * 根据搜索条件获取商家列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getNoticeByWhere($where, $offset, $limit)
    {
        return $this->alias('user')->field( 'user.*')
            ->where($where)->limit($offset, $limit)->order(['id' => 'desc'])->select();
    }

    /**
     * 根据搜索条件获取所有的商家数量
     * @param $where
     */
    public function getAllNotice($where)
    {
        return $this->where($where)->count();
    }

    /**
     * 插入管理员信息
     * @param $param
     */
    public function addNotice($param)
    {
        try{

            $result =  $this->validate('NoticeValidate')->save($param);
            if(false === $result){
                // 验证失败 输出错误信息
                return msg(-1, '', $this->getError());
            }else{

                return msg(1, url('notice/index')->build(), '添加公告成功');
            }
        }catch(PDOException $e){

            return msg(-2, '', $e->getMessage());
        }
    }

    /**
     * 编辑管理员信息
     * @param $param
     */
    public function editNotice($param)
    {
        try{

            $result =  $this->validate('NoticeValidate')->save($param, ['id' => $param['id']]);

            if(false === $result){
                // 验证失败 输出错误信息
                return msg(-1, '', $this->getError());
            }else{

                return msg(1, url('notice/index')->build(), '编辑公告成功');
            }
        }catch(PDOException $e){
            return msg(-2, '', $e->getMessage());
        }
    }

    /**
     * 根据管理员id获取角色信息
     * @param $id
     */
    public function getOneNotice($id)
    {
        return $this->where('id', $id)->find();
    }

    /**
     * 删除管理员
     * @param $id
     */
    public function delNotice($id)
    {
        try{

            $this->where('id', $id)->delete();
            return msg(1, '', '删除公告成功');

        }catch( PDOException $e){
            return msg(-1, '', $e->getMessage());
        }
    }

    /**
     * 根据商家名获取管理员信息
     * @param $name
     */
    public function findNoticeByName($name)
    {
        return $this->where('name', $name)->find();
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

    // 获取所有分类
    public function getNotice()
    {
        return $this->select();
    }

    public function infos(): HasMany
    {
        return $this->hasMany(NoticeInfoModel::class, 'notice_id', 'id');
    }

    public function info(): HasOne
    {
        return $this->hasOne(NoticeInfoModel::class, 'notice_id', 'id');
    }
}
