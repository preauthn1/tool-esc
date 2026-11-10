<?php

namespace app\admin\controller;

use app\common\model\RecommendModel;
use think\facade\View;

/**
 * Class Recommend
 * 转盘
 * @package app\admin\controller
 */
class Recommend extends Base
{
    public function index()
    {
        $recommend_model = new RecommendModel();
        if (request()->isPost()) {
            $param = $this->request->param();
            //`day_rec` int DEFAULT '0' COMMENT '当日直推',
            //  `day_reward` decimal(10,2) DEFAULT '0.00' COMMENT '当日直推奖励',
            //  `week_rec` int DEFAULT '0' COMMENT '当周直推数量',
            //  `week_reward` decimal(10,2) DEFAULT '0.00' COMMENT '当周直推奖励',
            //  `month_rec` int DEFAULT '0' COMMENT '当月直推数量',
            //  `month_reward` decimal(10,0) DEFAULT '0' COMMENT '当月直推奖励',
            //  `vip1` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'VIP1奖励规则',
            //  `vip2` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'VIP2奖励规则',
            //  `vip3` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'VIP3奖励规则',
            //  `vip4` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'VIP4奖励规则',
            //  `vip5` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'VIP5奖励规则',
            //  `vip6` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'VIP6奖励规则',
            //  `vip7` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'VIP7奖励规则',
            //  `vip8` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'VIP8奖励规则',
            //  `status` tinyint(1) DEFAULT '1' COMMENT '1开启  2关闭',
            $up_data = [
                'day_rec'      => $param['day_rec'],
                'day_reward'   => $param['day_reward'],
                'week_rec'     => $param['week_rec'],
                'week_reward'  => $param['week_reward'],
                'month_rec'    => $param['month_rec'],
                'month_reward' => $param['month_reward'],
//                'rec_tip' => $param['rec_tip'],
                'vip1'         => $param['vip1']??'',
                'vip2'         => $param['vip2']??'',
                'vip3'         => $param['vip3']??'',
                'vip4'         => $param['vip4']??'',
                'vip5'         => $param['vip5']??'',
                'vip6'         => $param['vip6']??'',
                'vip7'         => $param['vip7']??'',
                'vip8'         => $param['vip8']??'',
                'level1'       => $param['level1']??'',
                'level2'       => $param['level2']??'',
                'level3'       => $param['level3']??'',
                'level4'       => $param['level4']??'',
//                'vip_tip' => $param['vip_tip'],
//                'prompt_tip' => $param['prompt_tip'],
                'status'       => $param['status'],
                'update_time'  => time()
            ];
            $recommend_model->where('id', $param['id'])->update($up_data);
//            $this->success(lang('提交成功'),'','close');
        }
        $recommend = $recommend_model->getRecommend();
        View::assign('recommend', $recommend);
        return View::fetch();
    }
}
