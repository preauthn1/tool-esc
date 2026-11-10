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

namespace app\admin\controller;

use app\admin\model\MemberModel;
use app\admin\transform\InvestmentProductOrderTransform;
use app\common\model\InvestmentProductModel;
use app\common\repository\InvestmentProductOrderRepository;
use think\facade\View;

class InvestmentProductOrder extends Base
{

    public function index()
    {
        $order_status = InvestmentProductOrderRepository::$statusMap;

        $product_list = InvestmentProductModel::column('product_name', 'id');
        if (request()->isAjax() || $this->request->isPost()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $page = $param['pageNumber'];
            $is_xuni = $param['is_xuni'];
            $where = [];
            if (!empty($param['user_id'])) {
//                $uid = getUseridByMobile($param['searchText']);
//                if (!empty($uid)) {
//                    $where[] = ['user_id', '', $uid];
//                } else {
                $where[] = ['user_id', '=', $param['user_id']];
//                }
            }
            // 用户名搜索
            if (!empty($param['user_name'])) {
                $where[] = ['member_model.user_name', 'like', $param['user_name'].'%'];
            }
            if (isset($param['investment_product_id']) && $param['investment_product_id'] != '') {
                $where[] = ['investment_product_id', '=', $param['investment_product_id']];
            }
            if (isset($param['order_no']) && $param['order_no'] != '') {
                $where[] = ['order_no', '=', $param['order_no']];
            }
            if (isset($param['status']) && $param['status'] != '') {
                $where[] = ['investment_product_order_model.status', '=', $param['status']];
            } else {
                $where[] = ['investment_product_order_model.status', '<>', InvestmentProductOrderRepository::STATUS_PENDING];
            }
            if (!empty($param['leader1'])) {
                $where[] = ['member_model.tree_id', 'like', '%/'.$param['leader1'].'/%'];
                $where[] = ['member_model.tree_id', 'not like', '%/'.$param['leader1'].'/'];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime(($param['startTime']));
                $end_time = strtotime(($param['endTime']));
                $where[] = ['investment_product_order_model.create_time', 'BETWEEN', [$start_time, $end_time]];
            }
//            dd($where);
            $export = $this->request->param("export/d", 0);
            if ($export) {
                $header = [
                    "id"                      => "ID",
                    "order_no"                => "订单号",
                    "level_name"              => "VIP等级",
                    "user_info"               => "用户信息",
                    "investment_product_name" => "任务信息",
                    "money"                   => "投资金额",
                    "profit"                  => "收益率",
                    "cycle"                   => "投资天数",
                    "profit_money"            => "投资收益金额",
                    "start_time"              => "开始时间",
                    "end_time"                => "结束时间",
                    "status_str"              => "状态",
                    "create_time"             => "添加时间",
                    "settlement_time"         => "结算时间",
                    "top_leader_remark"       => "所属代理",
                    "is_xuni_text"            => "是否虚拟账号",
                ];
                $query = (new InvestmentProductOrderRepository())->model();
                if (in_array($is_xuni, [0, 1])) {
                    $query = $query->hasWhere('user', ['is_xuni' => $is_xuni]);
                } else {
                    $query = $query->hasWhere('user');
                }
                $result = $query->with(['user', 'level'])
                    ->order(['create_time' => 'desc'])
                    ->where($where)
                    ->select();
                $data = (new InvestmentProductOrderTransform)->transformCollection($result);

                return genXlsx($header, $data, "广告位记录");
            }
            $query = (new InvestmentProductOrderRepository())->model();
            if (in_array($is_xuni, [0, 1])) {
                $query = $query->hasWhere('user', ['is_xuni' => $is_xuni]);
            } else {
                $query = $query->hasWhere('user');
            }
            $result = $query->with(['user', 'level'])
                ->order(['create_time' => 'desc'])
                ->where($where)
                ->paginate(['page' => $page, 'list_rows' => $limit]);
            $data = (new InvestmentProductOrderTransform)->transformPages($result->toArray());
            return json($data);
        }
        $agent = MemberModel::where('is_proxy', 1)->select();
        View::assign('agent', $agent);
        View::assign([
            'order_status' => $order_status,
            'product_list' => $product_list,
        ]);
        return View::fetch();
    }
}
