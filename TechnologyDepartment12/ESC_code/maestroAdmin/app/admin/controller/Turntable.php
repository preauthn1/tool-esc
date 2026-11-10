<?php

namespace app\admin\controller;

use app\admin\model\PrizeLogModel;
use app\admin\model\PrizeModel;
use app\common\model\ConfigModel;
use app\common\model\TurntableCountModel;
use app\common\model\TurntableModel;
use app\common\model\UserModel;
use think\facade\Db;

/**
 * Class Turntable
 * 转盘
 * @package app\admin\controller
 */
class Turntable extends Base
{
    public function index()
    {
        $turntable_model = new TurntableModel();
        if (request()->isPost()) {
            $param = $this->request->param();
//            `status` tinyint(1) DEFAULT '1' COMMENT '1开启  2关闭',
//  `type` tinyint(1) DEFAULT '1' COMMENT '1转盘抽奖 2红包抽奖',
//  `prize` tinyint(1) DEFAULT '1' COMMENT '奖品类型 1全局  2局部 到VIP设置',
//  `capital_type` tinyint(2) DEFAULT '1' COMMENT '资金类型  1基础账户  5佣金账户',
//  `register_num` int(11) DEFAULT '0' COMMENT '注册奖励次数',
//  `invite_recharge` decimal(20,2) DEFAULT '0.00' COMMENT '邀请用户充值多少获得抽奖机会',
            $up_data = [
                'status' => $param['status'],
                'type' => $param['type'],
                'prize' => $param['prize'],
                'capital_type' => $param['capital_type'],
                'register_num' => $param['register_num'],
                'invite_recharge' => $param['invite_recharge'],
            ];
            $turntable_model->where('id', $param['id'])->update($up_data);
//            $this->success(lang('提交成功'),'','close');
        }
        $turntable = $turntable_model->getTurntable();
        View::assign('turntable', $turntable);
        return View::fetch();
    }

