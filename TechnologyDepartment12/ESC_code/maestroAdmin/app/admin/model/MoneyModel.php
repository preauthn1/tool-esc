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
class MoneyModel extends Model
{
    // 确定链接表名
    protected $name = 'money_log';

    /**
     * 根据搜索条件获取用户列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getListsByWhere($where, $offset, $limit)
    {
        return $this->alias('int')->field( 'int.*')
            // ->join('level rol', 'user.level = rol.id', 'left')
            ->where($where)->limit($offset, $limit)->order(['id' => 'desc'])->select();
    }

    /**
     * 根据搜索条件获取所有的用户数量
     * @param $where
     */
    public function getAllLists($where)
    {
        return $this->where($where)->count();
    }

    /**
     * 插入管理员信息
     * @param $param
     */
    public function insertInt($param)
    {

        $full_table_name = "qd_money_log";
        try{
            $ret = \think\Db::table($full_table_name)->insertGetId($param);
            if(false === $ret){
                // 验证失败 输出错误信息
                return msg(-1, '', '调整失败');
            }else{
                return msg(1, url('member/money', ['uid'=>$param['user_id']])->build(), '调整成功');
            }
        }catch(PDOException $e){

            return msg(-2, '', $e->getMessage());
        }
    }

}
