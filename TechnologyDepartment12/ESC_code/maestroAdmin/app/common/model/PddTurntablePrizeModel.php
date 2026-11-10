<?php

namespace app\common\model;

use think\Model;
use think\model\relation\BelongsTo;

class PddTurntablePrizeModel extends Model
{
    // 确定链接表名
    protected $name = 'pdd_turntable_prize';

    protected $type = [
        'is_big_prize' => 'boolean',
        'value' => 'float',
        'probability' => 'float',
    ];

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
    public static function getPrize($turntable_id, $class = 1, $level_id = -1)
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
    public static function getPrizeList($turntable_id, $class = 1, $level_id = -1)
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

    /**
     * 获取普通奖品概率列表
     * @param $turntable_id
     * @return array
     */
    public static function getNormalPrizeProbs($turntable_id)
    {
        $list = self::where([
            'turntable_id' => $turntable_id,
            'is_big_prize' => 0,
            'status' => 1
        ])->select();

        $total_prob = array_sum(array_column($list->toArray(), 'probability'));
        $probs = [];

        foreach ($list as $prize) {
            if ($total_prob > 0) {
                // 重新计算概率，确保总和为100%
                $probs[$prize['id']] = ($prize['probability'] / $total_prob) * 100;
            } else {
                // 如果所有概率都是0，则平均分配
                $probs[$prize['id']] = 100 / count($list);
            }
        }

        return $probs;
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(PddTurntablePrizeConfigModel::class, 'id', 'prize_id');
    }
}
