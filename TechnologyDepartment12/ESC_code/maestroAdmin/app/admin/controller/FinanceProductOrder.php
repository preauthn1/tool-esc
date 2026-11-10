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

use app\admin\model\LevelModel;
use app\admin\model\MemberModel;
use app\admin\transform\FinanceProductOrderTransform;
use app\common\model\FinanceProductOrderModel as Model;
use app\common\model\TaskSessionModel;
use app\common\repository\FinanceProductOrderRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Cache;
use think\facade\View;

class FinanceProductOrder extends Base
{

    public function index()
    {
        $order_status = FinanceProductOrderRepository::$statusMap;

        $level_list = LevelModel::column('level_name', 'level_id');
        $session_list = TaskSessionModel::order('start_time')->select();
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
                $where[] = ['member_model.user_name', 'like', $param['user_name'] . '%'];
            }
            if (isset($param['level_id']) && $param['level_id'] != '') {
                $where[] = ['finance_product_order_model.level_id', '=', $param['level_id']];
            }
            if (isset($param['order_no']) && $param['order_no'] != '') {
                $where[] = ['order_no', 'like', '%' . $param['order_no'] . '%'];
            }
            if (isset($param['status']) && $param['status'] != '') {
                $where[] = ['finance_product_order_model.status', '=', $param['status']];
            } else {
//                $where[] = ['finance_product_order_model.status', '<>', FinanceProductOrderRepository::STATUS_PENDING];
            }
            if (!empty($param['leader1'])) {
                $where[] = ['member_model.tree_id', 'like', '%/' . $param['leader1'] . '/%'];
                $where[] = ['member_model.tree_id', 'not like', '%/' . $param['leader1'] . '/'];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                if ($param['session']) {
                    $session = explode('-', $param['session']);
                    $start_time = strtotime(explode(' ', $param['startTime'])[0] . ' ' . $session[0]);
                    $end_time = strtotime(explode(' ', $param['endTime'])[0] . ' ' . $session[1]);
                } else {
                    $start_time = strtotime(($param['startTime']));
                    $end_time = strtotime(($param['endTime']));
                }

                $where[] = ['finance_product_order_model.create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            $query = (new FinanceProductOrderRepository())->model();
            if (in_array($is_xuni, [0, 1])) {
                $query = $query->hasWhere('user', ['is_xuni' => $is_xuni]);
            } else {
                $query = $query->hasWhere('user');
            }
            $result = $query->with(['user', 'level'])
                ->order(['create_time' => 'desc', 'id' => 'desc'])
                ->where($where)
                ->paginate(['page' => $page, 'list_rows' => $limit]);
            $data = (new FinanceProductOrderTransform)->transformPages($result->toArray());
            return json($data);
        }
        $agent = MemberModel::where('is_proxy', 1)->select();
        View::assign('agent', $agent);
        View::assign([
            'order_status' => $order_status,
            'level_list' => $level_list,
            'session_list' => $session_list,
        ]);
        return View::fetch();
    }

