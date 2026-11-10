<?php

namespace app\api\controller;

use app\admin\model\LevelModel;
use app\admin\model\RechargeModel;
use app\common\model\ConfigModel;
use app\common\model\CustomerServiceModel;
use app\common\model\MemberLevelModel;
use app\common\model\ProductInfoModel;
use app\common\model\TaskModel;
use app\common\model\TaskQmtaiModel;
use app\common\model\UserModel;

//量化任务
class Qmtai extends Base
{
//    public function __construct(Request $request = null)
//    {
//        $this->userid = 1;
//        parent::__construct($request);
//    }

    // 首页
    public function index()
    {
        $userid = $this->userid;
        // 获取用户最新的vip信息
        $member_level = MemberLevelModel::where('user_id', $userid)
            ->where('level_end_time', '>', time())
            ->find();
        $levels = LevelModel::where('is_show', 1)->order('sort')->select();
        foreach ($levels as &$value) {
            if (!empty(floatval($value['price']))) {
                $value['ratio_max'] = round($value['income'] / $value['price'] * 100, 2);
                $value['ratio_min'] = round($value['income'] / $value['price'] * 100 / rand(1, 5), 2);
            } else {
                $value['ratio_max'] = 20.00;
                $value['ratio_min'] = 10.00;
            }
            $value['strategy'] = ProductInfoModel::where('isopen', 1)->limit(4)->orderRaw('rand()')->select();
        }
        $day24 = false;
        $hours_24 = ConfigModel::getConfigValue('task_reset_type');
        if ($hours_24 == 1) {
            $day24 = true;
        }
        $task_model = new TaskModel();
        $task_count = $task_model->getTaskNumber($this->userid, $day24);
        $qmtai = [
            'task_count' => $this->userInfo['task_count'],//剩余任务数量
            'task_end' => TaskQmtaiModel::where('user_id', $this->userid)->count(),
            'vip' => UserModel::getLevelId($this->userid),
            'day' => intval((strtotime(date("Y-m-d")) - strtotime(date("Y") . "-01-01")) / 86400),
            'day_money' => TaskQmtaiModel::where('user_id', $this->userid)->where('day', date("Y-m-d"))->where('status', 2)->sum('money'),
            'property' => TaskQmtaiModel::where('user_id', $this->userid)->where('status', 2)->sum('product_price'),
            'money' => TaskQmtaiModel::where('user_id', $this->userid)->where('status', 2)->sum('money')
        ];
//        View::assign([
//            'member_level' => $member_level,
//            'levels' => $levels,
//            'user' => $this->userInfo,
//            'qmtai' => $qmtai,
//            'task_count' => $task_count,
//            'customer_services' => $customer_services,
//        ]);
        $data = [
//            'member_level' => $member_level,
//            'levels' => $levels,
//            'user' => $this->userInfo,
            'qmtai' => $qmtai,
            'task_count' => $task_count,
//            'customer_services' => $customer_services,
        ];
//        $user_money = UserModel::getFieldData($this->userid, 'user_money');
//        $brokerage_money = UserModel::getFieldData($this->userid, 'brokerage_money');
//        View::assign('all_user_money', number_format($user_money + $brokerage_money, 2, '.', ','));
//        return View::fetch();
        return json(msg(0, $data, ''));
    }

    /**
     * 量化记录
     * @return mixed
     */
    public function record()
    {
        $userid = $this->userid;
        $task_qmtai = TaskQmtaiModel::where('user_id', $userid)->order('id desc')->select();
        foreach ($task_qmtai as &$value) {
            $value['finish_time'] = date("Y-m-d H:i:s", $value['finish_time']);
        }
        return json(msg(0, $task_qmtai, ''));
    }

    /**
     * 量化教程
     * @return mixed
     */
    public function tutorials()
    {
        $userid = $this->userid;
        return View::fetch();
    }

    /**
     * 添加量化任务
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function addTask()
    {
        $user_id = $this->userid;
        $task_model = new TaskQmtaiModel();
        $qmtai_temp = ProductInfoModel::getProductInfo();
        // * id 产品id
        //     * name 产品名称
        //     * img 产品图片
        //     * price 量化金额
        $product = [
            'id' => $qmtai_temp['id'],
            'name' => $qmtai_temp['ptitle'],
            'img' => $qmtai_temp['logo'],
            'price' => RechargeModel::getRecharge($user_id)
        ];
        $level_id = UserModel::getLevelId($user_id);
        $level_data = LevelModel::where('level_id', $level_id)->field('task_num,income')->find();
        if (empty($level_data)) {
            return [
                'code' => 9,
                'msg' => '等级不存在'
            ];
        }
        $brokerage = [
            'level_id' => $level_id,
            'money' => $level_data['income']
        ];
        $out_data = $task_model->addTask($user_id, $product, $brokerage);
        if ($out_data['code'] == 1) {
            $this->success(lang($out_data['msg']));
        } else {
            $this->error(lang($out_data['msg']));
        }
    }

}
