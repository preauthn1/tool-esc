<?php

namespace app\admin\controller;

use app\admin\transform\PddDrawRecordTransform;
use app\admin\transform\PddTransform;
use app\common\model\PddTurntableCountModel;
use app\common\model\PddTurntableModel;
use app\common\model\PddTurntablePrizeConfigModel;
use app\common\model\PddTurntablePrizeModel;
use app\common\repository\PddRepository;
use Carbon\Carbon;
use think\facade\Db;
use think\facade\View;

class Pdd extends Base
{

    //用户列表
    public function index(PddRepository $pdd_repository)
    {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            $selectResult = $pdd_repository->where($where)
                ->order(['id' => 'DESC'])
                ->paginate([
                    'page' => $offset,
                    'list_rows' => $limit
                ]);
            $data = (new PddTransform())->transformPages($selectResult->toArray());
            return json($data);
        }
        return View::fetch();
    }

    // 添加用户
    public function add()
    {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.', '', 'trim');
            $param['start_time'] = strtotime($param['start_time']);
            $param['end_time'] = strtotime($param['end_time']);

            if ($id > 0) {
                $result = (new PddTurntableModel())->where('id', $id)->update($param);
            } else {
                $result = PddTurntableModel::create($param);
                // 创建活动，同时创建奖品列表
                $prizes = [];
                for ($i = 1; $i <= 8; $i++) {
                    $prizes[] = [
                        'turntable_id' => $result->id,
                        'name' => '奖项' . $i,
                        'value' => '',
                        'status' => 1,
                        'img' => '',
                        'sort' => $i,
                        'probability' => 0,
                    ];
                }
                $result->prizes()->saveAll($prizes);
            }

            $this->success(lang('提交成功'), '', 'close');
        }

        $info = PddTurntableModel::where('id', $id)->find();
        if (!empty($info)) {
            $info = (new PddTransform)->transform($info);
        } else {
            $info = [
                'id' => '',
                'title' => '',
                'start_time' => date('Y-m-d H:i:s', time() - 3600 * 12),
                'end_time' => date('Y-m-d H:i:s'),
                'status' => 1,
                'fixed_times' => 4,
            ];
        }
        View::assign([
            "info" => $info
        ]);
        return View::fetch();
    }

    // 删除用户
    public function del()
    {
        $id = $this->request->param('id/d');
        $model = new PddTurntableModel();
        $flag = $model->where('id', $id)->delete();
        if ($flag) {
            return json(msg(1, url('index')->build(), lang('删除成功')));
        } else {
            return json(msg(-1, url('index')->build(), lang('删除失败')));
        }
    }

    public function prize()
    {
        $id = $this->request->param('id/d');
        $turntable = PddTurntableModel::where('id', $id)->find();
        $prizes = PddTurntablePrizeModel::where('turntable_id', $id)
            ->order(['sort' => 'ASC'])
            ->select();

        View::assign([
            'turntable' => $turntable,
            'prizes' => $prizes
        ]);
        return View::fetch();
    }

    public function prize_save()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            $prizes = $params['prizes'];

            Db::startTrans();
            try {
                foreach ($prizes as $prize) {
                    PddTurntablePrizeModel::update($prize);
                }
                Db::commit();
                return json(['code' => 1, 'msg' => '保存成功']);
            } catch (\Exception $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '保存失败：' . $e->getMessage()]);
            }
        }
        return json(['code' => 0, 'msg' => '非法请求']);
    }

    public function prize_config()
    {
        $id = $this->request->param('id/d');
        $prize = PddTurntablePrizeModel::where('id', $id)->find();
        if (!$prize) {
            $this->error('奖品不存在');
        }

        // 获取配置信息
        $config = PddTurntablePrizeConfigModel::where('prize_id', $id)->find();
        if (empty($config)) {
            $config = [
                'id' => '',
                'prize_id' => $id,
                'initial_min' => 0,
                'initial_max' => 0,
                'task_period' => 0,
                'stage_1_min' => 0,
                'stage_1_max' => 0,
                'stage_1_high_min' => 0,
                'stage_1_high_max' => 0,
                'stage_1_high_prob' => 0.00,
                'stage_1_middle_min' => 0,
                'stage_1_middle_max' => 0,
                'stage_1_middle_prob' => 0.00,
                'stage_1_low_min' => 0,
                'stage_1_low_max' => 0,
                'stage_1_low_prob' => 0.00,
                'stage_2_min' => 0,
                'stage_2_max' => 0,
                'stage_2_high_min' => 0,
                'stage_2_high_max' => 0,
                'stage_2_high_prob' => 0.00,
                'stage_2_middle_min' => 0,
                'stage_2_middle_max' => 0,
                'stage_2_middle_prob' => 0.00,
                'stage_2_low_min' => 0,
                'stage_2_low_max' => 0,
                'stage_2_low_prob' => 0.00,
                'stage_3_min' => 0,
                'stage_3_max' => 0,
                'stage_3_high_min' => 0,
                'stage_3_high_max' => 0,
                'stage_3_high_prob' => 0.00,
                'stage_3_middle_min' => 0,
                'stage_3_middle_max' => 0,
                'stage_3_middle_prob' => 0.00,
                'stage_3_low_min' => 0,
                'stage_3_low_max' => 0,
                'stage_3_low_prob' => 0.00,
            ];
        }
        View::assign([
            'prize' => $prize,
            'config' => $config
        ]);
        return View::fetch();
    }

    public function prize_config_save()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            Db::startTrans();
            try {
                $config = PddTurntablePrizeConfigModel::where('prize_id', $params['prize_id'])->find();
                if ($config) {
                    $config->update($params);
                } else {
                    PddTurntablePrizeConfigModel::create($params);
                }

                Db::commit();
                return json(['code' => 1, 'msg' => '配置保存成功']);
            } catch (\Exception $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '配置保存失败：' . $e->getMessage()]);
            }
        }
        return json(['code' => 0, 'msg' => '非法请求']);
    }

    // 抽奖记录
    public function drawRecord()
    {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = $param['pageNumber'];
            $is_xuni = $param['is_xuni'];
            $where = [];
            $where[] = ['record_id', '>', 0];

            if ($param['user_id']) {
                $where[] = ['user_id', '=', $param['user_id']];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $end_time   = strtotime($param['endTime'] . " 23:59:59");
                $where[] = ['pdd_turntable_count_model.update_time', 'BETWEEN', [$start_time, $end_time]];
            }
            if (!empty($param['startTime']) && empty($param['endTime'])) {
                $start_time = strtotime($param['startTime'] . " 00:00:00");
                $where[] = ['pdd_turntable_count_model.update_time', '>=', $start_time];
            }
            if (!empty($param['endTime']) && empty($param['startTime'])) {
                $end_time   = strtotime($param['endTime'] . " 23:59:59");
                $where[] = ['pdd_turntable_count_model.update_time', '<=', $end_time];
            }

            if (in_array($is_xuni, [0, 1])) {
                $query = PddTurntableCountModel::haswhere('user', ['is_xuni' => $is_xuni]);
            } else {
                $query = PddTurntableCountModel::haswhere('user');
            }
            $selectResult = $query->with(['user', 'record'])
                ->where($where)
                ->order(['update_time' => 'DESC'])
                ->paginate([
                    'page'      => $offset,
                    'list_rows' => $limit
                ]);
            $data = (new PddDrawRecordTransform())->transformPages($selectResult->toArray());
            return json($data);
        }
        return View::fetch();
    }
}
