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

use app\common\model\PaymentPlatformModel;
use think\Model;
use think\model\relation\BelongsTo;
use think\model\relation\HasOne;

class RechargeModel extends Model
{

    protected $autoWriteTimestamp = true;
    // 确定链接表名
    protected $name = 'recharge';
    protected $insert = ['order_id'];

    const COLLECTION_SUCCESS = 1;
    const COLLECTION_FAIL = -1;

    // 充值类型
    const TYPE_BANK = 1;
    const TYPE_CONTRACT = 2;
    const TYPE_THIRD = 3;

    public static array $typesMap = [
        self::TYPE_BANK     => '银行卡充值',
        self::TYPE_CONTRACT => '合约充值',
        self::TYPE_THIRD    => '第三方充值',
    ];

    // 状态 0-待审核 1-待支付 2-通过 3-失效
    const STATUS_PENDING = 0; // 待审核
    const STATUS_WAIT = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_FAIL = 3;

    public static array $statusMap = [
        self::STATUS_PENDING => '待审核',
        self::STATUS_WAIT    => '待支付',
        self::STATUS_SUCCESS => '成功',
        self::STATUS_FAIL    => '失败',
    ];


    protected function setOrderIdAttr()
    {
        return genOrderSN(random(4, 'number'), 'RE');
    }

    /**
     * 根据搜索条件获取充值列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getLlistsByWhere($where, $offset, $limit, $orderby)
    {
        $lists = $this->alias('r')
            ->field('r.*, hu.id as uid,hu.leader1, hu.user_name, hu.true_name, hu.is_xuni, hu.mobile, hu.last_login_ip,hu.tree_id, cd.amount, cd.coin, cd.from_address as cd_from_address, cd.to_address as cd_to_address, cd.status as cd_status, cd.amount as cd_amount, pp.name as channel_name')
            ->join('home_user hu', 'r.user_id = hu.id', 'left')
            ->join('collection c', 'c.batch_no = r.batch_no', 'left')
            ->join('collection_detail cd', 'cd.collection_id = c.id', 'left')
            ->join('payment_platform pp', 'pp.id = r.pay_id', 'left')
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
     * 根据搜索条件获取充值列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getAllByWhere($where, $orderby)
    {
        $lists = $this->alias('r')
            ->field('r.*,hu.leader1, hu.id as uid, hu.user_name, hu.true_name, hu.is_xuni, hu.mobile, hu.last_login_ip,hu.tree_id, cd.amount, cd.coin, cd.from_address as cd_from_address, cd.to_address as cd_to_address, cd.status as cd_status, cd.amount as cd_amount, pp.name as channel_name')
            ->join('home_user hu', 'r.user_id = hu.id', 'left')
            ->join('collection c', 'c.batch_no = r.batch_no', 'left')
            ->join('collection_detail cd', 'cd.collection_id = c.id', 'left')
            ->join('payment_platform pp', 'pp.id = r.pay_id', 'left')
            ->where($where)
            ->order($orderby)
            ->select();
        if ($lists) {
            return $lists->toArray();
        }
        return [];
    }

    /**
     * 根据搜索条件获取所有的充值数量
     * @param $where
     */
    public function getAlllists($where)
    {
        return $this->alias('r')
            ->join('home_user hu', 'r.user_id = hu.id', 'left')
            ->where($where)
            ->count();
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
        try {

            $this->where('id', $id)->delete();
            return msg(1, '', '删除充值记录成功');
        } catch (PDOException $e) {
            return msg(-1, '', $e->getMessage());
        }
    }

    public static function getRecharge($userid)
    {
        return (new self())->where('user_id', $userid)->where('status', 2)->sum('money');
    }

    public static function getTodayRecharge($userid)
    {
        return (new self())->where('user_id', $userid)->whereTime('create_time', 'today')->where('status',
            2)->sum('money');
    }

    public function channel(): HasOne
    {
        return $this->hasOne(PaymentPlatformModel::class, 'id', 'pay_id');
    }


    public function collection(): HasOne
    {
        return $this->hasOne(CollectionModel::class, 'batch_no', 'batch_no');
    }

    public function collectionDetail(): HasOne
    {
        return $this->hasOne(CollectionDetailModel::class, 'recharge_no', 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'user_id', 'id');
    }
}
