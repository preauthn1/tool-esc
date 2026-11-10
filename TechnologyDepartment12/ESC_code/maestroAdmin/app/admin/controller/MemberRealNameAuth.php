<?php

namespace app\admin\controller;

use app\common\model\MemberRealNameAuthModel;
use Exception;
use think\facade\Db;
use think\facade\View;
use think\response\Json;

class MemberRealNameAuth extends Base
{

    //用户列表
    public function index()
    {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (!empty($param['user_id'])) {
                $where[] = ['user_id', '=', $param['user_id']];
            }
            if (!empty($param['real_name'])) {
                $where[] = ['real_name', 'like', '%' . $param['real_name'] . '%'];
            }
            $finance_product = new MemberRealNameAuthModel();
            $selectResult = $finance_product->getMemberRealNameAuthByWhere($where, $offset, $limit);
            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['id_card_front'] = show_img($vo['id_card_front']);
                $selectResult[$key]['id_card_back'] = show_img($vo['id_card_back']);
                $selectResult[$key]['hand_hold_photo'] = show_img($vo['hand_hold_photo']);
                if ($vo['status'] == 0) {
                    $selectResult[$key]['operate'] = showOperate($this->makeButton($vo['id']));
                }
                $selectResult[$key]['status_text'] = MemberRealNameAuthModel::$statusMap[$vo['status']];
                $selectResult[$key]['check_time'] = $vo['check_time'] > 0 ? date('Y-m-d H:i:s', $vo['check_time']) : '-';;
                $selectResult[$key]['admin_name'] = $vo['admin'] ? $vo['admin']['user_name'] : '系统';
            }
            $return['total'] = $finance_product->getAllMemberRealNameAuth($where);  //总数据
            $return['rows'] = $selectResult;
            return json($return);
        }
        $remark = MemberRealNameAuthModel::where("remark", "<>", "")->distinct(true)->column("remark");
        View::assign([
            'remark' => array_filter($remark),
        ]);
        return View::fetch();
    }

    // 删除实名认证
    public function MemberRealNameAuthDel(): Json
    {
        $id = input('param.id');
        $finance_product = new MemberRealNameAuthModel();
        $flag = $finance_product->delMemberRealNameAuth($id);
        return json(msg($flag['code'], $flag['data'], lang($flag['msg'])));
    }


    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id)
    {
        return [
            '审核通过' => [
                'auth' => 'MemberRealNameAuth/checkstatus',
                'href' => "javascript:checkStatus(" . $id . ", 1)",
                'btnStyle' => 'primary',
                'icon' => ''
            ],
            '审核失败' => [
                'auth' => 'MemberRealNameAuth/checkstatus',
                'href' => "javascript:checkStatus(" . $id . ", 2)",
                'btnStyle' => 'danger',
                'icon' => ''
            ]
        ];
    }

    // 审核
    public function checkStatus()
    {
        $id = input('param.id');
        $status = input('param.status');
        $remark = input('param.remark');
        if (!in_array($status, [1, 2])) { // 1-成功 2-失败
            return json(msg(-1, '', lang('审核状态有误')));
        }
        if (empty($remark)) {
            return json(msg(-1, '', lang('备注不能为空')));
        }

        $ids = explode(",", $id);
        foreach ($ids as $id) {
            $info = MemberRealNameAuthModel::where('id', $id)->where("status", 0)->lock()->find();
            if (!$info) {
                return json(msg(-1, '', lang('实名信息不存在')));
            }
        }
        foreach ($ids as $id) {
            MemberRealNameAuthModel::startTrans();
            $admin_id = session('id');
            try {
                $info = MemberRealNameAuthModel::where('id', $id)->where("status", 0)->lock()->find();
                $info->status = $status;
                $info->admin_id = $admin_id;
                $info->check_time = time();
                $info->remark = $remark;
                if ($status == MemberRealNameAuthModel::STATUS_SUCCESS) {
                    // 设置用户kyc_status = 1
                    Db::name('home_user')->where(['id' => $info->user_id])->update(['kyc_status' => 1]);
                }
                $info->save();
                MemberRealNameAuthModel::commit();
            } catch (Exception $e) {
                MemberRealNameAuthModel::rollback();
            }

        }
        return json(msg(1, '', lang('操作成功')));
    }

}
