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

class ActivityLogModel extends Model
{
    // 确定链接表名
    protected $name = 'activity_log';

    /**
     * 查询所有数据
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getListByWhere($where, $offset, $limit)
    {
        $lists = $this->alias('a')
            ->where($where)
            ->limit($offset, $limit)
            ->field('a.*')
            ->order(['a.id' => 'desc'])
            ->select();
        return $lists;
    }

    /**
     * 根据搜索条件获取所有数量
     * @param $where
     */
    public function getCount($where)
    {
        return $this->where($where)->count();
    }

    /**
     * 根据文章的id 获取指定ID的信息
     * @param $id
     */
    public function getOne($id)
    {
        $info = $this->alias('a')
            ->field('a.*')
            ->order(['a.id' => 'desc'])
            ->where('a.id', $id)
            ->find();
        return $info;
    }

    /**
     * 删除文章
     * @param $id
     */
    public function del($id)
    {
        try{
            $param = self::get($id);
            $this->where('id', $id)->delete();
            if ($param) {
                insert_operation_log('删除', "{$param['id']}");
            }
            return msg(1, '', '删除记录成功');

        }catch(\Exception $e){
            return msg(-1, '', $e->getMessage());
        }
    }
}
