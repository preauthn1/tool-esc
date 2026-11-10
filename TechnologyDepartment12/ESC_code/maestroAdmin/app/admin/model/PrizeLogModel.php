<?php

namespace app\admin\model;

use think\Model;

class PrizeLogModel extends Model {
    // 确定链接表名
    protected $name = 'turntable_prize_log';

    public function getPrizeLogByWhere($where, $offset, $limit) {
        return $this->where($where)->alias('p')
            ->field('p.*, hu.nick_name, hu.mobile, hu.is_xuni')
            ->join('home_user hu', 'p.user_id = hu.id')
            ->limit($offset, $limit)->order(['p.id' => 'DESC'])->select();
    }

    public function getAllPrizeLog($where) {
        return $this->where($where)->alias('p')
            ->join('home_user hu', 'p.user_id = hu.id')->count();
    }

    public function whereSql($where){
        return $this->where($where)->alias('p')
            ->join('home_user hu', 'p.user_id = hu.id');
    }
}
