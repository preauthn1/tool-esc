<?php

namespace app\admin\controller;

use app\admin\model\ConfigModel;
use app\admin\transform\BrokeragePoolTransform;
use app\common\model\MoneyLogModel;
use think\facade\View;

class BrokeragePool extends Base
{

    public function index()
    {
        View::assign('title', lang('个人佣金池'));
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $page = $param['pageNumber'];

            $where = [];
            if (array_key_exists('user_id', $param) && $param['user_id']) {
                $where[] = ['user_id', '=', $param['user_id']];
            }
//            if ($param['user_name']) {
//                $where[] = ['user_name', '=', $param['user_name']];
//            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime']);
                $end_time   = strtotime($param['endTime']);
                $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
            }

            // 获取数据
            $result = MoneyLogModel::with(['user', 'user.user_brokerage_pool'])
                ->where($where)
                ->where('detail_type', 25)
                ->order(['id' => 'desc'])
                ->paginate(['page' => $page, 'list_rows' => $limit]);
            $data = (new BrokeragePoolTransform())->transformPages($result->toArray());
            return json($data);
        }
        return View::fetch();
    }

    public function config(): string
    {
        $brokerage_pool_config = ConfigModel::getConfigValue('brokerage_pool_config');
        $brokerage_pool_config = $brokerage_pool_config ? json_decode($brokerage_pool_config, true) : [];
        View::assign([
            'brokerage_pool_config' => $brokerage_pool_config,
        ]);
        return View::fetch();
    }
}