    /**
     * 奖品列表
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function prizeList()
    {
        $level_id = $this->request->param('level_id');
        $prize_model = new PrizeModel();
        $prize_reg_list = $prize_model->getPrizeList(1, $level_id);
        $prize_inv_list = $prize_model->getPrizeList(2, $level_id);
        if (request()->isAjax()) {
            $param = $this->request->param();
            $id = $param['id'];
            $value = $param['value'];
            $probability = $param['probability'];
            $probability_all = 0;
            foreach ($probability as $vx) {
                $probability_all += floatval($vx);
            }
            if (round($probability_all, 2) != round(100, 2)) {
                $this->error('概率总和必须为100');
            }
            foreach ($id as $key => $val) {
                $up_data = [
                    'value' => $value[$key],
                    'probability' => $probability[$key],
                ];
                $prize_model->where('id', $id[$key])->update($up_data);
            }
            $this->success('提交成功', '', 'close');
        }
        View::assign([
            'prize_reg_list' => $prize_reg_list,
            'prize_inv_list' => $prize_inv_list,
            'level_id' => $level_id,
            'fiat_currency_unit' => ConfigModel::getConfigValue('fiat_currency_unit')
        ]);
        return View::fetch();
    }

    /**
     * 中奖记录
     * @return mixed|\think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function winlist()
    {
        $act = $this->request->param('act/s');
        if (request()->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $searchText = $param['title'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if ($searchText) {
                $where[] = ['user_id|name', 'like', '%' . $searchText . '%'];
            }
            $model = Db::name('turntable_log');
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            foreach ($result as $key => $vo) {
                $result[$key]['create_time'] = timeToStr($vo['create_time']);
                $result[$key]['update_time'] = timeToStr($vo['update_time']);
            }
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }
        if ($act == 'add') {
            return View::fetch('add');
        }
        return View::fetch();
    }

    /**
     * 中奖记录
     * @return mixed|\think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function countlist()
    {
        $act = $this->request->param('act/s');
        if (request()->isAjax()) {
            if ($act == 'add') {
                $user_id = $this->request->param('user_id');
                $num = $this->request->param('num');
                $remark = $this->request->param('remark');
                $agent = $this->request->param('agent');
                if ($agent == 1) {
                    //是代理线
                    $uids = UserModel::getTeamAllIds($user_id, 7);
                    if (empty($uids)) {
                        $this->error('该用户没有代理线');
                    }
                    $ret = [];
                    foreach ($uids as $uid) {
                        $map = [
                            'user_id' => $uid,
                            'turntable_id' => 1
                        ];
                        $temp = TurntableCountModel::where($map)->find();
                        if (empty($temp)) {
                            $data = [
                                'user_id' => $uid,
                                'turntable_id' => 1,
                                'from_user_id' => "[]",
                                'reg_num' => 0,
                                'num' => $num,
                                'remark' => $remark,
                                'create_time' => time(),
                                'update_time' => time(),
                            ];
                            $ret = TurntableCountModel::insert($data);
                        } else {
                            $data = [
                                'num' => ($num + $temp['num']) > 0 ? ($num + $temp['num']) : 0,
                                'remark' => $remark,
                                'update_time' => time(),
                            ];
                            $ret = TurntableCountModel::where($map)->update($data);
                        }
                    }
                    if (!$ret) {
                        $this->error('添加失败', '', 'close');
                    }
                    $this->success('添加成功', '', 'close');
                } else {
                    $map = [
                        'user_id' => $user_id,
                        'turntable_id' => 1
                    ];
                    $user_temp = UserModel::where('id', $user_id)->find();
                    if (empty($user_temp)) {
                        $this->error('用户不存在');
                    }
                    $temp = TurntableCountModel::where($map)->find();
                    if (empty($temp)) {
                        $data = [
                            'user_id' => $user_id,
                            'turntable_id' => 1,
                            'from_user_id' => "[]",
                            'reg_num' => 0,
                            'num' => $num,
                            'remark' => $remark,
                            'create_time' => time(),
                            'update_time' => time(),
                        ];
                        $ret = TurntableCountModel::insert($data);
                    } else {
                        $data = [
                            'num' => ($num + $temp['num']) > 0 ? ($num + $temp['num']) : 0,
                            'remark' => $remark,
                            'update_time' => time(),
                        ];
                        $ret = TurntableCountModel::where($map)->update($data);
                    }
                    if (!$ret) {
                        $this->error('添加失败', '', 'close');
                    }
                    $this->success('添加成功', '', 'close');
                }
            }
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $searchText = $param['user_id'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if ($searchText) {
                $where[] = ['user_id', 'like', '%' . $searchText . '%'];
            }
            $model = Db::name('turntable_count');
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            foreach ($result as $key => $vo) {
                $result[$key]['create_time'] = timeToStr($vo['create_time']);
                $result[$key]['update_time'] = timeToStr($vo['update_time']);
            }
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }
        if ($act == 'add') {
            return View::fetch('add');
        }
        return View::fetch();
    }


    /**
     * 新增奖品
     * @return mixed|\think\response\Json
     */
    public function prizeAdd()
    {
        if (request()->isPost()) {
            $param = input('post.');
            $prize = new PrizeModel();
            $flag = $prize->insertPrize($param);
            return json(msg($flag['code'], $flag['data'], lang($flag['msg'])));
        }
        View::assign('prize_type', config('prize_type'));
        View::assign('status', config('app.status'));
        return View::fetch();
    }

    /**
     * 编辑奖品
     * @return mixed|\think\response\Json
     */
    public function prizeEdit()
    {
        $prize = new PrizeModel();
        if (request()->isPost()) {
            $param = input('post.');
            $flag = $prize->editPrize($param);
            return json(msg($flag['code'], $flag['data'], lang($flag['msg'])));
        }
        $id = input('param.id');
        View::assign([
            'prize' => $prize->findPrize($id),
            'prize_type' => config('prize_type'),
            'status' => config('app.status'),
        ]);
        return View::fetch();
    }

