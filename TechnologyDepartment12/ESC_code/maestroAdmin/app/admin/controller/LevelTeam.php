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
use app\admin\model\LevelTeamModel;
use app\admin\transform\LevelTeamTransform;
use app\common\model\LevelTeamInvestTimeModel;
use app\common\model\UserModel;
use app\common\repository\LevelRepository;
use app\common\repository\LevelTeamRepository;
use app\common\repository\TaskSessionRepository;
use think\facade\Cache;
use think\facade\View;

class LevelTeam extends Base
{

    //level列表
    public function index()
    {
        $param = input('param.');
        $user_id = $param['id'];
        if (request()->isAjax()) {
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $page = $param['pageNumber'];
            $where = [];
            $where[] = ['is_show', '=', 1];
            $where[] = ['type', 'in', [LevelRepository::TYPE_NORMAL, LevelRepository::TYPE_PROMOTION]];
            if (!empty($param['searchText'])) {
                $where[] = ['level_name', 'like', '%'.$param['searchText'].'%'];
            }
            $level_repository = new LevelRepository();
            $selectResult = $level_repository->with([
                'level_team' => function ($query) use ($user_id) {
                    $query->where('user_id', $user_id);
                }
            ])->where($where)->paginate(['page' => $page, 'list_rows' => $limit]);;
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['user_id'] = $user_id;
            }
            $result = (new LevelTeamTransform())->transformPages($selectResult->toArray());

            return json($result);
        }
        View::assign('id', $user_id);
        return View::fetch();
    }

    public function add()
    {
        $id = $this->request->param("id/d");
        $user_id = $this->request->param("user_id/d");
        $level_id = $this->request->param("level_id/d");

        $model = new LevelTeamModel();
        if (request()->isPost()) {
            $param = input('post.');
            // 获取下一场开始时间
            $task_session = (new TaskSessionRepository())->getCurrentTaskSession();
            if ($task_session) {
                $overdue_time = strtotime(date('Y-m-d '.$task_session->end_time));
            } else {
                $next_task_session = (new TaskSessionRepository())->getNextTaskSession();
                $overdue_time = strtotime(date('Y-m-d '.$next_task_session->end_time));
            }
            $param['overdue_time'] = $overdue_time;
            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                unset($param['id']);
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            } else {
                (new LevelTeamRepository())->clearCache($user_id, $level_id);
                insert_operation_log('代理会员等级:'.($id > 0 ? '修改' : '添加'), $param, $param['user_id']);
            }
            $this->success(lang('提交成功'), '', 'close');
        }
        $info = $model->where('id', $id)->where('user_id', $user_id)->where('level_id', $level_id)->find();
        if (empty($info)) {
            $info = [
                'level_id'          => $level_id,
                'user_id'           => $user_id,
                'unlock_time_start' => '00:00:00',
                'unlock_time_end'   => '23:59:59'
            ];
        }
        $types = LevelRepository::$typeMap;
        View::assign('types', $types);
        View::assign("info", $info);
        return View::fetch();
    }

    // 删除level
    public function levelDel()
    {
        $id = input('param.id');
        $role = new LevelModel();
        $flag = $role->delLevel($id);
        return json(msg($flag['code'], $flag['data'], lang($flag['msg'])));
    }

    // 修改任务状态
    public function changeStatus()
    {
        $param = $this->request->param();

        $field = '';
        switch ($param['type']) {
            case 'status':
                $field = 'status';
                break;
            case 'subordinate':
                $field = 'subordinate';
                break;
            default:
                $this->error(lang('参数错误'));
        }
//        if ($param['val'] == 1) { // 停用
//            $overdue_time = time();
//        } else {
//            // 获取下一场开始时间
//            $task_session = (new TaskSessionRepository())->getCurrentTaskSession();
//            if ($task_session) {
//                $overdue_time = strtotime(date('Y-m-d '.$task_session->end_time));
//            } else {
//                $next_task_session = (new TaskSessionRepository())->getNextTaskSession();
//                if ($next_task_session->end_time < date('H:i:s')) {
//                    $overdue_time = strtotime(date('Y-m-d '.$next_task_session->end_time)) + 3600*24;
//                } else {
//                    $overdue_time = strtotime(date('Y-m-d '.$next_task_session->end_time));
//                }
//            }
//        }
        $res = LevelTeamModel::update([
            $field => !$param['val'],
//            'overdue_time' => $overdue_time
        ], ['id' => $param['id']]);
        if ($res) {
            insert_operation_log('代理会员等级:'.($param['id'] > 0 ? '修改' : '添加'), $param);
            (new LevelTeamRepository())->clearCache($param['user_id'], $param['level_id']);
            $level_team_repository =  new LevelTeamRepository();
            $level_team = $level_team_repository->find($param['id']);

            if ($level_team->subordinate == 1 && $level_team->status == 1) {
                // 给下级赋值
                // 获取所有下级
                $team_ids = UserModel::where('tree_id', 'like', "%/{$level_team->user_id}/%")
                    ->where('tree_id', 'not like', '%/'.$level_team->user_id.'/')
                    ->column('id');

                foreach ($team_ids as $user_id) {
                    // 查找用户是否有广告位比例设置
                    $sub_user_level_team = $level_team_repository->findWhere([
                        'user_id' => $user_id,
                        'level_id' => $level_team->level_id
                    ]);
                    if ($sub_user_level_team) {
                        $sub_user_level_team->invest_rate = $level_team->invest_rate;
                        $sub_user_level_team->active_rate = $level_team->active_rate;
                        $sub_user_level_team->invest_max_rate = $level_team->invest_max_rate;
                        $sub_user_level_team->invest_num = $level_team->invest_num;
                        $sub_user_level_team->invest_profit = $level_team->invest_profit;
                        $sub_user_level_team->invest_day = $level_team->invest_day;
                        $sub_user_level_team->invest_period_type = $level_team->invest_period_type;
                        $sub_user_level_team->status = $level_team->status;
                        $sub_user_level_team->save();
                        (new LevelTeamRepository())->clearCache($user_id, $param['level_id']);
                    } else {
                        $level_team_repository->create([
                            'user_id'            => $user_id,
                            'level_id'           => $level_team->level_id,
                            'invest_rate'        => $level_team->invest_rate,
                            'active_rate'        => $level_team->active_rate,
                            'invest_max_rate'    => $level_team->invest_max_rate,
                            'invest_num'         => $level_team->invest_num,
                            'invest_profit'      => $level_team->invest_profit,
                            'invest_day'         => $level_team->invest_day,
                            'invest_period_type' => $level_team->invest_period_type,
                            'status'             => $level_team->status,
                            'overdue_time'       => 0
                        ]);
                    }
                }
            }
            $this->success(lang('修改成功'));
        }

        $this->error(lang('修改失败'));
    }

    /**
     * 理财时段列表
     */
    public function investTime()
    {
        $param = input('param.');
        $level_id = $param['level_id'];
        $user_id = $param['user_id'];
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $where[] = ['level_id', '', $level_id];
            $where[] = ['user_id', '', $user_id];
            $investTimeModel = new LevelTeamInvestTimeModel();

            $total = $investTimeModel->getInvestTimeCount($where);
            $list = $investTimeModel->getInvestTimeList($where, $offset, $limit);

            foreach ($list as &$item) {
                $item['status_text'] = LevelTeamInvestTimeModel::$statusMap[$item['status']];
                $item['operate'] = showOperate([
                    '编辑' => [
                        'auth'     => 'levelTeam/investTimeEdit',
                        'href'     => url('levelTeam/investTimeEdit', ['id' => $item['id']])->build(),
                        'btnStyle' => 'primary',
                        'icon'     => 'fa fa-paste'
                    ],
                    '删除' => [
                        'auth'     => 'levelTeam/investTimeDel',
                        'href'     => "javascript:investTimeDel(".$item['id'].")",
                        'btnStyle' => 'danger',
                        'icon'     => 'fa fa-trash-o'
                    ]
                ]);
            }

            return json(['total' => $total, 'rows' => $list]);
        }

        View::assign('user_id', $user_id);
        View::assign('level_id', $level_id);
        return View::fetch();
    }

    /**
     * 切换理财时段状态
     */
    public function changeInvestTimeStatus()
    {
        $id = input('param.id/d');
        $investTimeModel = new LevelTeamInvestTimeModel();

        $result = $investTimeModel->toggleStatus($id);
        if ($result !== false) {
            insert_operation_log('会员等级理财时段:修改状态', ['id' => $id]);
            return json(msg(1, [], lang('修改成功')));
        }
        return json(msg(-1, [], lang('修改失败')));
    }

    /**
     * 添加/编辑理财时段
     */
    public function investTimeEdit()
    {
        $id = input('param.id/d', 0);
        $investTimeModel = new LevelTeamInvestTimeModel();

        if (request()->isPost()) {
            $param = input('post.');

            $data = [
                'level_id'    => intval($param['level_id']),
                'user_id'    => intval($param['user_id']),
                'start_time'  => $param['start_time'],
                'end_time'    => $param['end_time'],
                'invest_rate' => floatval($param['invest_rate']),
                'profit_rate' => floatval($param['profit_rate'])
            ];

            if ($id > 0) {
                $result = $investTimeModel->updateInvestTime($data, $id);
            } else {
                $result = $investTimeModel->addInvestTime($data);
            }

            if (false === $result) {
                $this->error(lang('操作失败'));
            }
            insert_operation_log('会员等级理财时段:'.($id > 0 ? '修改' : '添加'), $data);
            $this->success(lang('提交成功'), '', 'close');
        }

        $info = [];
        if ($id > 0) {
            $info = $investTimeModel->getOneInvestTime($id);
        } else {
            $level_id = input('param.level_id/d');
            $user_id = input('param.user_Id/d');
            $info = ['level_id' => $level_id, 'user_id' => $user_id];
        }

        View::assign('info', $info);
        return View::fetch();
    }

    /**
     * 删除理财时段
     */
    public function investTimeDel()
    {
        $id = input('param.id/d');
        $investTimeModel = new LevelTeamInvestTimeModel();
        $result = $investTimeModel->deleteInvestTime($id);

        if ($result !== false) {
            insert_operation_log('会员等级理财时段:删除', ['id' => $id]);
            return json(msg(1, [], lang('删除成功')));
        }
        return json(msg(-1, [], lang('删除失败')));
    }
}
