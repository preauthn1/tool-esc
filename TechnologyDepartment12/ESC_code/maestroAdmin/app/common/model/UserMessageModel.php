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

namespace app\common\model;

use think\exception\PDOException;
use think\Model;

class UserMessageModel extends Model
{

    // 确定链接表名
    protected $name = 'home_user_message';
    protected $autoWriteTimestamp = true;

    const TYPE_CUSTOM = 1;
    const TYPE_RECHARGE = 2;
    const TYPE_WITHDRAW = 3;
    const TYPE_WITHDRAW_FAIL = 4;

    public static $typeMap = [
        self::TYPE_CUSTOM        => '自定义',
        self::TYPE_RECHARGE      => '充值到账',
        self::TYPE_WITHDRAW      => '提现到账',
        self::TYPE_WITHDRAW_FAIL => '提现失败',
    ];


    /**
     * 根据搜索条件获取商家列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getUserMessageWhere($where, $offset, $limit)
    {
        return $this->where($where)->limit($offset, $limit)->order('id DESC')->select();
    }

    /**
     * 根据搜索条件获取所有的商家数量
     * @param $where
     */
    public function getAllUserMessage($where)
    {
        return $this->alias('user')->where($where)->count();
    }


    /**
     * 插入站内信信息
     * @param $param
     * @return array
     */
    public function insertUserMessage($param): array
    {
        try {
            $result = $this->save($param);
            if (false === $result) {
                // 验证失败 输出错误信息
                return msg(-1, '', $this->getError());
            } else {
//                insert_operation_log('站内信:增加', $param);
                return msg(1, url('level/index'), '添加站内信成功');
            }
        } catch (PDOException $e) {

            return msg(-2, '', $e->getMessage());
        }
    }



    // 获取未读消息数量
    public static function getUnreadCount($user_id)
    {
        return \app\admin\model\UserMessageModel::where('user_id', $user_id)
            ->where('is_read', 0)
            ->count();
    }

    // 获取未读弹窗消息数量
    public static function getPopUnreadCount($user_id)
    {
        return UserMessageModel::where('user_id', $user_id)
            ->where('is_pop', 1)
            ->where('is_read', 0)
            ->count();
    }
}
