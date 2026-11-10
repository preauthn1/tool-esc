<?php

namespace app\admin\controller;

use app\common\repository\PromotionRewardRecordRepository;
use think\facade\View;

class PromotionRewardRecord extends Base
{

    // 计划任务列表
    public function index()
    {
        View::assign('title', lang('连续推广奖励领取记录'));
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['user_id']) && $param['user_id'] != '') {
                $where[] = ['user_id', '=', $param['user_id']];
            }

            $model = (new PromotionRewardRecordRepository)->model();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            $return['total'] = $total;  // 总数据
            $return['rows'] = $result;
            return json($return);
        }
        return View::fetch();
    }

}
