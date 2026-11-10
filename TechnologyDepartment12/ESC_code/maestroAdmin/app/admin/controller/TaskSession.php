<?php

namespace app\admin\controller;

use app\common\model\TaskSessionModel;
use app\common\repository\LevelRepository;
use think\facade\Cache;
use think\facade\View;
use think\response\Json;

class TaskSession extends Base
{
    /**
     * 任务时段列表
     */
    public function index()
    {
        if (request()->isAjax()) {
            $where = [];
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $taskSessionModel = new TaskSessionModel();
            $total = $taskSessionModel->getTaskSessionCount($where);
            $list = $taskSessionModel->getTaskSessionList($where, $offset, $limit)->toArray();
            foreach ($list as &$item) {
                $item['status_text'] = TaskSessionModel::$statusMap[$item['status']];
                $item['operate'] = showOperate([
                    '编辑' => [
                        'auth' => 'taskSession/edit',
                        'href' => url('taskSession/edit', ['id' => $item['id']])->build(),
                        'btnStyle' => 'primary',
                        'icon' => 'fa fa-paste'
                    ],
                    '删除' => [
                        'auth' => 'taskSession/del',
                        'href' => "javascript:del(" . $item['id'] . ")",
                        'btnStyle' => 'danger',
                        'icon' => 'fa fa-trash-o'
                    ]
                ]);
                $level_types = array_filter(explode(',', $item['level_types']));
                $item['level_types'] = '';
                foreach ($level_types as $level_type) {
                    $item['level_types'] .= (LevelRepository::$typeMap[$level_type] ?? '未知类型') . ',';
                }
                $item['level_types'] = trim($item['level_types'], ',');
            }

            return json(['total' => $total, 'rows' => $list]);
        }

        return View::fetch();
    }

    /**
     * 切换任务时段状态
     */
    public function change_status(): Json
    {
        $id = input('param.id/d');
        $taskSessionModel = new TaskSessionModel();

        $result = $taskSessionModel->toggleStatus($id);
        if ($result !== false) {
            insert_operation_log('任务时段:修改状态', ['id' => $id]);
            $this->clearCache();
            return json(msg(1, [], lang('修改成功')));
        }
        return json(msg(-1, [], lang('修改失败')));
    }

    /**
     * 添加/编辑任务时段
     */
    public function edit()
    {
        $id = input('param.id/d', 0);
        $taskSessionModel = new TaskSessionModel();

        if (request()->isPost()) {
            $param = input('post.');
            $session = $param['session'];
            $sort = $param['sort'];
            $level_types = $param['level_types'];
            $session_arr = explode(' - ', $session);
            $start_time = $session_arr[0];
            $end_time = $session_arr[1];
            $data = [
                'start_time' => $start_time,
                'end_time' => $end_time,
                'sort' => $sort,
                'level_types' => ',' . $level_types . ',',
            ];

            if ($id > 0) {
                $result = $taskSessionModel->updateTaskSession($data, $id);
            } else {
                $result = $taskSessionModel->addTaskSession($data);
            }

            if (false === $result) {
                $this->error(lang('操作失败'));
            }
            insert_operation_log('任务时段:' . ($id > 0 ? '修改' : '添加'), $data);
            $this->clearCache();
            $this->success(lang('提交成功'), '', 'close');
        }

        $info = [];
        if ($id > 0) {
            $info = $taskSessionModel->getOneTaskSession($id);
        } else {
            $level_id = input('param.level_id/d');
            $info = [
                'level_id' => $level_id,
                'start_time' => date('H:i:s'),
                'end_time' => date('H:i:s'),
                'level_types' => '',
            ];
        }
        $info_level_types = explode(',', $info['level_types']);
        $level_types = LevelRepository::$typeMap;
        View::assign('level_types', $level_types);
        View::assign('info_level_types', $info_level_types);
        View::assign('info', $info);
        return View::fetch();
    }

    /**
     * 删除任务时段
     */
    public function del()
    {
        $id = input('param.id/d');
        $taskSessionModel = new TaskSessionModel();
        $result = $taskSessionModel->deleteTaskSession($id);

        if ($result !== false) {
            insert_operation_log('任务时段:删除', ['id' => $id]);
            $this->clearCache();
            return json(msg(1, [], lang('删除成功')));
        }
        return json(msg(-1, [], lang('删除失败')));
    }

    public function clearCache()
    {
        $redis = Cache::store('redis');
        $redis_key = config("redis_cache_key.task_session_list");
        $redis->delete($redis_key);
        $redis_key = config("redis_cache_key.task_session_list_all");
        $redis->delete($redis_key);
    }
}
