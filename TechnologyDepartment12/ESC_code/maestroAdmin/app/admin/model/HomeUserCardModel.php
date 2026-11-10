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

use think\Model;

class HomeUserCardModel extends Model
{

    // 确定链接表名
    protected $name = 'home_user_card';


    /**
     * 绑定银行卡
     * @param $data
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function bindCard($data)
    {
        // `user_id` int DEFAULT '0' COMMENT '用户id',
        //  `type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '类型  bank:银行卡，alipay:支付宝',
        //  `bank_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '银行卡id',
        //  `bank_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '名称',
        //  `true_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '此卡人名称',
        //  `bank_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '卡号',
        //  `bank_kh` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '开户行',
        //  `bank_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '银行地址',
        $map = [
            'bank_number' => $data['bank_number']
        ];
        $checkData = $this->where($map)->find();
        if (!empty($checkData)) {
            return [
                'code' => -1,
                'msg' => lang('根据平台的政策，禁止同一IP地址或同一人注册多个账户进行推广活动。')
            ];
        }

        $map = [
            'user_id' => $data['user_id'],
            'type' => $data['type']
        ];
        $checkData = $this->where($map)->find();
        if (!empty($checkData)) {
            return [
                'code' => -1,
                'msg' => lang('每种类型只能绑定一个账户')
            ];
        }

        $in_data = [
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'bank_id' => isset($data['bank_id']) ?? 0,
            'bank_name' => $data['bank_name'],
            'true_name' => $data['true_name'],
            'bank_number' => $data['bank_number'],
            'bank_kh' => isset($data['bank_kh']) ?? ''
        ];
        $ret = $this->save($in_data);
        if ($ret != false) {
            return [
                'code' => 0,
                'msg' => lang('添加成功')
            ];
        }
        return [
            'code' => -1,
            'msg' => lang('添加失败')
        ];
    }
}
