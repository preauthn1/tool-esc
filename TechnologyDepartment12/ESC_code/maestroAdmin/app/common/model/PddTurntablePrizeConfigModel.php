<?php

namespace app\common\model;

use think\Model;

class PddTurntablePrizeConfigModel extends Model
{
    protected $name = 'pdd_turntable_prize_config';

    protected $type = [
        // 初始范围
        'initial_min'         => 'float',
        'initial_max'         => 'float',

        // 第一阶段
        'stage_1_min'         => 'float',
        'stage_1_max'         => 'float',
        'stage_1_high'        => 'float',
        'stage_1_middle'      => 'float',
        'stage_1_low'         => 'float',
        'stage_1_high_min'    => 'float',
        'stage_1_high_max'    => 'float',
        'stage_1_middle_min'  => 'float',
        'stage_1_middle_max'  => 'float',
        'stage_1_low_min'     => 'float',
        'stage_1_low_max'     => 'float',
        'stage_1_high_prob'   => 'float',
        'stage_1_middle_prob' => 'float',
        'stage_1_low_prob'    => 'float',

        // 第二阶段
        'stage_2_min'         => 'float',
        'stage_2_max'         => 'float',
        'stage_2_high'        => 'float',
        'stage_2_middle'      => 'float',
        'stage_2_low'         => 'float',
        'stage_2_high_min'    => 'float',
        'stage_2_high_max'    => 'float',
        'stage_2_middle_min'  => 'float',
        'stage_2_middle_max'  => 'float',
        'stage_2_low_min'     => 'float',
        'stage_2_low_max'     => 'float',
        'stage_2_high_prob'   => 'float',
        'stage_2_middle_prob' => 'float',
        'stage_2_low_prob'    => 'float',

        // 第三阶段
        'stage_3_min'         => 'float',
        'stage_3_max'         => 'float',
        'stage_3_high'        => 'float',
        'stage_3_middle'      => 'float',
        'stage_3_low'         => 'float',
        'stage_3_high_min'    => 'float',
        'stage_3_high_max'    => 'float',
        'stage_3_middle_min'  => 'float',
        'stage_3_middle_max'  => 'float',
        'stage_3_low_min'     => 'float',
        'stage_3_low_max'     => 'float',
        'stage_3_high_prob'   => 'float',
        'stage_3_middle_prob' => 'float',
        'stage_3_low_prob'    => 'float'
    ];
}
