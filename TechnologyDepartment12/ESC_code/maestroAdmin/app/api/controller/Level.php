<?php

namespace app\api\controller;

use app\common\model\ConfigModel;
use app\common\model\UserModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\LevelRepository;
use app\lib\exception\FinanceProductPasswordException;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\response\Json;

class Level extends Base
{
    /**
     * 用户点击升级到指定等级
     * @param  int  $level_id
     * @return Json
     * @throws \Throwable
     * @throws Exception
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function upLevel(int $level_id = 0): Json
    {
//        list($orders, $task_session) = (new FinanceProductOrderRepository)->getLevelSessionOrders($level_id,
//            $this->userid);
//        if ($orders) {
//            return json(msg(0, [
//                'code' => 0,
//                'msg' => lang("有未完成的订单")
//            ], lang('成功')));
//        }
//        $userModel = new UserModel();
//        $mode = ConfigModel::getConfigValue('level_upgrade_type');
//        $out_data = $userModel->vipUpgradeStatus($this->userid, $level_id, $mode);
//        if ($out_data['code'] == 1) { // 成功
//            return json(msg(0, msg(0, '', lang($out_data['msg'])), lang($out_data['msg'])));
//        } else {
//            // 失败
//            if ($out_data['code'] == 11 || $out_data['code'] == 12) {
//                return json(msg(0, $out_data, lang($out_data['msg'])));
//            } else {
//                return json(msg(0, msg(-1, '', lang($out_data['msg'])), lang($out_data['msg'])));
//            }
//        }
        return json(msg(0, msg(0, '', lang('成功')), lang('成功')));
    }

    /**
     * 验证等级编码是否正确和是否到期
     * @return Json
     */
    public function checkPassword(): Json
    {
        $password = input('password', '');
        $level_id = input('level_id', 0);
        $type = input('type', 0);
        if (empty($password)) {
            return json(msg(0, ['code' => -1, 'msg' => lang('请输入编码')], lang('success')));
        }
        try {
            $res = (new LevelRepository())->checkPassword($password, $level_id, $type);
        } catch (FinanceProductPasswordException $e) {
            return json(msg(0, $e->extendedData, lang('success')));
        }
        return json(msg(0, $res, lang('success')));
    }
}