    /**
     * 中奖记录
     * @return mixed|\think\response\Json
     */
    public function awardList()
    {

        $prize_type = config('prize_type');
        if (request()->isAjax() || $this->request->isPost()) {
            $prizeLog = new PrizeLogModel();
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $export = $this->request->param("export/d", 0);

            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (!empty($param['searchText'])) {
                $where[] = ['p.name', 'like', '%' . $param['searchText'] . '%'];
            }
            if (!empty($param['mobile'])) {
                $where[] = ['hu.mobile', 'like', '%' . $param['mobile'] . '%'];
            }
            if (isset($param['userType']) && $param['userType'] >= 0) {
                $where[] = ['hu.is_xuni', '=', $param['userType']];
            }
            if (isset($param['type']) && $param['type'] >= 0) {
                $where[] = ['type', '=', $param['type']];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time = strtotime($param['endTime'] . " 23:59:59");
                $where[] = ['p.create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            if ($export) {
                $offset = 0;
                $limit = 1000000; //最多一百万条，再多xlx也打不开
            }
            $selectResult = $prizeLog->getPrizeLogByWhere($where, $offset, $limit);
            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['value'] = $this->prizeTypeValue($vo['type'], $vo['value']);
                $selectResult[$key]['money'] = i18n_currency($vo['money']);
                $selectResult[$key]['type'] = $prize_type[$vo['type']];
                $selectResult[$key]['user_type'] = $vo['is_xuni'] ? '是' : '不是';
                $selectResult[$key]['num'] = $vo['num'] == '-1' ? '无限量' : $vo['num'];
                $selectResult[$key]['probability'] = $vo['probability'] . ' (' . round($vo['probability'] / 10000, 8) . '%)';
                $selectResult[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = $prizeLog->getAllPrizeLog($where);  //总数据
            $return['rows'] = $selectResult;
            if ($export) {
                $header = [
                    "id" => "记录ID",
                    "nick_name" => "昵称",
                    "mobile" => "手机号",
                    "user_type" => "虚拟账号",
                    "money" => "抽奖支付金额",
                    "name" => "奖品名称",
                    "num" => "奖品库存",
                    "type" => "奖品类型",
                    "value" => "奖品类型对应数值",
                    "probability" => "中奖几率",
                    "create_time" => "抽奖时间",
                ];
                return genCsv($header, $return['rows'], "中奖记录");
            }
            $return['total_data']['total_money'] = $prizeLog->whereSql($where)->sum('money');  //总数据
            if (isset($param['type']) && ($param['type'] == 0 || $param['type'] == -1)) {
                $return['total_data']['total_red'] = $prizeLog->whereSql(array_merge($where, ['type' => 0]))->sum('value');  //红包
            }
            if (isset($param['type']) && ($param['type'] == 1 || $param['type'] == -1)) {
                $return['total_data']['total_grab'] = $prizeLog->whereSql(array_merge($where, ['type' => 1]))->sum('value');  //抢单
            }
            if (isset($param['type']) && ($param['type'] == 2 || $param['type'] == -1)) {
                $return['total_data']['total_entity'] = $prizeLog->whereSql(array_merge($where, ['type' => 2]))->count();  //实物
            }
            if (isset($param['type']) && ($param['type'] == 3 || $param['type'] == -1)) {
                $return['total_data']['total_thank'] = $prizeLog->whereSql(array_merge($where, ['type' => 3]))->count();  //谢谢参与
            }
            return json($return);
        }
        View::assign('prize_type', $prize_type);
        return View::fetch();
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id)
    {
        return [
            '编辑' => [
                'auth' => 'turntable/prizeEdit',
                'href' => url('turntable/prizeEdit', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            /*'删除' => [
                'auth' => 'level/leveldel',
                'href' => "javascript:levelDel(" .$id .")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]*/
        ];
    }

    private function prizeTypeValue($type, $value = '')
    {
        if ($type == 0) {
            return '返现:' . $value;
        } else if ($type == 1) {
            return '增加抢单次数:' . $value;
        }
        if (empty($value)) {
            return '无';
        }
        return $value;
    }
}
