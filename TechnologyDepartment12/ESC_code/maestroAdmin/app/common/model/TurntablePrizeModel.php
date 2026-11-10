<?php

namespace app\common\model;

use think\Model;

class TurntablePrizeModel extends Model
{
    // 确定链接表名
    protected $name = 'turntable_prize';

    /**
     * 获取奖品
     * @param $turntable_id
     * @param int $class 1注册 2邀请
     * @param int $level_id 等级
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getPrize($turntable_id, $class = 1,$level_id = -1)
    {
        $map = [
            'turntable_id' => $turntable_id,
            'level_id' => $level_id,
            'class' => $class,
            'status' => 1,
        ];
        $temp_list = self::where($map)->order('sort DESC')->select();
        $arr_prize = [];
        foreach ($temp_list as $key => $value) {
            if ($value['probability'] > 0) {
                for ($i = 0; $i < $value['probability']; $i++) {
                    $arr_prize[] = $value;
                }
            }
        }
        for ($i = 0; $i < 100 - count($arr_prize); $i++) {
            $arr_prize[] = [];
        }
        shuffle($arr_prize);
        return $arr_prize[rand(0, count($arr_prize) - 1)];
    }

    /**
     * 获取奖品列表
     * @param $turntable_id
     * @param int $class
     * @param int $level_id
     * @return array|bool|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getPrizeList($turntable_id, $class = 1,$level_id = -1)
    {
        $map = [
            'turntable_id' => $turntable_id,
            'level_id' => $level_id,
            'class' => $class,
            'status' => 1,
        ];
        $temp_list = self::where($map)->order('sort DESC')->select();
        if (empty($temp_list)) {
            return [];
        }
        return $temp_list;
    }
}
