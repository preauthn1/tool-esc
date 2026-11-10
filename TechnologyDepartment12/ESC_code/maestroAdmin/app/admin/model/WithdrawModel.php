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

class WithdrawModel extends Model
{
    // 确定链接表名
    protected $name = 'withdrawals';

    /**
     * 根据搜索条件获取商家列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getLlistsByWhere($where, $offset, $limit, $orderby)
    {
        $lists = $this->alias('w')->with(['user'])
                ->field('w.*, hu.id as uid, hu.user_name, hu.is_xuni, hu.mobile, hu.last_login_ip, pp.name as channel_name')
                ->join('home_user hu', 'w.user_id = hu.id', 'left')
                ->join('payment_platform pp', 'pp.id = w.channel', 'left')
                ->where($where)
                ->limit($offset, $limit)
                ->order($orderby)
                ->select();
        if ($lists) {
            return $lists->toArray();
        }
        return [];
    }
    /**
     * 根据搜索条件获取商家列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getAllByWhere($where, $orderby)
    {
        $lists = $this->alias('w')->with(['user'])
                ->field('w.*, hu.id as uid, hu.user_name, hu.is_xuni, hu.true_name, hu.mobile, hu.last_login_ip, pp.name as channel_name')
                ->join('home_user hu', 'w.user_id = hu.id', 'left')
                ->join('payment_platform pp', 'pp.id = w.channel', 'left')
                ->where($where)
                ->order($orderby)
                ->select();
        if ($lists) {
            return $lists->toArray();
        }
        return [];
    }

    /**
     * 根据搜索条件获取所有的商家数量
     * @param $where
     */
    public function getAlllists($where)
    {
        return $this->alias('w')
                ->join('home_user hu', 'w.user_id = hu.id', 'left')
                ->where($where)
                ->count();
    }


    /**
     * 根据管理员id获取角色信息
     * @param $id
     */
    public function getOneWithdraw($id)
    {
        return $this->where('id', $id)->find();
    }

    /**
     * 删除管理员
     * @param $id
     */
    public function delWithdraw($id)
    {
        try{

            $this->where('id', $id)->delete();
            return msg(1, '', '删除提现申请成功');

        }catch( PDOException $e){
            return msg(-1, '', $e->getMessage());
        }
    }

    public static function getWithdraw($userid) {
        return (new self())->where('user_id', $userid)->where('status', 4)->sum('original_money');
    }

    public static function getTodayWithdraw($userid) {
        return (new self())->where('user_id', $userid)->whereTime('create_time', 'today')->where('status', 4)->sum('original_money');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'user_id', 'id');
    }
}
