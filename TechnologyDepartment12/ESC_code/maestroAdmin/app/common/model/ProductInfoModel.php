<?php

namespace app\common\model;

use think\Model;

class ProductInfoModel extends Model
{
    // 确定链接表名
    protected $name = 'productinfo';


    /**
     * 随机获取一条产品信息
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getProductInfo()
    {
        // `ptitle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT '标题',
        //  `procode` varchar(18) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL COMMENT ' 产品代码',
        //  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT '图片',
        //  `change_rate` decimal(10,4) DEFAULT '0.0000' COMMENT '涨跌比例',
        $list = self::field('id,ptitle,procode,logo,change_rate')->select();
        $len = count($list);
        $temp = $list[rand(0, $len - 1)];
        return $temp;
    }
}
