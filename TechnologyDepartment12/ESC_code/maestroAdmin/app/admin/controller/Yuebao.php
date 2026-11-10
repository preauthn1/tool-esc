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

use app\admin\model\YuebaoModel;
use app\admin\model\RateModel;
use think\facade\Db;
use think\facade\View;

class Yuebao extends Base
{
    //用户列表
    public function index()
    {
        $status = config('yuebao_status');
        if(request()->isAjax()){
            $param  = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit  = $param['pageSize'];
            $is_xuni  = $param['is_xuni'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where[] = ['w.is_demo', '=', 0];
            if (!empty($param['searchText'])) {
                $where[] = ['hu.mobile|hu.true_name', 'like', '%' . $param['searchText'] . '%'];
            }
            if ($param['days'] > 0) {
                $where[] = ['w.days', '=', $param['days']];
            }
            $where[] = ['w.status', '>', -2];
            if($param['searchStatus'] != '') {
                $where[] = ['w.status', '=', $param['searchStatus']];
            }
            if (in_array($is_xuni, [0,1])) {
                $where[] = ['hu.is_xuni', '=', $is_xuni];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time   = strtotime($param['endTime'] . " 23:59:59");
                $where[] = ['w.create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            $medol = new YuebaoModel();
            $selectResult = $medol->getLlistsByWhere($where, $offset, $limit);
            // 拼装参数
            foreach($selectResult as $key=>$vo){
                if($vo['status'] == 1){
                    $buttons = showOperate($this->makeButton($vo['id']));
                } else {
                    $buttons = '暂无';
                }
                $selectResult[$key]['status']      = $status[$vo['status']];
                $selectResult[$key]['rate'] = ($vo['rate']/100) . "%";
//                $selectResult[$key]['create_time'] = date('Y-m-d H:i:s', $vo['create_time']);
                $selectResult[$key]['operate']     = $buttons;
            }
            $return['total'] = $medol->getAllLists($where);  //总数据
            $return['rows']  = $selectResult;

            $model = new YuebaoModel();
            $stat = $model->alias('w')
                    ->join('home_user hu', 'w.user_id = hu.id', 'left')
                    ->where($where)->field('sum(w.principal) AS totalBtc, sum(w.gray) AS totalGray')->find();
            $stat_text = '';
            if ($stat) {
                $stat_text .= "<span class=\"label label-info\">本金总额:" . sprintf4f($stat['totalBtc']) . "</span> ";
                $stat_text .= "<span class=\"label label-danger\">利息总额:" . sprintf4f($stat['totalGray']) . "</span>";
            }
            $return['stat']  = $stat;
            $return['stat_text']  = $stat_text;
            return json($return);
        }
        $rate = RateModel::order('days ASC')->select();
        View::assign('rate', $rate);
        View::assign([
            'status' => $status
        ]);
        return View::fetch();
    }

    // 审核
    public function checkStatus()
    {
        $id     = input('param.id');
        $status = input('param.status');
        $info   = Db::name('yuebao')->where('id', $id)->find();
        if(!$info) return json(msg(-1, '', lang('余额宝记录信息不存在')));
        $redis = \think\facade\Cache::store('redis');
        $redis_key = "yuebao_check_status:{$id}";
        if ($redis->has($redis_key)) {
            return json(msg(-1, '', lang('请勿重复操作')));
        }
        $redis->set($redis_key, 1, 3);
        $user_id = $info['user_id'];
        if($status == 2){
            addMoneyLog($user_id, $info['principal'], 6, lang('余额宝取出本金'), 2);
            if($info['gray'] > 0){
                addMoneyLog($user_id, $info['gray'], 6);
            }
        }
        $ret = Db::name('yuebao')->where(['id'=>$id])->update(['status' => $status, 'out_time' => time()]);
        if($ret) return json(msg(1, '', lang('操作成功')));
        else return json(msg(-1, '', lang('操作失败')));
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id)
    {
        return [
            '转出' => [
                'auth' => 'yuebao/checkstatus',
                'href' => "javascript:checkStatus(" .$id .", 2)",
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ]
        ];
    }
}
