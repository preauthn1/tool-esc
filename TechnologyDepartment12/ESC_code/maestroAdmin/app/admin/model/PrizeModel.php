<?php

namespace app\admin\model;

use think\Model;

class PrizeModel extends Model
{
    // 确定链接表名
    protected $name = 'turntable_prize';

    /**
     * 获取奖品列表
     * @param $class
     * @param int $level_id
     * @param int $turntable_id
     * @return bool|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getPrizeList($class, $level_id = -1, $turntable_id = 1)
    {
        if (!is_numeric($level_id)) {
            $level_id = -1;
        }
        $map = [
            'class' => $class,
            'level_id' => $level_id,
            'turntable_id' => $turntable_id,
        ];
//        `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '奖品ID',
//  `level_id` int(11) DEFAULT '-1' COMMENT '-1全局  0-10对应VIP等级',
//  `class` tinyint(1) DEFAULT '1' COMMENT '1注册奖品 2邀请奖品',
//  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '奖品名称',
//  `num` int(11) DEFAULT '-1' COMMENT '奖品数量-1=无限',
//  `type` int(11) DEFAULT '0' COMMENT '奖品类型0=返现红包,2=实物，3=谢谢参与',
//  `value` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '奖品类型对应数值',
//  `status` int(11) DEFAULT '1' COMMENT '奖品状态1=正常，2=禁用',
//  `img` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '奖品图片',
//  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
//  `probability` int(11) DEFAULT '1' COMMENT '中奖几率',
        $temp = $this->where($map)->order(['sort' => 'ASC'])->select();
        $list = [];
        if (empty($temp)) {
            for ($i = 1; $i < 9; $i++) {
                $list[] = [
                    'level_id' => $level_id,
                    'turntable_id' => $turntable_id,
                    'class' => $class,
                    'name' => '奖项' . $i,
                    'num' => -1,
                    'type' => 0,
                    'value' => '',
                    'status' => 1,
                    'img' => '',
                    'sort' => $i,
                    'probability' => 0,
                ];
            }
            $this->saveAll($list);
        }
        $temp = $this->where($map)->order(['sort' => 'ASC'])->select();
        return $temp;
    }
}