    /**
     * 导出文件
     */
    public function export_file()
    {
        header('X-Accel-Buffering: no');
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $param = input('param.');
        $limit = 200; // 每次处理200条记录

        // 构建查询条件（与原代码相同）
        $where = [];
        if (!empty($param['user_id'])) {
            $where[] = ['user_id', '=', $param['user_id']];
        }
        if (!empty($param['user_name'])) {
            $where[] = ['member_model.user_name', 'like', $param['user_name'] . '%'];
        }
        if (isset($param['level_id']) && $param['level_id'] != '') {
            $where[] = ['finance_product_order_model.level_id', '=', $param['level_id']];
        }
        if (isset($param['order_no']) && $param['order_no'] != '') {
            $where[] = ['order_no', 'like', '%' . $param['order_no'] . '%'];
        }
        if (isset($param['status']) && $param['status'] != '') {
            $where[] = ['finance_product_order_model.status', '=', $param['status']];
        }
        if (!empty($param['leader1'])) {
            $where[] = ['member_model.tree_id', 'like', '%/' . $param['leader1'] . '/%'];
            $where[] = ['member_model.tree_id', 'not like', '%/' . $param['leader1'] . '/'];
        }
        if (!empty($param['startTime']) && !empty($param['endTime'])) {
            if ($param['session']) {
                $session = explode('-', $param['session']);
                $start_time = strtotime(explode(' ', $param['startTime'])[0] . ' ' . $session[0]);
                $end_time = strtotime(explode(' ', $param['endTime'])[0] . ' ' . $session[1]);
            } else {
                $start_time = strtotime(($param['startTime']));
                $end_time = strtotime(($param['endTime']));
            }
            $where[] = ['finance_product_order_model.create_time', 'BETWEEN', [$start_time, $end_time]];
        }

        $is_xuni = $param['is_xuni'];
        $query = (new FinanceProductOrderRepository())->model();
        if (in_array($is_xuni, [0, 1])) {
            $query = $query->hasWhere('user', ['is_xuni' => $is_xuni]);
        } else {
            $query = $query->hasWhere('user');
        }
        $session_id = session('file_id');
        if(empty($session_id)){
            $session_id = uniqid();
            session('file_id', $session_id);
        }
        $cache_key = md5(json_encode($where) . "finance_product". $session_id);
        $cache_file = Cache::store('file');

        // 获取总记录数
        $total = $query->where($where)->count();

        $header = [
            "id" => "ID",
            "order_no" => "订单号",
            "level_name" => "广告位名称",
            "level_id" => "广告位等级",
            "user_info" => "用户信息",
            "finance_product_name" => "任务信息",
            "session_no" => "广告位批次",
            "stock_range_min" => "广告位批次min",
            "stock_range_max" => "广告位批次max",
            "balance" => "匹配时用户余额",
            "invest_rate" => "占余额比例",
            "price" => "花费金额",
            "money" => "投资金额",
            "profit" => "收益率",
            "cycle" => "投资时长",
            "invest_period_type" => "投资时长单位",
            "profit_money" => "投资收益金额",
            "start_time" => "开始时间",
            "end_time" => "结束时间",
            "status_str" => "状态",
            "match_time" => "匹配时间",
            "buy_time" => "购买时间",
            "create_time" => "添加时间",
            "settlement_time" => "结算时间",
            "top_leader_remark" => "所属代理",
            "is_xuni_text" => "是否虚拟账号",
        ];

        $save_path = public_path() . 'storage/export/productorder/' . date('Ymd') . '/';
        if (!is_dir(public_path() . 'storage/export/productorder/')) {
            mkdir(public_path() . 'storage/export/productorder/', 0777, true);
        }
        if (!is_dir($save_path)) {
            mkdir($save_path, 0777, true);
        }

        // 获取缓存信息
        $file_name = $cache_file->get($cache_key . "_name");
        $current_page = $cache_file->get($cache_key . "_page", 1);
        $processed_count = $cache_file->get($cache_key . "_offset", 0);

        // 如果没有缓存文件名，说明是新任务
        if (empty($file_name)) {
            $file_name = "广告位记录_" . genOrderSN() . ".xlsx";
            $cache_file->set($cache_key . "_name", $file_name, 600);
            $current_page = 1;
            $processed_count = 0;
        }

        $full_file_path = $save_path . $file_name;

        // 创建或加载Excel
        if (file_exists($full_file_path) && $current_page > 1) {
            // 如果文件已存在且不是第一页，加载现有文件
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($full_file_path);
            $sheet = $spreadsheet->getActiveSheet();
        } else {
            // 创建新的Excel文件
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // 写入表头（仅在创建新文件时写入）
            $col = 1;
            foreach ($header as $key => $value) {
                $cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1';
                $sheet->setCellValue($cellAddress, $value);
                $col++;
            }
        }

        do {
            $result = $query->with(['user', 'level'])
                ->order(['create_time' => 'desc'])
                ->where($where)
                ->limit($limit)
                ->page($current_page)
                ->select();

            if (empty($result)) {
                break;
            }

            $data = [];
            foreach ($result as $key => $item) {
                $data[$key] = (new FinanceProductOrderTransform)->transform($item->toArray());
            }

            // 写入数据到Excel
            foreach ($data as $rowIndex => $rowData) {
                $col = 1;
                $excelRow = $processed_count + $rowIndex + 2; // +2是因为第一行是表头
                foreach ($header as $field => $title) {
                    $cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $excelRow;
                    $sheet->setCellValue($cellAddress, $rowData[$field] ?? '');
                    $col++;
                }
            }

            // 更新处理计数
            $processed_count += count($result);
            $current_page++;

            // 保存当前进度到缓存
            $cache_file->set($cache_key . "_page", $current_page, 600);
            $cache_file->set($cache_key . "_offset", $processed_count, 600);

            // 保存Excel文件
            $writer = new Xlsx($spreadsheet);
            $writer->save($full_file_path);

            // 清理内存
            unset($result, $data);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // 发送进度信息
            $item_data = [
                'item' => $processed_count,
                'total' => $total
            ];
            echo "data: " . json_encode($item_data) . "\n\n";
            ob_flush();
            flush();

            // 短暂休眠以防止连接超时
            usleep(100000); // 0.1秒

        } while ($processed_count < $total);

        // 导出完成，清理缓存
        $cache_file->delete($cache_key . "_name");
        $cache_file->delete($cache_key . "_page");
        $cache_file->delete($cache_key . "_offset");

        $file_path = str_replace(public_path() . 'storage', "", $save_path) . $file_name;
        $file_data = [
            'url' => toMedia($file_path, 1),
            'file_name' => $file_name
        ];

        echo "event: stream-end\n";
        ob_flush();
        flush();
        echo "data: " . json_encode($file_data) . "\n\n";
        ob_flush();
        flush();
    }


