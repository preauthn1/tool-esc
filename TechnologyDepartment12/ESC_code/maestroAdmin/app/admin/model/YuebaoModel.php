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

class YuebaoModel extends Model
{
    // 确定链接表名
    protected $name = 'yuebao';

    /**
     * 根据搜索条件获取商家列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getLlistsByWhere($where, $offset, $limit)
    {
        $lists = $this->alias('w')
                ->field('w.*, hu.id as uid, hu.nick_name, hu.mobile, hu.is_xuni')
                ->join('home_user hu', 'w.user_id = hu.id', 'right')
                ->where($where)
                ->limit($offset, $limit)
                ->order(['id' => 'desc'])
                ->select();
        return $lists;
    }

    /**
     * 根据搜索条件获取所有的商家数量
     * @param $where
     */
    public function getAlllists($where)
    {
        $lists = $this->alias('w')
                ->join('home_user hu', 'w.user_id = hu.id', 'right')
                ->where($where)
                ->count();
        return $lists;
    }


    /**
     * 根据管理员id获取角色信息
     * @param $id
     */
    public function getOneInfo($id)
    {
        return $this->where('id', $id)->find();
    }

    /**
     * 删除管理员
     * @param $id
     */
    public function delInfo($id)
    {
        try{

            $this->where('id', $id)->delete();
            return msg(1, '', '删除余额宝记录成功');

        }catch( PDOException $e){
            return msg(-1, '', $e->getMessage());
        }
    }

}
