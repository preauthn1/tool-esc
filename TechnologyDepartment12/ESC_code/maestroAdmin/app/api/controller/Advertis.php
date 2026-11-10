<?php

namespace app\api\controller;


use think\facade\Lang;
use think\response\Json;
use think\facade\Db;

class Advertis extends Base
{

    public function index(): Json
    {
        $res = [];
        // 先获取每个广告名称的最新时间
        $subQuery = Db::table('qd_advertis_space')
            ->field('*, ROW_NUMBER() OVER (PARTITION BY advertis_en_name ORDER BY create_time DESC) as rn')
            ->where('advertis_en_name', '<>', '')
            ->buildSql();

        $list = Db::table($subQuery . ' t')
            ->field('advertis_name, turnover, exposure, cpm_cost_money, advertis_en_name as advertis_en')
            ->where('rn', 1)
            ->order('create_time desc')
            ->select()
            ->toArray();
        $stock_all = 0;
        $turnover_all = 0;
        $exposure_all = 0;
        foreach ($list as &$item) {
            $item['matching_rate'] = round($item['turnover'] / $item['exposure'] * 100, 2);
            $item['stock_num'] = Db::table('qd_advertis_num')->where('advertis_name', $item['advertis_name'])->value('num');
            $stock_all += $item['stock_num'];
            $turnover_all += $item['turnover'];
            $exposure_all += $item['exposure'];
        }
        $res['stock_all_last'] = round($stock_all, 4);
        if ($exposure_all <= 0) {
            $res['matching_rate'] = 0;
        } else {
            $res['matching_rate'] = round($turnover_all / $exposure_all * 100, 2);
        }
        $res['list'] = $list;
        return json(msg(0, $res, '请求成功'));
    }

    public function detail(): Json
    {
        $res = [];
        $type = input('param.type');
        if (empty($type)) {
            return json(msg(100, null, '类型不能为空'));
        }
        $cycle = input('param.cycle');
        if (empty($cycle)) {
            return json(msg(100, null, '周期不能为空'));
        }
        $between = [];
        switch ($cycle) {
            case '1':
                $between = [strtotime(date('Y-m-d', time())), time()];
                break;
            case '3':
                $between = [strtotime(date('Y-m-d', strtotime('-3 days'))), time()];
                break;
            case '5':
                $between = [strtotime(date('Y-m-d', strtotime('-5 days'))), time()];
                break;
            case '7':
                $between = [strtotime(date('Y-m-d', strtotime('-7 days'))), time()];
                break;
            default :
                $between = [strtotime(date('Y-m-d', time())), time()];
                break;
        }
        if ($type == 'all') {
            //汇总查询
            $subQuery = Db::table('qd_advertis_space')
                ->field('advertis_name,advertis_en_name, MAX(create_time) as max_time')
                ->where('advertis_en_name', '<>', '')
                ->group('advertis_en_name')
                ->buildSql();
            // 再关联查询获取完整记录
            $last_list = Db::table('qd_advertis_space')
                ->alias('a')
                ->join([$subQuery => 'b'], 'a.advertis_en_name = b.advertis_en_name AND a.create_time = b.max_time')
                ->field('a.*')
                ->order('a.create_time desc')
                ->select()
                ->toArray();
            // `advertis_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '库存名称',
            //  `exposure` int DEFAULT NULL COMMENT '曝光量',
            //  `turnover` int DEFAULT NULL COMMENT '成交量',
            //  `turnover_num` int DEFAULT NULL COMMENT '成交笔数',
            //  `turnover_money` decimal(20,2) unsigned NOT NULL COMMENT '成交金额',
            //  `cpm_cost_money` decimal(20,2) unsigned NOT NULL COMMENT 'CPM千次曝光平均成本',
            //  `create_time` int DEFAULT NULL COMMENT '创建时间',
            //  `update_time` int DEFAULT NULL COMMENT '更新时间',
            //  `advertis_en_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '英文名称',
            $last_data = [
                'advertis_name' => '',
                'exposure' => 0,
                'turnover' => 0,
                'turnover_num' => 0,
                'turnover_money' => 0,
                'cpm_cost_money' => 0,
                'advertis_en_name' => '',
            ];

            $list_arr = [];
            foreach ($last_list as $item) {
                $last_data['exposure'] += $item['exposure'];
                $last_data['turnover'] += $item['turnover'];
                $last_data['turnover_num'] += $item['turnover_num'];
                $last_data['turnover_money'] += $item['turnover_money'];
                $last_data['cpm_cost_money'] += $item['cpm_cost_money'];
                $type = $item['advertis_name'];
                $list_arr[] = $this->_dayList($type, $between);
            }
            //把  formatted_date 与 sort_type 相同的数据加到一个数组里面  变成一个一维数组
            $result = [];
            foreach ($list_arr as $subArray) {
                foreach ($subArray as $item) {
                    $key = $item['formatted_date'] . '_' . $item['sort_type'];
                    // 如果不存在，则直接添加
                    if (!isset($result[$key])) {
                        $result[$key] = $item;
                    } else {
                        // 如果需要合并相同key的数据，可以累加数值字段
                        $result[$key]['total_exposure'] += $item['total_exposure'];
                        $result[$key]['total_turnover'] += $item['total_turnover'];
                        // 其他字段根据需求处理
                    }
                }
            }
            $list = array_values($result);
            // 先按 formatted_date 排序，再按 sort_type 排序
            usort($list, function($a, $b) {
                // 先按日期排序
                $dateCompare = strcmp($a['formatted_date'], $b['formatted_date']);
                if ($dateCompare != 0) {
                    return $dateCompare;
                }
                // 日期相同，按 sort_type 排序
                // sort_type 格式为 "M1", "M2", "M3"...，提取数字部分进行比较
                $numA = intval(substr($a['sort_type'], 1));
                $numB = intval(substr($b['sort_type'], 1));
                return $numA - $numB;
            });
            //
        } else {
            $last_data = Db::table('qd_advertis_space')
                ->where('advertis_name', $type)
                ->field('*')->order('create_time desc')->find();
            $list = $this->_dayList($type, $between);
        }
        $res['type'] = $type;
        $res['last_data'] = $last_data;
        $res['list'] = $list;
        return json(msg(0, $res, '请求成功'));
    }

    /**
     * 获取广告位数据
     * @param $type
     * @param $between
     * @return mixed
     */
    public function _dayList($type, $between)
    {
        // 再关联查询获取完整记录
        $list = Db::table('qd_advertis_space')
            ->field('id,exposure as total_exposure, 
                    turnover as total_turnover,
                    create_time')
            ->where('advertis_name', $type)
            ->whereBetween('create_time', $between)
            ->order('create_time asc')
            ->select()
            ->toArray();
        //日期
        foreach ($list as &$item) {
            $item['formatted_date'] = date('m/d', $item['create_time']);
        }
        //如果formatted_date是同一天  sort_type_num 累加1 如果不是同一天 sort_type_num 置1
        $sort_type_num = 1;
        foreach ($list as $key => &$val) {
            if ($key > 0 && $val['formatted_date'] == $list[$key - 1]['formatted_date']) {
                $sort_type_num++;
            } else {
                $sort_type_num = 1;
            }
            $val['sort_type_num'] = $sort_type_num;
            $val['sort_type'] = "M" . $sort_type_num;
        }
        return $list;
    }
}