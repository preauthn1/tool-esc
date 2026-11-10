<?php

namespace app\common\repository;

use app\admin\model\RechargeModel;
use Carbon\Carbon;
use think\Collection;


class RechargeRepository extends Repository
{
    protected $model;

    public function __construct()
    {

        //绑定模型
        $this->model = new RechargeModel();
    }

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

    // 来源 1-前端 2-后台
    const FROM_FRONT = 1;
    const FROM_ADMIN = 2;

    public static array $fromMap = [
        self::FROM_FRONT => '前端',
        self::FROM_ADMIN => '后台',
    ];

    /**
     * @param $user_ids
     * @param  array  $field
     * @param  bool  $include_xuni
     * @return Collection|RechargeModel[]
     */
    public function getTodayRecharge($user_ids, array $field = ['*'], bool $include_xuni = false)
    {
        $where_time = [strtotime(date('Y-m-d 00:00:00')), strtotime(date('Y-m-d 23:59:59'))];

        return $this->getRecharges($user_ids, $where_time, $field, $include_xuni);
    }

    public function getRecharges($user_ids, $where_time, array $field = ['*'], bool $include_xuni = false)
    {
        $where = [
            ['user_id', 'in', $user_ids],
            ['create_time', 'between', $where_time],
            ['status', '=', self::STATUS_SUCCESS],
        ];
        if (!$include_xuni) {
            $where[] = ['is_xuni', '=', 0];
        }
        return $this->model->where($where)->field($field)
            ->select();
    }

    public function getTeamRechargeSuccessAmount($user_ids, $where = [['from'=>self::FROM_FRONT]], $include_xuni = false): float
    {
        $default_where = [
            ['user_id', 'in', $user_ids],
            'status' => self::STATUS_SUCCESS,
        ];
        $new_where = array_merge($default_where, $where);

        return $this->getRechargeAmount($new_where, $include_xuni);
    }


    public function getRechargeAmount($where = [], $include_xuni = false): float
    {
        $default_where = [];
        $new_where = array_merge($default_where, $where);
        if (!$include_xuni) {
            $new_where[] = ['is_xuni', '=', 0];
        }
        return $this->model->where($new_where)->sum('money');
    }

    public function getRechargeCount($where = [], $include_xuni = false): int
    {
        $default_where = [];
        $new_where = array_merge($default_where, $where);
        if (!$include_xuni) {
            $new_where[] = ['is_xuni', '=', 0];
        }
        return $this->model->where($new_where)->distinct('user_id')->count();
    }

    public function getTeamTodayRechargeAmount($user_ids, $where = [["from"=>self::FROM_FRONT]], $include_xuni = false)
    {
        $now = Carbon::now();
        $default_where = [
            ['user_id', 'in', $user_ids],
            'status' => self::STATUS_SUCCESS,
            ['success_time', 'between', [$now->startOfDay()->timestamp, $now->endOfDay()->timestamp]]
        ];
        $new_where = array_merge($default_where, $where);
        return $this->getRechargeAmount($new_where, $include_xuni);
    }

    public function getTeamTodayRechargeCount($user_ids, $where = [], $include_xuni = false)
    {
        $now = Carbon::now();
        $default_where = [
            ['user_id', 'in', $user_ids],
            'status' => self::STATUS_SUCCESS,
            'from'   => self::FROM_FRONT,
            ['success_time', 'between', [$now->startOfDay()->timestamp, $now->endOfDay()->timestamp]]
        ];
        $new_where = array_merge($default_where, $where);
        return $this->getRechargeCount($new_where, $include_xuni);
    }


    public function getTeamTodayRepeatRechargeRechargeCount(mixed $user_ids, $where = [], $include_xuni = false): int
    {
        $now = Carbon::now();
        $default_where = [
            ['user_id', 'in', $user_ids],
            'status'   => self::STATUS_SUCCESS,
            'from'     => self::FROM_FRONT,
            'is_first' => 0,
            ['success_time', 'between', [$now->startOfDay()->timestamp, $now->endOfDay()->timestamp]]
        ];
        $new_where = array_merge($default_where, $where);
        return $this->getRechargeCount($new_where, $include_xuni);

    }

    public function getTeamTodayFirstRechargeMoney($user_ids, $where = [["from"=>self::FROM_ADMIN]], $include_xuni = false)
    {
        $now = Carbon::now();
        $default_where = [
            ['user_id', 'in', $user_ids],
            'status'   => self::STATUS_SUCCESS,
            'is_first' => 1,
            ['success_time', 'between', [$now->startOfDay()->timestamp, $now->endOfDay()->timestamp]]
        ];
        $new_where = array_merge($default_where, $where);
        if (!$include_xuni) {
            $new_where[] = ['is_xuni', '=', 0];
        }
        return $this->model->where($new_where)->sum('money');
    }

    public function getTeamTodayFirstRechargeCount($user_ids, $where = [], $include_xuni = false): int
    {
        $now = Carbon::now();
        $default_where = [
            ['user_id', 'in', $user_ids],
            'status'   => self::STATUS_SUCCESS,
            'from'     => self::FROM_FRONT,
            'is_first' => 1,
            ['success_time', 'between', [$now->startOfDay()->timestamp, $now->endOfDay()->timestamp]]
        ];
        $new_where = array_merge($default_where, $where);
        if (!$include_xuni) {
            $new_where[] = ['is_xuni', '=', 0];
        }
        return $this->model->where($new_where)->distinct('user_id')->count();
    }

    // 当日首充人数 这边指的是当日注册当日充值的账号
    public function getTeamTodayRegisterAndFirstRechargeCount($user_ids, $where = [], $include_xuni = false)
    {
        $now = Carbon::now();
        $today_time_arr = [$now->startOfDay()->timestamp, $now->endOfDay()->timestamp];
        $default_where = [
            ['user_id', 'in', $user_ids],
            'recharge_model.status' => self::STATUS_SUCCESS,
            'from'                  => self::FROM_FRONT,
            'is_first'              => 1,
            ['success_time', 'between', $today_time_arr]
        ];
        $new_where = array_merge($default_where, $where);
        if (!$include_xuni) {
            $new_where[] = ['recharge_model.is_xuni', '=', 0];
        }
        return $this->model
            ->hasWhere('user', [['create_time', 'between', $today_time_arr]])
            ->where($new_where)
            ->distinct('user_id')
            ->count();
    }
}
