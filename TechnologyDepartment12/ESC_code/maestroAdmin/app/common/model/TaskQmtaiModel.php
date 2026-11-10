<?php

namespace app\common\model;

use think\Model;

class TaskQmtaiModel extends Model
{
    //0取消 1进行中 2已完成
    const STATUS_CANCEL = 0;//取消
    const STATUS_ING = 1;//进行中
    const STATUS_END = 2;//已完成

    //
    static $STATUS_NAMES = [
        self::STATUS_CANCEL => "取消",
        self::STATUS_ING => "进行中",
        self::STATUS_END => "已完成",
    ];
    // 确定链接表名
    protected $name = 'task_qmtai';

    protected $autoWriteTimestamp = true;

    /**
     * 增加单条任务
     * @param $user_id //会员id
     * @param $product
     * id 产品id
     * name 产品名称
     * img 产品图片
     * price 量化金额
     * @param $brokerage
     * level_id 会员等级id
     * money 会员等级佣金(量化佣金)
     * @param int $is_xuni 是否是虚拟 0不是 1是
     * @return array
     */
    public function addTask($user_id, $product, $brokerage, $is_xuni = 0)
    {
        $data = UserModel::endTaskCondition($user_id);
        if ($data['code'] != 1) {
            return $data;
        }
        UserModel::decTaskCount($user_id);
        // `is_xuni` tinyint(1) NOT NULL DEFAULT '0' COMMENT '虚拟用户0不是 1是',
        //  `user_id` int NOT NULL DEFAULT '0' COMMENT '用户ID',
        //  `level_id` int NOT NULL DEFAULT '0' COMMENT '等级ID',
        //  `product_id` int NOT NULL DEFAULT '0' COMMENT '商品ID',
        //  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '产品名称',
        //  `product_img` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
        //  `product_price` decimal(20,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '量化金额',
        //  `income_profit` decimal(20,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '量化收益',
        //  `time` int unsigned NOT NULL DEFAULT '0' COMMENT '量化时间',
        //  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '拼单状态 0 取消 1 进行中 2 已完成 3 已失效',
        //  `day` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '日期 年-月-日',
        //  `finish_time` int DEFAULT '0' COMMENT '结束时间',
        $time = rand(10, 60);
        $in_data = [
            'is_xuni' => $is_xuni,
            'user_id' => $user_id,
            'level_id' => $brokerage['level_id'],
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'product_img' => $product['img'],
            'product_price' => $product['price'],//量化金额
            'money' => $brokerage['money'],//量化佣金
            'day' => date("Y-m-d"),//时间
            'status' => 1,//拼单状态 0取消 1 进行中 2 已完成 3 已失效
            'time' => $time,
            'finish_time' => time() + ($time),//量化结束时间
            'create_time' => time(),
            'update_time' => time(),
        ];
        $id = $this->insertGetId($in_data);
        if (empty($id)) {
            return [
                'code' => 10,
                'msg' => '量化失败'
            ];
        }
        return [
            'code' => 1,
            'data' => $id,
            'msg' => '量化成功,稍后后查看收益'
        ];
    }

    //完成任务
    public function finishTask($id = '')
    {
        if (empty($id)) {
            $map = [
                'status' => self::STATUS_ING,
            ];
            $list = $this->where($map)->where('finish_time', '<=', time())->order('id ASC')->field('id')->limit(10)->select();
            if (!empty($list)) {
                foreach ($list as $k => $v) {
                    $this->endTask($v['id']);
                }
            }
        }
        $this->endTask($id);
        return true;
    }

    //完成任务结算
    public function endTask($id)
    {
        $map = [
            'status' => self::STATUS_ING,
            'id' => $id
        ];
        $temp = $this->where($map)->find();
        if (empty($temp)) {
            return false;
        }
        $user_id = $temp['user_id'];
        $in_update = [
            'status' => self::STATUS_END,
            'update_time' => time(),
        ];
        $this->startTrans();
        $ret = $this->where('id', $id)->update($in_update);
        if ($ret == false) {
            $this->rollback();
            return [
                'code' => 14,
                'msg' => '任务完成失败',
            ];
        }
        $money = $temp['money'];
        if (floatval($money) <= 0) {
            $this->commit();
            return [
                'code' => 1,
                'msg' => '任务完成成功',
            ];
        }
        $title = "任务返佣";
        $remarks = "完成任务返佣:{$money} ";
        $money_log = new MoneyLogModel();
        $change = "add";
        $data = $money_log->changeMoney($user_id, $money, MoneyLogModel::TYPE_BROKERAGE, $change, $title, $remarks, 3);
        if ($data['code'] == 1) {
            $this->commit();
            $money_log->allotBrokerage($user_id, $money, 3, false);
            UserModel::decTaskCount($user_id);
            return [
                'code' => 1,
                'msg' => '任务完成成功',
            ];
        }
        $this->rollback();
        return [
            'code' => 15,
            'msg' => '任务完成失败',
        ];
    }
}