    public function edit()
    {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.');
            $model = new Model;
            if ($id > 0) {
                // 比例 收益率 周期
                $order = $model->find($id);

                if ($order->money != $param['money'] || $order->profit != $param['profit']) {
                    if ($order->status != FinanceProductOrderRepository::STATUS_PENDING) {
                        $this->error(lang('待匹配状态才可以修改投资金额和收益率'));
                    }
                    // 有修改收益率或投资金额
                    $param['profit_money'] = $param['money'] * $param['profit'] / 100;
                    $order->profit_money = $param['money'] * $param['profit'] / 100;
                    $order->money = $param['money'];
                    $order->profit = $param['profit'];
                }
                if ($order->invest_period_type != $param['invest_period_type'] || $order->cycle != $param['cycle']) {
                    if ($order->status != FinanceProductOrderRepository::STATUS_PENDING && $order->status != FinanceProductOrderRepository::STATUS_PURCHASED) {
                        $this->error(lang('待匹配和已购买状态才可以修改广告位周期'));
                    }
                    $order->invest_period_type = $param['invest_period_type'];
                    $order->cycle = $param['cycle'];
                    // 有修改收益率或投资金额
                    if ($order->status == FinanceProductOrderRepository::STATUS_PURCHASED && $order->start_time > 0) {
                        $order->end_time = (new FinanceProductOrderRepository())->calculateTimestampFromDays($order, $order->start_time);
                    }
                }
                $result = $order->save();
            } else {
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            } else {
                insert_operation_log('广告位记录:' . ($id > 0 ? '修改' : '添加'), $param);
            }
            $this->success(lang('提交成功'), '', 'close');
        }
        $info = Model::where('id', $id)->find();
        $title = lang('编辑');
        if (empty($info)) {
            $title = lang('添加');
            $model = new Model();
            $info = array_fill_keys($model->getTableFields(), '');
            $info['status'] = 1;
        }
        $info['money'] = i18n_sprintf($info['money']);
        View::assign("title", $title);
        View::assign("info", $info);
        return View::fetch();
    }
}
